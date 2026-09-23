<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\Bill;
use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\Settlement;
use App\Models\Master\BillingInstruction;
use App\Models\Master\PayMode;
use App\Models\Master\Room;
use App\Support\Folio;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One family, several rooms, one bill.
 *
 * Checking three rooms in together already makes three stays sharing one
 * `folio_no` — that is the family. This screen is what that folio looks like
 * from the cashier's side: every room the family is in, what each owes, and one
 * grand total.
 *
 * **Every room still gets its own numbered bill.** Room revenue is reported per
 * room and a tax invoice is per room, so collapsing five into one would lose
 * something the hotel needs. What this adds is a `group_no` shared by the five,
 * which is what lets them be printed as a single document with one total — and
 * any one of them still be handed out on its own.
 *
 * So the family gets one bill, the accountant gets five, and neither is a
 * reconstruction of the other.
 */
class GroupBillController extends Controller
{
    /**
     * GET front-office/check-out-guest/folio/{folio}
     *
     * The family's rooms, side by side.
     */
    public function show(Request $request, string $folio): View|RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $stays = $this->stays($branchId, $folio);

        if ($stays->isEmpty()) {
            return redirect()
                ->route('front-office.check-out-guest')
                ->with('info', "Nobody is in house on folio {$folio}.");
        }

        // Every room's own figures, and the family's total under them. Room
        // nights are posted first, exactly as the single-room screen does, or
        // the guest is handed a blank bill.
        $rows = $stays->map(function (CheckIn $stay) use ($request) {
            $folio = Folio::for($stay);

            if ($stay->isInHouse()) {
                $folio->post($request->user()?->user_id);
            }

            return (object) [
                'stay' => $stay,
                'totals' => $folio->totals(),
                'charges' => $folio->charges(),
            ];
        });

