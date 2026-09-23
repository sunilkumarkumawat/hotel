<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosGuestRequest;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosTable;
use App\Support\GuestMessage;
use App\Support\Notify;
use App\Support\PosTill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What a guest asked for from their own phone, waiting for somebody at the
 * desk to say yes or no.
 *
 * approve() is the only place in the app that turns a guest's own request
 * into real order lines and a KOT — and it does that through PosTill, the
 * same class every other screen that changes a bill goes through, so a
 * guest-placed samosa is billed, taxed and printed exactly like a
 * waiter-placed one. reject() touches nothing but this request.
 */
class GuestRequestController extends Controller
{
    /** GET point-of-sale/pos/guest-requests */
    public function index(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);

        $requests = PosGuestRequest::query()
            ->where('branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
            ->with('table')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('pos.till.guest-requests', [
            'outlet' => $outlet,
            'outlets' => $outlets,
            'nav' => PosController::nav($outlet),
            'pending' => $requests->where('status', 'pending')->values(),
            'decided' => $requests->where('status', '!=', 'pending')->take(20)->values(),
        ]);
    }

    /**
     * POST point-of-sale/pos/guest-requests/{guestRequest}/approve
     *
     * "Approve" means: put these items on this table's order and send them to
     * the kitchen in the same breath — a captain reading the request and
     * telling the kitchen, just typed on a phone instead of a pad.
     */
    public function approve(Request $request, int $guestRequest): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $guestRequest = PosGuestRequest::query()
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->findOrFail($guestRequest);

        $seat = PosTable::query()->forBranch($branchId)->findOrFail($guestRequest->pos_table_id);
        $userId = $request->user()?->user_id;
        $till = PosTill::atTable($seat, $branchId, $userId);

        // Re-read from the live menu, same as the guest's own store() did —
        // never trust a snapshot taken whenever the guest happened to order.
        $menu = PosMenuItem::query()
            ->forBranch($branchId)
            ->sellable()
            ->with('ratePlans')
            ->whereIn('id', collect($guestRequest->items)->pluck('id'))
            ->get()
            ->keyBy('id');

        $skipped = [];

        foreach ($guestRequest->items as $line) {
            $item = $menu->get((int) $line['id']);

            if (! $item) {
                $skipped[] = $line['name'];

                continue;
            }

            // No per-item remark — a guest's note (if they left one) is
            // table-level, shown alongside the request on this screen rather
            // than attached to one line.
            $till->addItem($item, (float) $line['qty'], null);
        }

        if (count($skipped) === count($guestRequest->items)) {
            return back()->with(
                'error',
                'None of these items are on the menu any more — Decline this request instead.'
            );
        }

        $kotNumber = $till->fireKot($userId);
        $order = $till->order();

        $guestRequest->update([
            'status' => 'approved',
            'pos_order_id' => $order->id,
            'kot_no' => $kotNumber,
            'decided_by' => $userId,
            'decided_at' => now(),
        ]);

        if ($kotNumber) {
            Notify::event('pos.kot')
                ->title('KOT ' . $kotNumber . ' — ' . $order->order_no)
                ->body(trim(($order->outlet?->name ?? '') . ' · ' . ($order->table_no ?: 'counter')))
                ->url(route('point-of-sale.kitchen-display'))
                ->send();

            // Only when the guest chose to leave a number or address on the
            // menu page — nobody is made to give one, so most requests skip
            // this silently, same as a walk-in the system has no number for.
            if ($guestRequest->guest_mobile || $guestRequest->guest_email) {
                GuestMessage::send('guest.pos-order', $guestRequest->guest_mobile, [
                    'guest' => null,
                    'guest_email' => $guestRequest->guest_email,
                    'where' => 'Table ' . $seat->name,
                    'items' => collect($guestRequest->items)
                        ->map(fn ($line) => rtrim(rtrim(number_format((float) $line['qty'], 2), '0'), '.')
                            . '× ' . $line['name'])
                        ->implode(', '),
                ], $branchId);
            }
        }

        $message = 'Sent to the kitchen.';

        if ($skipped) {
            $message .= ' (' . implode(', ', $skipped) . ' had come off the menu since and were left out.)';
        }

        return back()->with('status', $message);
    }

    /** POST point-of-sale/pos/guest-requests/{guestRequest}/reject */
    public function reject(Request $request, int $guestRequest): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $guestRequest = PosGuestRequest::query()
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->findOrFail($guestRequest);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $guestRequest->update([
            'status' => 'rejected',
            'decline_reason' => $data['reason'] ?? null,
            'decided_by' => $request->user()?->user_id,
            'decided_at' => now(),
        ]);

        return back()->with('status', 'Declined — the guest will see that on their phone.');
    }

    /** @return array{0: int, 1: ?Outlet, 2: \Illuminate\Support\Collection} */
    private function context(Request $request): array
    {
        $branchId = (int) Helper::getActiveBranchId();
        $outlets = PosController::outlets($branchId);
        $outlet = $outlets->firstWhere('id', $request->integer('outlet')) ?: $outlets->first();

        return [$branchId, $outlet, $outlets];
    }
}
