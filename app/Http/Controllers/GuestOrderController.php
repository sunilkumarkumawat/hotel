<?php

namespace App\Http\Controllers;

use App\Models\Branch\Branch;
use App\Models\Pos\PosGuestRequest;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosTable;
use App\Support\Notify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The menu a guest actually gets when they scan the QR on their table.
 *
 * Deliberately outside every middleware group — same as GuestFeedbackFormController
 * — there is no account, no session and no cookie, and none is needed: the
 * table id plus PosTable::code() is what stands in for a login, exactly like
 * the feedback link's forty characters. There is no `forBranch()` / active-
 * branch session here either, because there is no signed-in user to have one
 * — the table itself says which branch's menu to show, once its code checks
 * out.
 *
 * ── What this does NOT do ───────────────────────────────────────────────
 *
 * It never lets a guest's order reach the kitchen by itself. store() only
 * ever creates a PosGuestRequest — a request, sitting by itself, worth
 * nothing until a member of staff reads it. Turning that into real order
 * lines and a KOT is GuestRequestController::approve(), which runs under the
 * hotel's own login like everything else that touches a bill.
 */
class GuestOrderController extends Controller
{
    /** GET order/{table}/{code} */
    public function menu(Request $request, int $table, string $code): View
    {
        $seat = $this->seat($table, $code);

        $chosen = $request->integer('category') ?: null;
        $term = trim($request->string('q')->toString());

        return view('guest-order.menu', [
            'table' => $seat,
            'outlet' => $seat->outlet,
            'hotel' => Branch::find($seat->branch_id),
            'code' => $code,
            'tree' => $this->menuTree($seat->branch_id, $seat->outlet_id),
            'items' => $this->menuItems($seat->branch_id, $chosen, $term),
            'chosen' => $chosen,
            'term' => $term,
        ]);
    }