        return view('front-office.group-bill', [
            'folioNo' => $folio,
            'rows' => $rows,
            'grand' => $this->grand($rows),
            'payModes' => PayMode::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'instructions' => BillingInstruction::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * POST front-office/check-out-guest/folio/{folio}/checkout
     *
     * Check the whole family out at once.
     *
     * One bill per room, all carrying the same group number, and one payment
     * spread across them in the order they are listed. Spreading rather than
     * recording the lump against the first room is the point: each room's bill
     * has to show what was actually paid against it, or the room's own revenue
     * figure is wrong for every room in the group.
     */
    public function checkout(Request $request, string $folio): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $stays = $this->stays($branchId, $folio);

        if ($stays->isEmpty()) {
            return back()->with('error', "Nobody is in house on folio {$folio}.");
        }

        $data = $request->validate([
            'checkout_date' => 'required|date',
            'discount_mode' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'billing_instruction_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0|max:99999999',
            'pay_mode_id' => 'nullable|integer',
            'pay_type' => ['nullable', Rule::in(array_keys(Settlement::PAY_TYPES))],
            'reference_no' => 'nullable|string|max:60',
        ]);

        $date = CarbonImmutable::parse($data['checkout_date'])->toDateString();
        $paying = round((float) ($data['amount'] ?? 0), 2);

        if ($paying > 0 && ! ($data['pay_mode_id'] ?? null)) {
            return back()->withInput()->with('error', 'Pick a pay mode for the amount being received.');
        }

        foreach ($stays as $stay) {
            if ($date < $stay->checkin_date->toDateString()) {
                return back()->withInput()->with(
                    'error',
                    "Room {$stay->room?->room_no} arrived on {$stay->checkin_date->format('d M')} — nobody can leave before they arrived."
                );
            }
        }

        $groupNo = DB::transaction(function () use ($stays, $branchId, $data, $date, $paying, $request) {
            $groupNo = Bill::nextGroupNumber($branchId);
            $userId = $request->user()?->user_id;
            $left = $paying;

            // Give back every early-departing room's remaining nights before
            // any discount is worked out. discountFor() sums every stay's
            // current totals to split the family's discount across the
            // rooms; trimming one stay only when the loop reached it would
            // mix already-trimmed and not-yet-trimmed stays into that sum,
            // depending on nothing more meaningful than list order.
            foreach ($stays as $stay) {
                Folio::for($stay)->trimRoomCharges($date);
            }

            foreach ($stays as $stay) {
                $folio = Folio::for($stay);

                // The discount is entered once for the family and applied to
                // each room in proportion to what that room owes, so the parts
                // still add back up to the family's total.
                $share = $this->discountFor(
                    $folio,
                    $data['discount_mode'] ?? 'amount',
                    (float) ($data['discount_value'] ?? 0),
                    $stays
                );

                $totals = $folio->totals($share);

                // Money is spread room by room in the order they are listed:
                // each room takes what it owes until the payment runs out.
                $take = min($left, max(0, (float) $totals['due']));

                if ($take > 0) {
                    Settlement::create([
                        'branch_id' => $branchId,
                        'check_in_id' => $stay->id,
                        'settle_date' => $date,
                        'pay_mode_id' => $data['pay_mode_id'],
                        'pay_type' => $data['pay_type'] ?? 'cash',
                        'amount' => round($take, 2),
                        'reference_no' => $data['reference_no'] ?? null,
                        'remark' => "Part of group bill {$groupNo}",
                        'created_by' => $userId,
                    ]);

                    $left = round($left - $take, 2);
                }

                // Re-read after the payment, so the bill records what was
                // actually taken rather than what was owed a moment ago.
                $final = Folio::for($stay->fresh())->totals($share);

                $bill = Bill::create([
                    'branch_id' => $branchId,
                    'check_in_id' => $stay->id,
                    'guest_id' => $stay->guest_id,
                    'bill_no' => Bill::nextNumber($branchId),
                    'group_no' => $groupNo,
                    'bill_date' => $date,
                    'room_total' => $final['room_total'],
                    'service_total' => $final['service_total'],
                    'discount_total' => $final['discount'],
                    'advance_amount' => $final['advance'],
                    'tax_total' => $final['tax'],
                    'net_amount' => $final['net'],
                    'paid_amount' => $final['paid'],
                    'refund_amount' => $final['refund'],
                    'balance_amount' => $final['due'],
                    'status' => $final['due'] > 0 ? 'partial' : 'settled',
                    'billing_instruction_id' => $data['billing_instruction_id'] ?? null,
                    'remark' => $data['remark'] ?? null,
                    'created_by' => $userId,
                ]);

                $stay->settlements()->whereNull('bill_id')->update(['bill_id' => $bill->id]);
                $stay->charges()->update(['is_settled' => 1]);

                $stay->update([
                    'status' => 'checked_out',
                    'actual_checkout_date' => $date,
                    'actual_checkout_time' => now()->format('H:i'),
                ]);

                // The room is empty and needs making up before it is sold again.
                if ($stay->room_id) {
                    Room::whereKey($stay->room_id)->update(['housekeeping_status' => 'dirty']);
                }

                // A family leaving early gives its remaining nights back —
                // trimmed from the folio above; this only shortens what the
                // booking is still holding on the room.
                $row = $stay->reservationRoom;

                if ($row && (int) $row->no_of_rooms === 1 && $date < $row->checkout_date->toDateString()) {
                    $row->moveCheckoutTo($date);
                    $row->reservation?->refreshTotals();
                }

                $stay->reservation?->refreshCheckOutStatus();
            }

            return $groupNo;
        });

        return redirect()
            ->route('front-office.check-out-guest.group-invoice', $groupNo)
            ->with('status', sprintf(
                '%d room%s checked out on group bill %s.',
                $stays->count(),
                $stays->count() === 1 ? '' : 's',
                $groupNo
            ));
    }

    /**
     * GET front-office/check-out-guest/group/{group}
     *
     * One sheet, every room.
     */
    public function invoice(string $group): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $bills = Bill::query()
            ->where('branch_id', $branchId)
            ->where('group_no', $group)
            ->where('status', '!=', 'cancelled')
            ->with(['checkIn.room', 'guest', 'billingInstruction', 'settlements.payMode'])
            ->orderBy('id')
            ->get();

        abort_if($bills->isEmpty(), 404);

        return view('front-office.group-invoice', [
            'groupNo' => $group,
            'bills' => $bills,
            'branch' => Helper::activeBranch(),
            // The charges behind each room, so the sheet can show the nights
            // and the services rather than four numbers per room.
            'lines' => $bills->mapWithKeys(fn (Bill $bill) => [
                $bill->id => $bill->checkIn
                    ? Folio::for($bill->checkIn)->charges()
                    : collect(),
            ]),
            'grand' => [
                'room' => round($bills->sum(fn ($b) => (float) $b->room_total), 2),
                'service' => round($bills->sum(fn ($b) => (float) $b->service_total), 2),
                'discount' => round($bills->sum(fn ($b) => (float) $b->discount_total), 2),
                'advance' => round($bills->sum(fn ($b) => (float) $b->advance_amount), 2),
                'tax' => round($bills->sum(fn ($b) => (float) $b->tax_total), 2),
                'net' => round($bills->sum(fn ($b) => (float) $b->net_amount), 2),
                'paid' => round($bills->sum(fn ($b) => (float) $b->paid_amount), 2),
                'refund' => round($bills->sum(fn ($b) => (float) $b->refund_amount), 2),
                'due' => round($bills->sum(fn ($b) => (float) $b->balance_amount), 2),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Everyone still in house on this folio, in room order. */
    private function stays(int $branchId, string $folio): Collection
    {
        return CheckIn::query()
            ->where('branch_id', $branchId)
            ->where('folio_no', $folio)
            ->inHouse()
            ->with(['room', 'reservationRoom'])
            ->get()
            ->sortBy(fn (CheckIn $stay) => $stay->room?->room_no ?? '')
            ->values();
    }

    /**
     * One room's share of a discount entered for the whole family.
     *
     * A percentage is the same percentage on every room, so it needs no
     * spreading. A flat amount does: ₹2,000 off a family bill is not ₹2,000 off
     * each of five rooms, so each room takes it in proportion to what it owes.
     */
    private function discountFor(Folio $folio, string $mode, float $value, Collection $stays): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($mode === 'percent') {
            return $folio->discountOf('percent', $value);
        }

        $totals = $folio->totals();
        $mine = (float) $totals['sub_total'] + (float) $totals['tax'];

        $all = round($stays->sum(function (CheckIn $stay) {
            $row = Folio::for($stay)->totals();

            return (float) $row['sub_total'] + (float) $row['tax'];
        }), 2);

        return $all > 0 ? round($value * $mine / $all, 2) : 0.0;
    }

    /**
     * The family's total.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, float>
     */
    private function grand(Collection $rows): array
    {
        $sum = fn (string $key) => round($rows->sum(fn ($row) => (float) $row->totals[$key]), 2);

        return [
            'room_total' => $sum('room_total'),
            'service_total' => $sum('service_total'),
            'tax' => $sum('tax'),
            'advance' => $sum('advance'),
            'paid' => $sum('paid'),
            'net' => $sum('net'),
            'due' => $sum('due'),
            'refund' => $sum('refund'),
            'nights' => (int) $rows->sum(fn ($row) => (int) $row->totals['nights']),
        ];
    }
}
