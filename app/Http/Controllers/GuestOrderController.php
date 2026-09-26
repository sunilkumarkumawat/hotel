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


class GuestOrderController extends Controller
{
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

    public function store(Request $request, int $table, string $code): RedirectResponse
    {
        $seat = $this->seat($table, $code);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'note' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:100'],
        ]);

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

    public function poll(int $table, string $code, string $token): JsonResponse
    {
        $seat = $this->seat($table, $code);
        $guestRequest = $this->request($seat, $token);

        return response()->json($this->state($guestRequest));
    }
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
