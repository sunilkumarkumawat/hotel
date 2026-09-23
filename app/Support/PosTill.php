<?php

namespace App\Support;

use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\FolioCharge;
use App\Models\Master\TaxMaster;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosAuditLog;
use App\Models\Pos\PosInvoice;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosModifier;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosPayment;
use App\Models\Pos\PosTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The till: one order, from the moment a table is touched to the moment it is
 * paid for.
 *
 * Every screen that can change an order goes through this class — the billing
 * screen, the table view's shift and cancel, the Kitchen Display System, the
 * settle dialog. That is deliberate. Totals are the one thing in a POS that
 * absolutely may not have two opinions, and the fastest way to end up with two
 * is to let a controller do its own arithmetic "just this once".
 *
 * Three rules run through the whole class:
 *
 * 1. **A line that has been sent to the kitchen is not a line you may quietly
 *    edit.** `kot_no = 0` means still being typed; anything above zero has been
 *    cooked or is being cooked, so changing it writes to the audit log.
 * 2. **Prices are read from the menu, never from the form.** The browser posts
 *    an item id and a quantity. What that item costs is this server's business.
 * 3. **The order is re-totalled after every change.** There is no "recalculate"
 *    button anywhere in the system, because there is no moment at which the
 *    stored totals are allowed to be stale.
 */
class PosTill
{
    /**
     * The one tax choice that only makes sense on a till.
     *
     * "Each item's own" — a restaurant where the food is taxed at one rate and
     * the bar at another sets its rates in Setup → Items and picks this once.
     * Everything else in the app resolves through {@see Tax}.
     */
    public const TAX_PER_ITEM = 'item';

    public function __construct(private PosOrder $order) {}

    /**
     * What a Tax dropdown on an order offers.
     *
     * "No Tax" first, because that is where a new order opens.
     *
     * @return array<string, string>
     */
    public static function taxChoices(?int $branchId = null): array
    {
        return [Tax::NONE => 'No Tax', self::TAX_PER_ITEM => "Each item's own tax"]
            + Tax::options($branchId);
    }

    /** Anything the form could post, turned into one of the choices above. */
    public static function normaliseTaxChoice(string|int|null $choice): string
    {
        $choice = strtolower(trim((string) $choice));

        return $choice === self::TAX_PER_ITEM ? self::TAX_PER_ITEM : Tax::normalise($choice);
    }

    public static function for(PosOrder $order): self
    {
        return new self($order);
    }

    public function order(): PosOrder
    {
        return $this->order;
    }

    /*
    |--------------------------------------------------------------------------
    | Opening an order
    |--------------------------------------------------------------------------
    */

    /**
     * The open order on a table, or a new one.
     *
     * Find-or-create rather than create: two waiters tapping table 7 at the
     * same moment must land on the same bill, not open two.
     */
    public static function atTable(PosTable $table, int $branchId, ?int $userId = null): self
    {
        $existing = PosOrder::query()
            ->where('branch_id', $branchId)
            ->where('pos_table_id', $table->id)
            ->onFloor()
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return new self($existing);
        }