    /** POST order/{table}/{code} */
    public function store(Request $request, int $table, string $code): RedirectResponse
    {
        $seat = $this->seat($table, $code);

        // The form posts one row per item on the whole menu, keyed by item id
        // — most of them left at 0 — so a quantity is nullable here. Only
        // what somebody actually set above zero survives the filter below.
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'note' => ['nullable', 'string', 'max:255'],
            // Optional, and never asked for twice — one box, guest's choice of
            // a WhatsApp number or an email address. See contact() below.
            'contact' => ['nullable', 'string', 'max:100'],
        ]);

        // Prices and names are re-read from the live menu, never trusted from
        // the form — the same rule PosTill::addItem() follows for a waiter's
        // own order. An id that no longer sells (86'd since the page loaded)
        // is quietly dropped rather than failing the whole request.
        $menu = PosMenuItem::query()
            ->forBranch($seat->branch_id)
            ->sellable()
            ->with('ratePlans')
            ->whereIn('id', array_keys($data['items']))
            ->get()
            ->keyBy('id');

        $lines = collect($data['items'])
            ->map(function ($qty, $id) use ($menu) {
                $qty = round((float) $qty, 2);
                $item = $qty > 0 ? $menu->get((int) $id) : null;

                return $item ? [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->priceOn(null),
                    'qty' => $qty,
                ] : null;
            })
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Please choose at least one item before sending your order.');
        }

        [$guestMobile, $guestEmail] = $this->contact($data['contact'] ?? '');

        $guestRequest = PosGuestRequest::create([
            'branch_id' => $seat->branch_id,
            'pos_table_id' => $seat->id,
            'outlet_id' => $seat->outlet_id,
            'token' => PosGuestRequest::newToken(),
            'items' => $lines->all(),
            'guest_note' => trim((string) ($data['note'] ?? '')) ?: null,
            'guest_mobile' => $guestMobile,
            'guest_email' => $guestEmail,
            'status' => 'pending',
        ]);

        Notify::event('pos.guest_request')
            ->branch($seat->branch_id)
            ->title('Table ' . $seat->name . ' wants to order')
            ->body($lines->count() . ' item(s) — ' . $lines->pluck('name')->implode(', '))
            ->url(route('point-of-sale.pos.guest-requests'))
            ->send();

        return redirect()->route('guest-order.status', [
            'table' => $seat->id,
            'code' => $code,
            'token' => $guestRequest->token,
        ]);
    }

    /** GET order/{table}/{code}/status/{token} */
    public function status(int $table, string $code, string $token): View
    {
        $seat = $this->seat($table, $code);
        $guestRequest = $this->request($seat, $token);

        return view('guest-order.status', [
            'table' => $seat,
            'hotel' => Branch::find($seat->branch_id),
            'code' => $code,
            'request' => $guestRequest,
            'state' => $this->state($guestRequest),
        ]);
    }

    /** GET order/{table}/{code}/status/{token}/poll — the same page's own live-status feed. */
    public function poll(int $table, string $code, string $token): JsonResponse
    {
        $seat = $this->seat($table, $code);
        $guestRequest = $this->request($seat, $token);

        return response()->json($this->state($guestRequest));
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** The table this link is for, once its code checks out — or a 404. */
    private function seat(int $table, string $code): PosTable
    {
        $seat = PosTable::query()->with('outlet')->findOrFail($table);

        if (! hash_equals($seat->code(), $code)) {
            abort(404);
        }

        return $seat;
    }

    private function request(PosTable $seat, string $token): PosGuestRequest
    {
        return PosGuestRequest::query()
            ->where('pos_table_id', $seat->id)
            ->where('token', $token)
            ->firstOrFail();
    }

    /**
     * One box on the menu page, whichever way the guest chooses to be found —
     * a WhatsApp number or an email address, never both fields at once. Blank
     * is a normal, expected answer: most guests will leave it, and nothing
     * about ordering depends on it.
     *
     * @return array{0: ?string, 1: ?string} [mobile, email]
     */
    private function contact(string $raw): array
    {
        $value = trim($raw);

        if ($value === '') {
            return [null, null];
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? [null, $value] : [$value, null];
    }

    /**
     * What to tell the guest right now, in one line, plus how far each of
     * their items has gotten in the kitchen once staff have accepted them.
     *
     * kitchen_status is read only for the same kot_no this request produced,
     * and only for the menu items this request actually asked for — a KOT
     * round can carry other tables' — no, other lines a waiter added to the
     * same ticket at the same moment, and this guest only asked about their
     * own.
     *
     * @return array{status: string, label: string, items: array<int, array{name: string, qty: float, kitchen_status: ?string}>}
     */
    private function state(PosGuestRequest $guestRequest): array
    {
        $items = collect($guestRequest->items);

        if ($guestRequest->status === 'pending') {
            return [
                'status' => 'pending',
                'label' => 'Sent to the hotel — waiting for someone to confirm it.',
                'items' => $items->map(fn ($i) => ['name' => $i['name'], 'qty' => $i['qty'], 'kitchen_status' => null])->all(),
            ];
        }

        if ($guestRequest->status === 'rejected') {
            return [
                'status' => 'rejected',
                'label' => $guestRequest->decline_reason
                    ?: 'The hotel could not take this order just now — please ask a staff member.',
                'items' => $items->map(fn ($i) => ['name' => $i['name'], 'qty' => $i['qty'], 'kitchen_status' => null])->all(),
            ];
        }

        // Approved: read back how each of these items is actually doing.
        $rank = array_flip(array_keys(PosOrderItem::KITCHEN_STATUSES));

        $live = PosOrderItem::query()
            ->where('pos_order_id', $guestRequest->pos_order_id)
            ->where('kot_no', $guestRequest->kot_no)
            ->whereIn('pos_menu_item_id', $items->pluck('id'))
            ->get(['pos_menu_item_id', 'kitchen_status'])
            ->keyBy('pos_menu_item_id');

        $withStatus = $items->map(fn ($i) => [
            'name' => $i['name'],
            'qty' => $i['qty'],
            'kitchen_status' => $live->get($i['id'])?->kitchen_status,
        ]);

        $lowest = $withStatus->pluck('kitchen_status')->filter()->sortBy(fn ($s) => $rank[$s] ?? 99)->first();

        return [
            'status' => 'approved',
            'label' => match ($lowest) {
                'pending' => 'Confirmed — the kitchen has your order.',
                'preparing' => 'Being prepared.',
                'ready' => 'Ready — on its way to your table.',
                'served' => 'Served. Enjoy!',
                default => 'Confirmed — the kitchen has your order.',
            },
            'items' => $withStatus->all(),
        ];
    }

    /** Same shape as PosController's own menuTree(), for a table with no order yet. */
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

    /** Same shape as PosController's own menuItems(), priced at the everyday rate. */
    private function menuItems(int $branchId, ?int $category, string $term)
    {
        return PosMenuItem::query()
            ->forBranch($branchId)
            ->sellable()
            ->with('category')
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
            ->map(function (PosMenuItem $item) {
                $item->setAttribute('sell_price', $item->priceOn(null));

                return $item;
            });
    }
}
