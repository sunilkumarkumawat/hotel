<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\FrontOffice\CheckIn;
use App\Models\Master\PayMode;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosNcType;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosSteward;
use App\Models\Pos\PosTable;
use App\Support\GuestMessage;
use App\Support\Notify;
use App\Support\PosFloor;
use App\Support\PosTill;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;


class PosController extends Controller
{
 
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $outlets = $this->outlets($branchId);
        $outlet = $this->pickOutlet($request, $outlets);

        if (! $outlet) {
            return view('pos.till.no-outlet');
        }
        $mode = $this->mode($request) === 'room' && $outlet->pos_room_service
            ? 'room'
            : 'restaurant';

        $floor = PosFloor::for($branchId, $outlet);

        if ($mode === 'room') {
            $tiles = $floor->rooms();
            $sections = collect([(object) ['group' => null, 'tables' => $tiles]]);
        } else {
            $sections = $floor->sections();
            $tiles = $floor->tiles($sections);
        }

        return view('pos.till.floor', [
            'outlets' => $outlets,
            'outlet' => $outlet,
            'mode' => $mode,
            'sections' => $sections,
            'summary' => $floor->summary($tiles),
            'states' => PosFloor::STATES,
            'counterTypes' => $this->counterTypes($outlet),
            'nav' => $this->nav($outlet),
        ]);
    }

    public function openTable(Request $request, int $table): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $seat = PosTable::query()->forBranch($branchId)->findOrFail($table);
        $till = PosTill::atTable($seat, $branchId, $request->user()?->user_id);

        return redirect()->route('point-of-sale.pos.order', $till->order()->id);
    }

    public function openRoom(Request $request, int $stay): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $outlet = $this->outletOrFail($request, $branchId);

        $checkIn = CheckIn::query()
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->with('room')
            ->findOrFail($stay);

        $till = PosTill::inRoom($checkIn, (int) $outlet->id, $branchId, $request->user()?->user_id);

        return redirect()->route('point-of-sale.pos.order', $till->order()->id);
    }

    public function openCounter(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $outlet = $this->outletOrFail($request, $branchId);

        $type = $request->string('order_type')->toString();

        if (! in_array($type, $this->counterTypes($outlet), true)) {
            return back()->with('error', 'This outlet does not take that kind of order.');
        }

        $till = PosTill::overCounter((int) $outlet->id, $type, $branchId, $request->user()?->user_id);

        return redirect()->route('point-of-sale.pos.order', $till->order()->id);
    }

    public function order(Request $request, int $order): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $chosen = $request->integer('category') ?: null;
        $term = trim($request->string('q')->toString());

        return view('pos.till.bill', [
            'order' => $posOrder,
            'outlet' => $posOrder->outlet,
            'outlets' => $this->outlets($branchId),
            'mode' => $posOrder->order_type === 'room_service' ? 'room' : 'restaurant',
            'lines' => $posOrder->items()->with(['department', 'modifiers'])->get(),
            'invoice' => $posOrder->invoice()->first(),
            'tree' => $this->menuTree($branchId, $posOrder->outlet_id),
            'items' => $this->menuItems($branchId, $posOrder, $chosen, $term),
            'chosen' => $chosen,
            'term' => $term,
            'stewards' => PosSteward::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'ncTypes' => PosNcType::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'departments' => PosDepartment::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'ratePlans' => $this->ratePlansFor($posOrder),
            'taxChoices' => PosTill::taxChoices($branchId),
            'payModes' => PayMode::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'tables' => $this->shiftTargets($branchId, $posOrder),
            'stay' => $posOrder->check_in_id
                ? CheckIn::query()->with('room')->find($posOrder->check_in_id)
                : null,
            'nav' => $this->nav($posOrder->outlet),
        ]);
    }

    public function addItem(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $data = $request->validate([
            'item_id' => 'required|integer',
            'qty' => 'nullable|numeric|min:0.01|max:999',
            'remark' => 'nullable|string|max:120',
            'modifiers' => 'nullable|array',
            'modifiers.*' => 'nullable|array',
            'modifiers.*.*' => 'nullable|integer',
        ]);

        $item = PosMenuItem::query()
            ->forBranch($branchId)
            ->sellable()
            ->with('ratePlans')
            ->findOrFail($data['item_id']);

        $modifierIds = collect($data['modifiers'] ?? [])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        return $this->act(
            fn () => PosTill::for($posOrder)->addItem($item, (float) ($data['qty'] ?? 1), $data['remark'] ?? null, $modifierIds),
            $request,
            $posOrder,
            $item->name . ' added.'
        );
    }
    public function scanItem(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $data = $request->validate([
            'barcode' => 'required|string|max:30',
        ]);

        $code = trim($data['barcode']);

        $item = PosMenuItem::query()
            ->forBranch($branchId)
            ->sellable()
            ->with(['modifierGroups' => fn ($q) => $q->active(), 'modifierGroups.modifiers' => fn ($q) => $q->active()])
            ->where('code', $code)
            ->first();

        if (! $item) {
            return back()->with('error', "No item is set up with the code \"{$code}\".");
        }

        $hasModifiers = $item->modifierGroups->contains(fn ($group) => $group->modifiers->isNotEmpty());

        if ($hasModifiers) {
            return redirect(route('point-of-sale.pos.order', $posOrder->id) . '#item-mods-' . $item->id)
                ->with('status', $item->name . ' scanned — choose its options below.');
        }

        return $this->act(
            fn () => PosTill::for($posOrder)->addItem($item),
            $request,
            $posOrder,
            $item->name . ' added.'
        );
    }

    public function updateItem(Request $request, int $order, int $line): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);
        $row = $this->line($posOrder, $line);

        $data = $request->validate([
            'qty' => 'nullable|numeric|min:0.01|max:999',
            'step' => 'nullable|integer|min:-99|max:99',
            'remark' => 'nullable|string|max:120',
            'is_nc' => 'nullable|boolean',
        ]);

        $till = PosTill::for($posOrder);
        $userId = $request->user()?->user_id;

        return $this->act(function () use ($till, $row, $data, $userId) {
            if (array_key_exists('remark', $data)) {
                $till->setRemark($row, $data['remark']);
            }

            if (array_key_exists('is_nc', $data)) {
                $till->setNoCharge($row, (bool) $data['is_nc']);
            }
            if (isset($data['step'])) {
                $till->setQty($row, (float) $row->qty + (int) $data['step'], $userId);
            } elseif (isset($data['qty'])) {
                $till->setQty($row, (float) $data['qty'], $userId);
            }
        }, $request, $posOrder, 'Order updated.');
    }

    public function removeItem(Request $request, int $order, int $line): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);
        $row = $this->line($posOrder, $line);
        $name = $row->item_name;

        return $this->act(
            fn () => PosTill::for($posOrder)->removeItem(
                $row,
                $request->user()?->user_id,
                $request->string('why')->toString()
            ),
            $request,
            $posOrder,
            $name . ' removed.'
        );
    }

    public function header(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);
        $data = $request->validate([
            'pos_steward_id' => ['nullable', 'integer', $this->ours('pos_stewards', $branchId)],
            'pos_rate_plan_id' => ['nullable', 'integer', $this->ours('pos_rate_plans', $branchId)],
            'nc_type_id' => ['nullable', 'integer', $this->ours('pos_nc_types', $branchId)],
            'nc_department_id' => ['nullable', 'integer', $this->ours('pos_departments', $branchId)],
            'guest_name' => 'nullable|string|max:150',
            'remark' => 'nullable|string|max:250',
            'pax' => 'nullable|integer|min:1|max:250',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'service_charge' => 'nullable|numeric|min:0|max:999999',
            'tax_choice' => ['nullable', 'string', Rule::in(array_keys(PosTill::taxChoices($branchId)))],
        ]);

        return $this->act(
            fn () => PosTill::for($posOrder)->applyHeader($data),
            $request,
            $posOrder,
            'Saved.'
        );
    }

    public function kot(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $number = 0;

        return $this->act(
            function () use ($posOrder, $request, &$number) {
                $number = PosTill::for($posOrder)->fireKot($request->user()?->user_id);
            },
            $request,
            $posOrder,
            function () use ($posOrder, &$number, $branchId) {
                if ($number) {
                    Notify::event('pos.kot')
                        ->title('KOT ' . $number . ' — ' . $posOrder->order_no)
                        ->body(trim(($posOrder->outlet?->name ?? '') . ' · ' . ($posOrder->table_no ?: 'counter')))
                        ->url(route('point-of-sale.kitchen-display'))
                        ->send();

                    $this->notifyGuestOfKot($posOrder, $number, $branchId);
                }

                return $number
                    ? "KOT {$number} sent to the kitchen."
                    : 'Everything on this order has already gone to the kitchen.';
            }
        );
    }

    private function notifyGuestOfKot(PosOrder $posOrder, int $kotNumber, int $branchId): void
    {
        $fresh = $posOrder->fresh();
        $stay = $fresh->check_in_id
            ? CheckIn::query()->where('branch_id', $branchId)->find($fresh->check_in_id)
            : null;

        if (! $stay) {
            return;
        }

        $items = PosOrderItem::query()
            ->where('pos_order_id', $fresh->id)
            ->where('kot_no', $kotNumber)
            ->with('menuItem')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        GuestMessage::send('guest.pos-order', $stay->mobile, [
            'guest' => $stay->guest_name,
            'guest_email' => $stay->reservation?->email,
            'where' => $fresh->order_type === 'room_service' ? $fresh->where_label : 'Table ' . $fresh->table_no,
            'items' => $items
                ->map(fn (PosOrderItem $i) => rtrim(rtrim(number_format((float) $i->qty, 2), '0'), '.')
                    . '× ' . ($i->menuItem?->name ?? 'Item'))
                ->implode(', '),
        ], $branchId);
    }

    public function bill(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        try {
            $invoice = PosTill::for($posOrder)->bill($request->user()?->user_id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Notify::event('pos.bill')
            ->title('Bill ' . $invoice->invoice_no . ' — ₹ ' . number_format((float) $invoice->net_amount, 2))
            ->body(trim(($posOrder->outlet?->name ?? '') . ' · ' . ($posOrder->table_no ?: $posOrder->order_no)))
            ->url(route('point-of-sale.pos.order', $posOrder->id))
            ->send();

        return redirect()
            ->route('point-of-sale.pos.order', $posOrder->id)
            ->with('status', "Bill {$invoice->invoice_no} is ready.")
            ->with('print', route('point-of-sale.pos.order.print', $posOrder->id));
    }

    public function settle(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);
        $till = PosTill::for($posOrder);
        $userId = $request->user()?->user_id;

        $how = $request->string('how')->toString();

        try {
            if ($how === 'room') {
                $stay = CheckIn::query()
                    ->where('branch_id', $branchId)
                    ->where('status', 'in_house')
                    ->findOrFail($request->integer('check_in_id'));

                $till->postToRoom($stay, $userId);
                $done = "Signed to room {$stay->room?->room_no}.";
            } elseif ($how === 'nc') {
                $request->validate([
                    'nc_type_id' => ['required', 'integer', $this->ours('pos_nc_types', $branchId)],
                    'nc_department_id' => ['nullable', 'integer', $this->ours('pos_departments', $branchId)],
                ]);

                $reason = PosNcType::query()->forBranch($branchId)->findOrFail($request->integer('nc_type_id'));

                if ($reason->requires_department && ! $request->integer('nc_department_id')) {
                    return back()->with('error', "\"{$reason->name}\" has to name the department that carries it.");
                }

                $till->settleAsNoCharge(
                    (int) $reason->id,
                    $request->integer('nc_department_id') ?: null,
                    $userId
                );

                $done = 'Settled as no charge.';
            } else {
                $data = $request->validate([
                    'payments' => 'required|array|min:1',
                    'payments.*.pay_mode_id' => 'nullable|integer',
                    'payments.*.amount' => 'nullable|numeric|min:0|max:9999999',
                    'payments.*.reference_no' => 'nullable|string|max:60',
                ]);

                $till->settle($data['payments'], $userId);
                $done = 'Payment taken.';
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $fresh = $posOrder->refresh();

        $stay = $fresh->check_in_id
            ? CheckIn::query()->where('branch_id', $branchId)->find($fresh->check_in_id)
            : null;

        if ($stay) {
            $items = PosOrderItem::query()
                ->where('pos_order_id', $fresh->id)
                ->with('menuItem')
                ->get();

            GuestMessage::send('guest.pos-bill', $stay->mobile, [
                'guest' => $stay->guest_name,
                'guest_email' => $stay->reservation?->email,
                'guest_phone' => $stay->mobile,
                'room' => $stay->room?->room_no,
                'outlet' => $fresh->outlet?->name,
                'bill_no' => $fresh->invoice()->value('invoice_no') ?: $fresh->order_no,
                'items' => $items->isNotEmpty()
                    ? $items->map(fn (PosOrderItem $i) => rtrim(rtrim(number_format((float) $i->qty, 2), '0'), '.')
                        . '× ' . ($i->menuItem?->name ?? 'Item'))->implode(', ')
                    : null,
                'amount' => '₹ ' . number_format((float) $fresh->net_amount, 2),
            ], $branchId);
        }

        Notify::event('pos.settled')
            ->title('Settled — ' . $fresh->order_no . ' · ₹ ' . number_format((float) $fresh->net_amount, 2))
            ->body(trim(($fresh->outlet?->name ?? '') . ' · ' . $done))
            ->url(route('point-of-sale.pos.invoices'))
            ->send();
        return $fresh->isSettled()
            ? redirect()->route('point-of-sale.pos', ['outlet' => $fresh->outlet_id])
                ->with('status', $done)
                ->with('print', route('point-of-sale.pos.order.print', $fresh->id))
            : redirect()->route('point-of-sale.pos.order', $fresh->id)->with('status', $done);
    }

    public function shift(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $target = PosTable::query()->forBranch($branchId)->findOrFail($request->integer('pos_table_id'));

        try {
            PosTill::for($posOrder)->shiftTo($target, $request->user()?->user_id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Moved to {$target->name}.");
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $data = $request->validate(['reason' => 'required|string|max:200']);

        try {
            PosTill::for($posOrder)->cancel($data['reason'], $request->user()?->user_id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Notify::event('pos.cancelled')
            ->title('Order cancelled — ' . $posOrder->order_no)
            ->body(trim(($posOrder->outlet?->name ?? '') . ' · ' . $data['reason']))
            ->url(route('point-of-sale.pos.outlet-orders'))
            ->send();

        return redirect()
            ->route('point-of-sale.pos', ['outlet' => $posOrder->outlet_id])
            ->with('status', "Order {$posOrder->order_no} cancelled.");
    }

    public function printBill(int $order): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        return view('pos.till.print-bill', [
            'order' => $posOrder,
            'lines' => $posOrder->items()->with('modifiers')->get(),
            'invoice' => $posOrder->invoice()->first(),
            'outlet' => $posOrder->outlet,
            'payments' => $posOrder->invoice()->first()?->payments()->with('payMode')->get() ?? collect(),
        ]);
    }

    public function printKot(int $order, int $number): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $posOrder = $this->find($order, $branchId);

        $lines = PosTill::for($posOrder)->kot($number);

        abort_if($lines->isEmpty(), 404);

        return view('pos.till.print-kot', [
            'order' => $posOrder,
            'lines' => $lines,
            'number' => $number,
            'outlet' => $posOrder->outlet,
        ]);
    }

    public function tableCard(int $table): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $seat = PosTable::query()->forBranch($branchId)->with(['group', 'outlet'])->findOrFail($table);
        $code = $this->tableCode($seat);

        $url = route('guest-order.menu', ['table' => $seat->id, 'code' => $code]);

        return view('pos.till.table-card', [
            'table' => $seat,
            'url' => $url,
            'code' => $code,
            'qr' => Qr::svg($url, 260),
        ]);
    }

    public function scan(Request $request, int $table): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $seat = PosTable::query()->forBranch($branchId)->findOrFail($table);

        if (! hash_equals($this->tableCode($seat), $request->string('code')->toString())) {
            abort(404);
        }

        return $this->openTable($request, $table);
    }

    private function tableCode(PosTable $table): string
    {
        return $table->code();
    }
    private function ours(string $table, int $branchId): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where(
            fn ($q) => $q->whereNull('deleted_at')
                ->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId))
        );
    }

    private function find(int $id, int $branchId): PosOrder
    {
        return PosOrder::query()
            ->where('branch_id', $branchId)
            ->with(['outlet', 'table', 'steward'])
            ->findOrFail($id);
    }

    private function line(PosOrder $order, int $id): PosOrderItem
    {
        return $order->items()->whereKey($id)->firstOrFail();
    }

    /**
     * @param  string|callable(): string  $done
     */
    private function act(callable $work, Request $request, PosOrder $order, $done): RedirectResponse
    {
        try {
            $work();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', is_callable($done) ? $done() : $done);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Outlet>
     */
    public static function outlets(int $branchId)
    {
        $user = auth()->user();

        return Outlet::query()
            ->forBranch($branchId)
            ->sellable()
            ->with('users')
            ->orderBy('name')
            ->get()
            ->filter(fn (Outlet $outlet) => $outlet->users->isEmpty()
                || is_admin()
                || $outlet->users->contains('user_id', $user?->user_id))
            ->values();
    }

    private function pickOutlet(Request $request, $outlets): ?Outlet
    {
        $wanted = $this->viewParts($request)[0] ?: $request->integer('outlet');

        return $outlets->firstWhere('id', $wanted) ?: $outlets->first();
    }

    private function mode(Request $request): string
    {
        return $this->viewParts($request)[1] ?: $request->string('mode')->toString();
    }

    /** @return array{0: int, 1: string} */
    private function viewParts(Request $request): array
    {
        $view = $request->string('view')->toString();

        if (! str_contains($view, '|')) {
            return [0, ''];
        }

        [$id, $mode] = explode('|', $view, 2);

        return [(int) $id, $mode === 'room' ? 'room' : 'restaurant'];
    }

    private function outletOrFail(Request $request, int $branchId): Outlet
    {
        $outlet = $this->pickOutlet($request, $this->outlets($branchId));

        abort_if(! $outlet, 404, 'No outlet is available to bill through.');

        return $outlet;
    }

    private function counterTypes(Outlet $outlet): array
    {
        return array_values(array_intersect($outlet->orderTypes(), ['take_away', 'delivery']));
    }

    private function menuTree(int $branchId, ?int $outletId)
    {
        return PosMenuCategory::query()
            ->forBranch($branchId)
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId))
            ->withCount(['items' => fn ($q) => $q->where('status', 1)])
            ->orderBy('name')
            ->get()
            ->groupBy(fn (PosMenuCategory $row) => $row->type === 'category' ? 'headings' : 'subs');
    }

    private function menuItems(int $branchId, PosOrder $order, ?int $category, string $term)
    {
        return PosMenuItem::query()
            ->forBranch($branchId)
            ->sellable()
            ->with([
                'ratePlans', 'category',
                'modifierGroups' => fn ($q) => $q->active(),
                'modifierGroups.modifiers' => fn ($q) => $q->active(),
            ])
            ->when($category, function ($q) use ($category) {
                $q->where(function ($inner) use ($category) {
                    $inner->where('pos_menu_category_id', $category)
                        ->orWhereIn(
                            'pos_menu_category_id',
                            PosMenuCategory::query()->where('parent_id', $category)->select('id')
                        );
                });
            })
            ->when($term, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('sort')
            ->orderBy('name')
            ->limit(400)
            ->get()
            ->map(function (PosMenuItem $item) use ($order) {
                $item->setAttribute('sell_price', $item->priceOn($order->pos_rate_plan_id));
                $item->setAttribute(
                    'has_modifiers',
                    $item->modifierGroups->contains(fn ($group) => $group->modifiers->isNotEmpty())
                );

                return $item;
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function ratePlansFor(PosOrder $order)
    {
        if (! $order->outlet) {
            return collect();
        }

        return $order->outlet->ratePlans()
            ->where('pos_rate_plans.status', 1)
            ->whereNull('pos_rate_plans.deleted_at')
            ->orderBy('pos_rate_plans.name')
            ->get(['pos_rate_plans.id', 'pos_rate_plans.name'])
            ->mapWithKeys(fn ($plan) => [$plan->id => $plan->name]);
    }

    private function shiftTargets(int $branchId, PosOrder $order)
    {
        $busy = PosOrder::query()
            ->where('branch_id', $branchId)
            ->onFloor()
            ->whereNotNull('pos_table_id')
            ->pluck('pos_table_id');

        return PosTable::query()
            ->forBranch($branchId)
            ->where('outlet_id', $order->outlet_id)
            ->where('status', 1)
            ->whereNotIn('id', $busy)
            ->with('group')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{label: string, route: string, icon: string, key: string}>
     */
    public static function nav(?Outlet $outlet): array
    {
        $with = $outlet ? ['outlet' => $outlet->id] : [];

        return [
            ['label' => 'Dine In', 'icon' => 'grid', 'key' => 'floor',
                'url' => route('point-of-sale.pos', $with)],
            ['label' => 'Live Orders', 'icon' => 'activity', 'key' => 'live',
                'url' => route('point-of-sale.pos.live-orders', $with)],
            ['label' => 'Unsettled Invoices', 'icon' => 'alert', 'key' => 'unsettled',
                'url' => route('point-of-sale.pos.unsettled', $with)],
            ['label' => 'Cash Balance', 'icon' => 'wallet', 'key' => 'cash',
                'url' => route('point-of-sale.pos.cash-balance', $with)],
            ['label' => 'Invoices', 'icon' => 'file', 'key' => 'invoices',
                'url' => route('point-of-sale.pos.invoices', $with)],
            ['label' => 'Outlet Orders', 'icon' => 'inbox', 'key' => 'outlet-orders',
                'url' => route('point-of-sale.pos.outlet-orders', $with)],
            ['label' => 'Collections', 'icon' => 'credit-card', 'key' => 'collections',
                'url' => route('point-of-sale.pos.collections', $with)],
        ];
    }
}