        return self::start([
            'branch_id' => $branchId,
            'outlet_id' => $table->outlet_id,
            'order_type' => 'dine_in',
            'pos_table_id' => $table->id,
            'table_no' => $table->name,
            'pax' => max(1, (int) $table->capacity ?: 1),
        ], $userId);
    }

    /**
     * The open room-service order for a stay, or a new one.
     *
     * The stay is carried on the order from the start, so a bill signed to the
     * room later does not have to ask who was in it at the time.
     */
    public static function inRoom(CheckIn $stay, int $outletId, int $branchId, ?int $userId = null): self
    {
        $existing = PosOrder::query()
            ->where('branch_id', $branchId)
            ->where('check_in_id', $stay->id)
            ->where('order_type', 'room_service')
            ->onFloor()
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return new self($existing);
        }

        return self::start([
            'branch_id' => $branchId,
            'outlet_id' => $outletId,
            'order_type' => 'room_service',
            'room_id' => $stay->room_id,
            'check_in_id' => $stay->id,
            'table_no' => $stay->room?->room_no ?: ('#' . $stay->room_id),
            'guest_name' => $stay->guest_name,
            'pax' => 1,
        ], $userId);
    }

    /** A counter order — take away or delivery — which holds no table at all. */
    public static function overCounter(int $outletId, string $type, int $branchId, ?int $userId = null): self
    {
        return self::start([
            'branch_id' => $branchId,
            'outlet_id' => $outletId,
            'order_type' => $type,
            'pax' => 1,
        ], $userId);
    }

    /** @param  array<string, mixed>  $attributes */
    private static function start(array $attributes, ?int $userId): self
    {
        $order = DB::transaction(function () use ($attributes, $userId) {
            return PosOrder::create($attributes + [
                'order_no' => PosOrder::nextNumber((int) $attributes['branch_id']),
                'opened_at' => now(),
                'status' => 'open',
                'created_by' => $userId,
            ]);
        });

        return new self($order);
    }

    /*
    |--------------------------------------------------------------------------
    | Changing what is on it
    |--------------------------------------------------------------------------
    */

    /**
     * Put an item on the order.
     *
     * Ordering the same thing twice adds to the line that has not gone to the
     * kitchen yet rather than making a second one — two rows of "1 × Masala
     * Dosa" is how a bill ends up unreadable. A line that has already been sent
     * is left alone and a fresh one is started beside it, which is also what
     * the kitchen wants: round two is a separate ticket.
     *
     * A line carrying modifiers is never merged into and never merged with —
     * "1 × Masala Dosa + Extra Cheese" ordered twice is two lines, not one
     * line of two, because merging would first have to prove two adds picked
     * the exact same options, and getting that wrong charges the wrong
     * surcharge for a saving nobody asked for.
     *
     * @param  array<int, int>  $modifierIds
     */
    public function addItem(PosMenuItem $item, float $qty = 1, ?string $remark = null, array $modifierIds = []): PosOrderItem
    {
        $this->mustBeOpen();

        $qty = round(max(0.01, $qty), 2);
        $price = $item->priceOn($this->order->pos_rate_plan_id);

        $modifiers = $this->resolveModifiers($item, $modifierIds);
        $modifierTotal = (float) $modifiers->sum('price');
        $hasModifiers = $modifiers->isNotEmpty();

        $line = $hasModifiers ? null : $this->order->items()
            ->unsent()
            ->where('pos_menu_item_id', $item->id)
            ->where('is_nc', 0)
            ->where('has_modifiers', 0)
            ->first();

        if ($line && (string) $line->remark === (string) $remark) {
            $line->qty = round((float) $line->qty + $qty, 2);
        } else {
            $line = new PosOrderItem([
                'pos_order_id' => $this->order->id,
                'pos_menu_item_id' => $item->id,
                'pos_department_id' => $item->pos_department_id,
                'item_name' => $item->name,
                'qty' => $qty,
                'remark' => $remark,
                'kot_no' => 0,
                'kitchen_status' => 'pending',
                'has_modifiers' => $hasModifiers,
                'sort' => (int) ($this->order->items()->max('sort') ?? 0) + 1,
            ]);
        }

        // Price and tax are re-read on every save, so an item re-priced during
        // service reaches a line that has not been sent yet. The modifier
        // surcharge is folded straight into the unit price rather than kept
        // separately, so recalculate() below — and every tax and discount
        // rule it runs — charges for "extra cheese" without having to know
        // modifiers exist at all.
        $line->price = $price + $modifierTotal;
        $line->tax_percent = $this->taxPercentFor($item);
        $line->save();

        if ($hasModifiers) {
            $line->modifiers()->createMany(
                $modifiers->map(fn (PosModifier $m) => [
                    'pos_modifier_id' => $m->id,
                    'name' => $m->name,
                    'price' => $m->price,
                ])->all()
            );
        }

        $this->recalculate();

        return $line->refresh();
    }

    /**
     * The modifiers a line may actually carry.
     *
     * Every id is checked against this item's own assigned groups, never
     * trusted from the form — the same rule the price itself follows. A
     * required group with nothing picked from it stops the add rather than
     * quietly selling the dish short of what its price assumes; a single-
     * select group handed two ids keeps only the first, the same way a radio
     * button can only ever mean one thing no matter what a hand-crafted
     * request posts.
     *
     * @param  array<int, int>  $modifierIds
     * @return Collection<int, PosModifier>
     */
    private function resolveModifiers(PosMenuItem $item, array $modifierIds): Collection
    {
        $groups = $item->modifierGroups()
            ->active()
            ->with(['modifiers' => fn ($q) => $q->active()])
            ->get();

        if ($groups->isEmpty()) {
            return collect();
        }

        $wanted = array_map('intval', $modifierIds);
        $picked = collect();

        foreach ($groups as $group) {
            $inGroup = $group->modifiers->whereIn('id', $wanted)->values();

            if ($group->selection_type === 'single' && $inGroup->count() > 1) {
                $inGroup = $inGroup->take(1);
            }

            if ($group->max_select && $inGroup->count() > $group->max_select) {
                $inGroup = $inGroup->take($group->max_select);
            }

            if ($group->is_required && $inGroup->isEmpty()) {
                throw new RuntimeException("Please choose {$group->name} for {$item->name}.");
            }

            $picked = $picked->merge($inGroup);
        }

        return $picked->unique('id')->values();
    }

    /**
     * Change how many of something.
     *
     * A quantity below the number already sent to the kitchen is refused: the
     * food exists. Taking it off is a removal, and removals are audited.
     */
    public function setQty(PosOrderItem $line, float $qty, ?int $userId = null): void
    {
        $this->mustBeOpen();
        $this->mustOwn($line);

        $qty = round($qty, 2);

        /*
         * Zero is not an edit. Taking a line off is a separate act with its own
         * permission, so this refuses rather than quietly deleting — otherwise
         * a waiter with edit-but-not-delete rights could remove a cooked item
         * by typing 0 into the quantity box.
         */
        if ($qty <= 0) {
            throw new RuntimeException(
                'Use Remove to take ' . $line->item_name . ' off the order.'
            );
        }

        $was = (float) $line->qty;

        if ($line->isSent() && $qty < $was) {
            $this->audit('item_modified', $line, $userId, sprintf(
                '%s cut from %s to %s after KOT %d',
                $line->item_name,
                $this->number($was),
                $this->number($qty),
                $line->kot_no
            ));
        }

        $line->qty = $qty;
        $line->save();

        $this->recalculate();
    }

    /** Take a line off. Anything the kitchen has seen is written to the log. */
    public function removeItem(PosOrderItem $line, ?int $userId = null, string $why = ''): void
    {
        $this->mustBeOpen();
        $this->mustOwn($line);

        if ($line->isSent()) {
            $this->audit('item_removed', $line, $userId, trim(
                $line->item_name . ' × ' . $this->number((float) $line->qty)
                . ' removed after KOT ' . $line->kot_no . ($why ? ' — ' . $why : '')
            ));
        }

        $line->delete();

        $this->recalculate();
    }

    /** A note for the kitchen — "no onion", "well done". */
    public function setRemark(PosOrderItem $line, ?string $remark): void
    {
        $this->mustOwn($line);

        $line->remark = $remark ?: null;
        $line->save();
    }

    /** Mark a line as no-charge, or put it back on the bill. */
    public function setNoCharge(PosOrderItem $line, bool $free): void
    {
        $this->mustBeOpen();
        $this->mustOwn($line);

        $line->is_nc = $free ? 1 : 0;
        $line->save();

        $this->recalculate();
    }

    /**
     * The things written across the top of the bill rather than on a line:
     * steward, covers, guest, discount, no-charge reason, remark.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyHeader(array $data): void
    {
        $this->mustBeOpen();

        $fields = [
            'pos_steward_id', 'pos_rate_plan_id', 'nc_type_id', 'nc_department_id',
            'guest_name', 'remark',
        ];

        // Set before the loop so `isDirty` below can see it move.
        if (array_key_exists('tax_choice', $data)) {
            $this->order->tax_choice = self::normaliseTaxChoice($data['tax_choice']);
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $this->order->{$field} = $data[$field] ?: null;
            }
        }

        if (array_key_exists('pax', $data)) {
            $this->order->pax = max(1, (int) $data['pax']);
        }

        if (array_key_exists('discount_percent', $data)) {
            $this->order->discount_percent = min(100, max(0, round((float) $data['discount_percent'], 2)));
        }

        if (array_key_exists('service_charge', $data)) {
            $this->order->service_charge = max(0, round((float) $data['service_charge'], 2));
        }

        if (array_key_exists('is_complimentary', $data)) {
            $this->order->is_complimentary = (bool) $data['is_complimentary'];
        }

        /*
         * Both flags are read BEFORE anything is saved. `save()` clears every
         * dirty flag on the model, so checking the second one after saving for
         * the first would silently skip it — change the price list and the tax
         * in the same submit and the tax would never have been re-applied.
         */
        $repriced = $this->order->isDirty('pos_rate_plan_id');
        $retaxed = $this->order->isDirty('tax_choice');

        $this->order->save();

        // A price list changed mid-order re-prices everything the kitchen has
        // not started on. Lines already sent keep what the guest was quoted.
        if ($repriced) {
            $this->reprice();
        }

        // Same rule for tax: an unsent line follows the new choice, a line the
        // kitchen already has keeps the tax it was quoted at.
        if ($retaxed) {
            $this->retax();
        }

        $this->recalculate();
    }

    /** Re-read the price of every line the kitchen has not been told about. */
    private function reprice(): void
    {
        $items = PosMenuItem::query()
            ->withTrashed()
            ->with('ratePlans')
            ->whereIn('id', $this->order->items()->unsent()->pluck('pos_menu_item_id')->filter())
            ->get()
            ->keyBy('id');

        foreach ($this->order->items()->unsent()->with('modifiers')->get() as $line) {
            if ($item = $items->get($line->pos_menu_item_id)) {
                // The modifier surcharge is folded into price alongside the
                // menu price (see addItem()), so a rate-plan change has to add
                // it back in — otherwise "extra cheese" would quietly vanish
                // the moment Happy Hours is switched on.
                $modifierTotal = $line->has_modifiers ? (float) $line->modifiers->sum('price') : 0.0;
                $line->price = $item->priceOn($this->order->pos_rate_plan_id) + $modifierTotal;
                $line->save();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Telling the kitchen
    |--------------------------------------------------------------------------
    */

    /**
     * Send everything that has not been sent yet.
     *
     * Round two prints only what round one did not — which is the whole reason
     * the rounds are numbered, and the reason a waiter can add a dessert to a
     * table without the kitchen cooking the starters again.
     *
     * @return int the KOT number that just went out, or 0 if there was nothing
     */
    public function fireKot(?int $userId = null): int
    {
        $this->mustBeOpen();

        $pending = $this->order->items()->unsent()->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $kot = (int) $this->order->kot_count + 1;
        $now = now();

        foreach ($pending as $line) {
            $line->kot_no = $kot;
            $line->fired_at = $now;
            $line->kitchen_status = 'pending';
            $line->save();
        }

        $this->order->kot_count = $kot;
        $this->order->save();

        return $kot;
    }

    /** What one KOT round contains — the ticket the kitchen prints. */
    public function kot(int $number): Collection
    {
        return $this->order->items()->where('kot_no', $number)->with(['department', 'modifiers'])->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Arithmetic
    |--------------------------------------------------------------------------
    */

    /**
     * Re-total the order and every line on it.
     *
     * The order-level discount is spread across the lines in proportion to what
     * they are worth, so the tax on each line is worked out on what the guest
     * actually pays for it. Without that, a 10% discount and a mixed 5%/18%
     * bill produce a tax figure that no auditor can reproduce.
     *
     * The last line absorbs the rounding remainder, so the split always adds
     * back up to the discount that was given.
     */
    public function recalculate(): void
    {
        $outlet = $this->order->outlet;
        $inclusive = $outlet?->tax_inclusive ? 'inclusive' : 'exclusive';
        $afterTax = (bool) $outlet?->discount_after_tax;

        $lines = $this->order->items()->get();

        // What each line is worth before any order-level discount.
        $gross = [];

        foreach ($lines as $line) {
            $value = round((float) $line->qty * (float) $line->price, 2);
            $gross[$line->id] = $line->is_nc || $this->order->is_complimentary
                ? 0.0
                : max(0, $value - (float) $line->discount);
        }

        $grossTotal = round(array_sum($gross), 2);

        $discount = $afterTax
            ? 0.0
            : round($grossTotal * (float) $this->order->discount_percent / 100, 2);

        $spread = self::spread($discount, $gross);

        $subTotal = 0.0;
        $taxTotal = 0.0;
        $netLines = 0.0;

        foreach ($lines as $line) {
            $base = round($gross[$line->id] - ($spread[$line->id] ?? 0), 2);
            $split = Money::split(max(0, $base), (float) $line->tax_percent, $inclusive);

            $line->amount = $split['amount'];
            $line->tax_amount = $split['tax'];
            $line->total_amount = $split['net'];
            $line->save();

            $subTotal += $split['amount'];
            $taxTotal += $split['tax'];
            $netLines += $split['net'];
        }

        // Discount after tax comes off the gross bill instead, which is the
        // other half of the outlet switch.
        $lateDiscount = $afterTax
            ? round($netLines * (float) $this->order->discount_percent / 100, 2)
            : 0.0;

        /*
         * A comp'd order carries no service charge either — "no charge" that
         * still asks for ten percent is not no charge.
         */
        $service = $this->order->is_complimentary ? 0.0 : (float) $this->order->service_charge;

        $beforeRounding = round($netLines - $lateDiscount + $service, 2);

        $rounded = round($beforeRounding);

        $this->order->sub_total = round($subTotal, 2);

        /*
         * Only real money taken off a real sub-total counts as discount. The
         * value given away on no-charge lines is NOT added here: those lines
         * never entered `sub_total`, so counting them would print a discount
         * bigger than the bill it is discounting. What was comped is readable
         * from the lines themselves, which is where it belongs.
         */
        $this->order->discount_total = round($discount + $lateDiscount, 2);
        $this->order->tax_total = round($taxTotal, 2);
        $this->order->round_off = round($rounded - $beforeRounding, 2);
        $this->order->net_amount = $rounded;
        $this->order->save();
    }

    /**
     * Split one figure across lines in proportion to their value.
     *
     * @param  array<int, float>  $gross
     * @return array<int, float>
     */
    private static function spread(float $amount, array $gross): array
    {
        $total = round(array_sum($gross), 2);

        if ($amount <= 0 || $total <= 0) {
            return [];
        }

        /*
         * Only lines that are worth something take a share. A no-charge line
         * has a gross of zero, and handing it the rounding remainder would put
         * a penny on a bill that says "no charge" — which is exactly the sort
         * of thing a guest notices and nobody can explain.
         */
        $paying = array_filter($gross, fn (float $value) => $value > 0);

        if (! $paying) {
            return [];
        }

        $out = [];
        $given = 0.0;
        $ids = array_keys($paying);
        $last = end($ids);

        foreach ($paying as $id => $value) {
            if ($id === $last) {
                // Whatever is left, so the parts add back up to the whole.
                $out[$id] = round($amount - $given, 2);

                continue;
            }

            $share = round($amount * $value / $total, 2);
            $out[$id] = $share;
            $given = round($given + $share, 2);
        }

        return $out;
    }

    /**
     * The tax this line carries — which is whatever the ORDER says, and
     * nothing at all unless the order says something.
     *
     * The old till took the item's own tax and fell back to the branch
     * default, so every bill came out taxed whether the hotel wanted it or not.
     * Now the order carries the choice and the choice starts at No Tax:
     *
     *   `none`   nothing. The default, and what a new order opens on.
     *   `item`   each line takes the tax its item names in Setup → Items;
     *            an item with no tax attached is untaxed. This is the old
     *            behaviour, kept as something you can pick.
     *   `<id>`   one tax from Masters → Tax across the whole order.
     */
    private function taxPercentFor(PosMenuItem $item): float
    {
        $choice = trim((string) $this->order->tax_choice);

        if ($choice === '' || $choice === Tax::NONE) {
            return 0.0;
        }

        if ($choice === self::TAX_PER_ITEM) {
            return $item->tax_master_id
                ? (float) (TaxMaster::query()->whereKey($item->tax_master_id)->value('percent') ?? 0)
                : 0.0;
        }

        return Tax::percent($choice, $this->order->branch_id);
    }

    /**
     * Re-read the tax on every line the kitchen has not been told about.
     *
     * Same rule as re-pricing: a line already sent keeps what the guest was
     * quoted, so changing the Tax dropdown halfway through service cannot
     * rewrite a KOT that is already on the pass.
     */
    private function retax(): void
    {
        $items = PosMenuItem::query()
            ->whereIn('id', $this->order->items()->pluck('pos_menu_item_id')->filter())
            ->get()
            ->keyBy('id');

        foreach ($this->order->items()->where('kot_no', 0)->get() as $line) {
            $item = $items->get($line->pos_menu_item_id);

            if (! $item) {
                continue;
            }

            $percent = $this->taxPercentFor($item);

            if ((float) $line->tax_percent !== $percent) {
                $line->update(['tax_percent' => $percent]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    /**
     * Print the bill.
     *
     * The order stops being editable at this point and the invoice number is
     * taken — which is why nothing before this moment consumes one. A bill
     * printed twice is one invoice printed twice, and the second print is
     * logged rather than renumbered.
     */
    public function bill(?int $userId = null): PosInvoice
    {
        if ($this->order->status === 'cancelled') {
            throw new RuntimeException('A cancelled order cannot be billed.');
        }

        if ($this->order->items()->count() === 0) {
            throw new RuntimeException('There is nothing on this order to bill.');
        }

        /*
         * Only a still-open order gets its last lines fired and its totals
         * redone. Calling this again on a billed order is a REPRINT — and
         * fireKot() would refuse it, which is what used to make the reprint
         * path unreachable and left Revenue Control's reprint counter stuck
         * at zero for every bill in the system.
         */
        if ($this->order->isOpen()) {
            $this->fireKot($userId);
            $this->recalculate();
        }

        return DB::transaction(function () use ($userId) {
            $invoice = $this->order->invoice()->first();

            if ($invoice) {
                $invoice->print_count = (int) $invoice->print_count + 1;
                $invoice->save();

                $this->audit('invoice_reprinted', null, $userId, sprintf(
                    'Invoice %s printed %d times',
                    $invoice->invoice_no,
                    $invoice->print_count
                ), (float) $invoice->net_amount, $invoice->id);

                return $invoice->refresh();
            }

            $invoice = PosInvoice::create([
                'branch_id' => $this->order->branch_id,
                'outlet_id' => $this->order->outlet_id,
                'pos_order_id' => $this->order->id,
                'invoice_no' => PosInvoice::nextNumber((int) $this->order->branch_id, $this->order->outlet),
                'invoice_at' => now(),
                'guest_name' => $this->order->guest_name,
                'check_in_id' => $this->order->check_in_id,
                'sub_total' => $this->order->sub_total,
                'discount_total' => $this->order->discount_total,
                'tax_total' => $this->order->tax_total,
                'net_amount' => $this->order->net_amount,
                'paid_amount' => 0,
                'print_count' => 1,
                'status' => 'open',
                'created_by' => $userId,
            ]);

            $this->order->status = 'billed';
            $this->order->save();

            return $invoice;
        });
    }

    /**
     * Take the money.
     *
     * Payments arrive as a list of `[pay_mode_id, amount, reference_no]`, so a
     * guest paying ₹800 cash and ₹1,200 by card is two rows against one bill
     * rather than a made-up "mixed" pay mode that no report can break down.
     *
     * A bill is settled when it is fully paid. Anything short stays open and
     * shows up on Unsettled Invoices, which is exactly where the manager needs
     * to see it.
     *
     * @param  list<array{pay_mode_id?: int|null, amount: float, reference_no?: string|null}>  $payments
     */
    public function settle(array $payments, ?int $userId = null): PosInvoice
    {
        /*
         * Add up what was entered BEFORE raising a bill. Otherwise a cashier
         * who submits an empty form consumes an invoice number, flips the
         * order to billed and then reads "no payment was entered" — with the
         * order now permanently uneditable.
         */
        $offered = 0.0;

        foreach ($payments as $payment) {
            $offered = round($offered + max(0, (float) ($payment['amount'] ?? 0)), 2);
        }

        if ($offered <= 0) {
            throw new RuntimeException('No payment was entered.');
        }

        $invoice = $this->order->invoice()->first() ?: $this->bill($userId);

        // A bill that is already paid for is not paid for again, whatever the
        // second click of a double-click was hoping to do.
        if ($invoice->status === 'settled' || $invoice->balance() <= 0) {
            throw new RuntimeException("Bill {$invoice->invoice_no} has already been paid.");
        }

        return DB::transaction(function () use ($invoice, $payments, $userId) {
            $taken = 0.0;

            foreach ($payments as $payment) {
                $amount = round((float) ($payment['amount'] ?? 0), 2);

                if ($amount <= 0) {
                    continue;
                }

                PosPayment::create([
                    'branch_id' => $invoice->branch_id,
                    'pos_invoice_id' => $invoice->id,
                    'pay_mode_id' => $payment['pay_mode_id'] ?? null,
                    'amount' => $amount,
                    'reference_no' => $payment['reference_no'] ?? null,
                    'paid_at' => now(),
                    'created_by' => $userId,
                ]);

                $taken = round($taken + $amount, 2);
            }

            $this->closeIfPaid($invoice, $userId);

            return $invoice->refresh();
        });
    }

    /**
     * Sign the bill to a room.
     *
     * The POS bill becomes one line on the guest's folio, so it leaves with
     * them at checkout rather than sitting in the restaurant's own unsettled
     * list forever. The folio line carries the invoice number, which is what
     * lets a guest querying "what is this ₹1,840" be answered in one lookup.
     */
    public function postToRoom(CheckIn $stay, ?int $userId = null): PosInvoice
    {
        $invoice = $this->order->invoice()->first() ?: $this->bill($userId);

        if ($stay->status !== 'in_house') {
            throw new RuntimeException('That guest is not in the house any more.');
        }

        if ($invoice->folio_charge_id) {
            return $invoice;
        }

        /*
         * What goes on the folio is what is still OWED, not the face value of
         * the bill. A guest who paid ₹500 cash and signed the rest must not
         * find the whole ₹1,840 on their room bill as well.
         */
        $owing = $invoice->balance();

        if ($owing <= 0) {
            throw new RuntimeException("Bill {$invoice->invoice_no} has nothing left to sign.");
        }

        return DB::transaction(function () use ($invoice, $stay, $userId, $owing) {
            $charge = FolioCharge::create([
                'branch_id' => $stay->branch_id,
                'check_in_id' => $stay->id,
                'charge_date' => now()->toDateString(),
                'charge_type' => 'service',
                'particulars' => trim(sprintf(
                    '%s — bill %s',
                    $this->order->outlet?->name ?: 'Point Of Sale',
                    $invoice->invoice_no
                )),
                'qty' => 1,
                'price' => $owing,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'amount' => $owing,
                'total_amount' => $owing,
                'created_by' => $userId,
            ]);

            $invoice->folio_charge_id = $charge->id;
            $invoice->folio_amount = $owing;
            $invoice->check_in_id = $stay->id;
            $invoice->guest_name = $invoice->guest_name ?: $stay->guest_name;
            $invoice->save();

            $this->order->check_in_id = $stay->id;
            $this->order->room_id = $this->order->room_id ?: $stay->room_id;
            $this->order->save();

            $this->closeIfPaid($invoice, $userId);

            return $invoice->refresh();
        });
    }

    /**
     * A no-charge bill: signed off against a reason, never against money.
     *
     * The reason is written here rather than through `applyHeader()`, which
     * refuses anything but an open order — a bill already printed is the most
     * common moment for somebody to decide it is being comped.
     */
    public function settleAsNoCharge(?int $reasonId, ?int $departmentId, ?int $userId = null): PosInvoice
    {
        $reasonId = $reasonId ?: $this->order->nc_type_id;

        if (! $reasonId) {
            throw new RuntimeException('A no-charge bill has to name a reason.');
        }

        if ($this->order->invoice()->first()?->status === 'settled') {
            throw new RuntimeException('This bill has already been settled.');
        }

        $this->order->nc_type_id = $reasonId;
        $this->order->nc_department_id = $departmentId ?: null;
        $this->order->is_complimentary = true;
        $this->order->save();

        // Re-total with the comp applied, even on a billed order: the whole
        // point is that the figure changes to zero.
        $this->recalculate();

        $invoice = $this->order->invoice()->first();

        if (! $invoice) {
            return $this->bill($userId);
        }

        // The bill was raised before it was comped, so its stored copy of the
        // totals is stale. Bring it back in step or Unsettled Invoices will go
        // on asking for money nobody owes.
        $invoice->fill([
            'sub_total' => $this->order->sub_total,
            'discount_total' => $this->order->discount_total,
            'tax_total' => $this->order->tax_total,
            'net_amount' => $this->order->net_amount,
        ])->save();

        $this->closeIfPaid($invoice, $userId);

        return $invoice->refresh();
    }

    /** Close the bill and the table once there is nothing left owing. */
    private function closeIfPaid(PosInvoice $invoice, ?int $userId): void
    {
        $paid = round((float) PosPayment::query()
            ->where('pos_invoice_id', $invoice->id)
            ->sum('amount'), 2);

        // A bill signed to a room is paid by the folio, not by a payment row —
        // for exactly the amount that was signed, which may be a part of it.
        if ($invoice->folio_charge_id) {
            $paid = round($paid + (float) $invoice->folio_amount, 2);
        }

        $invoice->paid_amount = min($paid, (float) $invoice->net_amount);

        if ($paid + 0.001 >= (float) $invoice->net_amount) {
            $invoice->status = 'settled';
            $invoice->settled_at = now();
            $invoice->save();

            $this->order->status = 'settled';
            $this->order->closed_at = now();
            $this->order->save();

            return;
        }

        $invoice->save();
    }

    /*
    |--------------------------------------------------------------------------
    | The table itself
    |--------------------------------------------------------------------------
    */

    /**
     * Move this order to another table.
     *
     * Refused if the destination already has a live order — merging two bills
     * is a different act with different consequences, and doing it silently
     * here is how a guest ends up paying for the next table's drinks.
     */
    public function shiftTo(PosTable $table, ?int $userId = null): void
    {
        if (! in_array($this->order->status, PosOrder::HOLDS_TABLE, true)) {
            throw new RuntimeException('Only an order still on the floor can be moved.');
        }

        /*
         * Cast both sides. `pos_table_id` is not cast on the model, so under
         * MySQL it can read back as the string "7" while the table's key is
         * the integer 7 — and `"7" === 7` is false, which used to make moving
         * a table to itself report that the table was busy with its own order.
         */
        if ((int) $this->order->pos_table_id === (int) $table->id) {
            return;
        }

        /*
         * A table in another outlet is a different till with a different bill
         * series. Moving there would leave the order and its invoice pointing
         * at two different outlets for ever, so it is refused rather than
         * silently reassigned.
         */
        if ((int) $table->outlet_id !== (int) $this->order->outlet_id) {
            throw new RuntimeException("{$table->name} belongs to a different outlet.");
        }

        $busy = PosOrder::query()
            ->where('branch_id', $this->order->branch_id)
            ->where('pos_table_id', $table->id)
            ->onFloor()
            ->exists();

        if ($busy) {
            throw new RuntimeException("{$table->name} already has an order running on it.");
        }

        $from = $this->order->table_no ?: 'counter';

        $this->order->pos_table_id = $table->id;
        $this->order->table_no = $table->name;
        $this->order->remark = trim(($this->order->remark ? $this->order->remark . ' · ' : '')
            . "Shifted {$from} → {$table->name}");
        $this->order->save();
    }

    /**
     * Cancel the order.
     *
     * Anything the kitchen had already been told about is written to the audit
     * log line by line. A table cancelled after three KOTs is not the same
     * event as one cancelled before any, and Revenue Control has to be able to
     * tell them apart.
     */
    public function cancel(string $reason, ?int $userId = null): void
    {
        if ($this->order->isSettled()) {
            throw new RuntimeException('A settled order cannot be cancelled.');
        }

        /*
         * Money already taken has to be given back at the till before the sale
         * can be voided. Cancelling over the top of it would leave payment rows
         * that no live invoice accounts for, and the drawer would be over at
         * cash-up with nothing to explain it.
         */
        if ($invoice = $this->order->invoice()->first()) {
            $paid = round((float) PosPayment::query()
                ->where('pos_invoice_id', $invoice->id)
                ->sum('amount'), 2);

            if ($paid > 0) {
                throw new RuntimeException(sprintf(
                    'Rs %s has already been taken against bill %s. Refund it first.',
                    number_format($paid, 2),
                    $invoice->invoice_no
                ));
            }

            if ($invoice->folio_charge_id) {
                throw new RuntimeException(
                    "Bill {$invoice->invoice_no} is on a guest's folio. Remove it there first."
                );
            }
        }

        DB::transaction(function () use ($reason, $userId) {
            foreach ($this->order->items()->sent()->get() as $line) {
                $this->audit('item_removed', $line, $userId, trim(
                    $line->item_name . ' × ' . $this->number((float) $line->qty)
                    . ' — order cancelled' . ($reason ? ': ' . $reason : '')
                ));
            }

            if ($invoice = $this->order->invoice()->first()) {
                $invoice->status = 'cancelled';
                $invoice->save();

                $this->audit('invoice_deleted', null, $userId, trim(
                    'Invoice ' . $invoice->invoice_no . ' cancelled' . ($reason ? ': ' . $reason : '')
                ), (float) $invoice->net_amount, $invoice->id);
            }

            $this->order->status = 'cancelled';
            $this->order->closed_at = now();
            $this->order->remark = trim(($this->order->remark ? $this->order->remark . ' · ' : '') . $reason);
            $this->order->save();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function mustBeOpen(): void
    {
        if (! $this->order->isOpen()) {
            throw new RuntimeException(
                'This order is ' . strtolower(PosOrder::STATUSES[$this->order->status] ?? $this->order->status)
                . ' and cannot be changed.'
            );
        }
    }

    /** A line posted from a form has to belong to the order it claims to. */
    private function mustOwn(PosOrderItem $line): void
    {
        if ((int) $line->pos_order_id !== (int) $this->order->id) {
            throw new RuntimeException('That line is not on this order.');
        }
    }

    private function audit(
        string $action,
        ?PosOrderItem $line,
        ?int $userId,
        string $particulars,
        ?float $amount = null,
        ?int $invoiceId = null
    ): void {
        PosAuditLog::create([
            'branch_id' => $this->order->branch_id,
            'action' => $action,
            'pos_order_id' => $this->order->id,
            'pos_invoice_id' => $invoiceId,
            'particulars' => mb_substr($particulars, 0, 250),
            'amount' => $amount ?? ($line ? round((float) $line->qty * (float) $line->price, 2) : 0),
            'happened_at' => now(),
            'created_by' => $userId,
        ]);
    }

    /** "2" rather than "2.00", but "2.5" when it matters. */
    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
