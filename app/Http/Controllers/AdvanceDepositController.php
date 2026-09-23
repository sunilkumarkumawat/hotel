<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\PayMode;
use App\Models\Reservation\AdvanceDeposit;
use App\Models\Reservation\Reservation;
use App\Support\GuestMessage;
use App\Support\Notify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Advance Deposit Details — money taken before or during a stay.
 *
 * The form on top records a receipt or a refund against a booking; the table
 * underneath is everything taken so far. Each save re-adds that booking's
 * deposits so `reservations.advance_paid` and the balance on the reservation
 * screen stay right.
 */
class AdvanceDepositController extends Controller
{
    /** Which list the Guest Name picker offers. */
    public const GUEST_SOURCES = [
        'advance' => 'Advance booking (not arrived)',
        'in_house' => 'In house (checked in)',
        'all' => 'All open bookings',
    ];

    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $filters = [
            'q' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $base = $this->filtered($branchId, $filters);

        $deposits = (clone $base)
            ->with(['reservation', 'payMode'])
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10) ?: 10)
            ->withQueryString();

        // Cloned from the same filtered query as the list above, so the totals
        // on the cards can never disagree with what the table is showing.
        $totals = (clone $base)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN 0 ELSE amount END), 0) AS received")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) AS refunded")
            ->first();

        return view('reservation.advance-deposit', [
            'deposits' => $deposits,
            'filters' => $filters,
            'perPage' => $deposits->perPage(),
            'editing' => $this->editing($request, $branchId),
            'types' => AdvanceDeposit::TYPES,
            'payTypes' => AdvanceDeposit::PAY_TYPES,
            'cardTypes' => AdvanceDeposit::CARD_TYPES,
            'needsCard' => AdvanceDeposit::CARD_TYPES_NEED_CARD,
            'sources' => self::GUEST_SOURCES,
            'payModes' => PayMode::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'received' => (float) ($totals->received ?? 0),
                'refunded' => (float) ($totals->refunded ?? 0),
                'net' => (float) ($totals->received ?? 0) - (float) ($totals->refunded ?? 0),
                'count' => $deposits->total(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $reservation = $this->reservation($data['reservation_id']);

        if ($message = $this->refundGuard($reservation, $data)) {
            return back()->withInput()->with('error', $message);
        }

        DB::transaction(function () use ($data, $reservation, $request) {
            AdvanceDeposit::create($this->attributes($data) + [
                'branch_id' => Helper::getActiveBranchId(),
                'reservation_id' => $reservation->id,
                'created_by' => $request->user()->user_id,
            ]);

            $reservation->refreshAdvancePaid();
        });

        if (($data['type'] ?? 'deposit') === 'deposit') {
            GuestMessage::send('guest.advance', $reservation->mobile, [
                'guest' => $reservation->guest_name,
                'guest_email' => $reservation->email,
                'reservation_no' => $reservation->reservation_no,
                'amount' => '₹ ' . number_format((float) $data['amount'], 2),
            ], $reservation->branch_id);

            Notify::event('reservation.deposit')
                ->title('Advance ₹ ' . number_format((float) $data['amount'], 2) . ' — ' . $reservation->reservation_no)
                ->body($reservation->guest_name)
                ->url(route('reservation.advance-deposit'))
                ->send();
        }

        return redirect()
            ->route('reservation.advance-deposit')
            ->with('status', $this->receipt($data, $reservation));
    }

    public function update(Request $request, AdvanceDeposit $deposit): RedirectResponse
    {
        $this->guardBranch($deposit);

        $data = $this->validated($request, $deposit);
        $reservation = $this->reservation($data['reservation_id']);

        if ($message = $this->refundGuard($reservation, $data, $deposit)) {
            return back()->withInput()->with('error', $message);
        }

        DB::transaction(function () use ($data, $deposit, $reservation) {
            $previous = $deposit->reservation;

            $deposit->update($this->attributes($data) + ['reservation_id' => $reservation->id]);

            // Moving a deposit to another booking has to fix both of them.
            if ($previous && $previous->id !== $reservation->id) {
                $previous->refreshAdvancePaid();
            }

            $reservation->refreshAdvancePaid();
        });

        return redirect()
            ->route('reservation.advance-deposit')
            ->with('status', 'Entry updated.');
    }

    public function destroy(AdvanceDeposit $deposit): RedirectResponse
    {
        $this->guardBranch($deposit);

        $reservation = $deposit->reservation;
        $amount = number_format((float) $deposit->amount, 2);

        DB::transaction(function () use ($deposit, $reservation) {
            $deposit->delete();
            $reservation?->refreshAdvancePaid();
        });

        return back()->with('status', "Entry of ₹{$amount} removed.");
    }

    /**
     * Bookings the Guest Name picker can offer, filtered by the Guest list.
     */
    public function guests(Request $request): JsonResponse
    {
        $source = $request->string('source')->toString();

        $statuses = match ($source) {
            'in_house' => ['checked_in'],
            'advance' => ['confirmed', 'tentative'],
            default => ['confirmed', 'tentative', 'checked_in'],
        };

        // A deposit being edited may sit on a booking that has since checked
        // out; keep it in the list so opening the form cannot quietly move
        // the money to whichever booking happens to be first.
        $keep = $request->integer('include');

        $bookings = Reservation::query()
            ->where('branch_id', Helper::getActiveBranchId())
            ->where(fn ($q) => $q->whereIn('status', $statuses)->when($keep, fn ($q) => $q->orWhere('id', $keep)))
            ->search($request->string('q')->toString())
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json($bookings->map(fn (Reservation $r) => [
            'id' => $r->id,
            'label' => $r->reservation_no . ' — ' . $r->guest_name . ($r->mobile ? ' · ' . $r->mobile : ''),
            'net' => (float) $r->net_amount,
            'paid' => (float) $r->advance_paid,
            'balance' => $r->balance,
            'status' => $r->status,
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The rows a branch's filters select.
     *
     * Shared by the list and the totals cards — cloned for each rather than
     * built twice, so a filter added here can never end up applied to one and
     * forgotten on the other.
     */
    private function filtered(int $branchId, array $filters): Builder
    {
        return AdvanceDeposit::query()
            ->where('branch_id', $branchId)
            ->search($filters['q'])
            ->when($filters['type'], fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('deposit_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('deposit_date', '<=', $d));
    }

    private function guardBranch(AdvanceDeposit $deposit): void
    {
        abort_unless($deposit->branch_id === Helper::getActiveBranchId(), 404);
    }

    private function reservation(int $id): Reservation
    {
        $reservation = Reservation::query()
            ->where('branch_id', Helper::getActiveBranchId())
            ->find($id);

        abort_unless($reservation, 404, 'That booking is not in this branch.');

        return $reservation;
    }

    /** The row being edited, when ?edit=<id> points at one of ours. */
    private function editing(Request $request, int $branchId): ?AdvanceDeposit
    {
        if (! $request->filled('edit')) {
            return null;
        }

        return AdvanceDeposit::query()
            ->where('branch_id', $branchId)
            ->with('reservation')
            ->find($request->integer('edit'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AdvanceDeposit $deposit = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AdvanceDeposit::TYPES))],
            'source' => ['nullable', Rule::in(array_keys(self::GUEST_SOURCES))],
            'reservation_id' => 'required|integer',
            'deposit_date' => 'required|date|before_or_equal:today',
            'pay_mode_id' => ['required', 'integer', Rule::exists('pay_mode', 'id')],
            'pay_type' => ['required', Rule::in(array_keys(AdvanceDeposit::PAY_TYPES))],
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'card_type' => ['nullable', Rule::in(array_keys(AdvanceDeposit::CARD_TYPES))],
            'card_name' => 'nullable|string|max:255',
            // Four digits only — see the migration for why.
            'card_last4' => 'nullable|digits:4',
            'pan_no' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'reference_no' => 'nullable|string|max:60',
            'remark' => 'nullable|string|max:255',
        ], [
            'deposit_date.before_or_equal' => 'Money cannot be received on a future date.',
            'card_last4.digits' => 'Enter the last 4 digits of the card only.',
            'pan_no.regex' => 'A PAN looks like ABCDE1234F.',
            'amount.min' => 'Enter an amount greater than zero.',
        ]);

        // Card details only make sense for a card payment.
        if (! in_array($data['pay_type'], AdvanceDeposit::CARD_TYPES_NEED_CARD, true)) {
            $data['card_type'] = null;
            $data['card_name'] = null;
            $data['card_last4'] = null;
        }

        $data['pan_no'] = $data['pan_no'] ? strtoupper($data['pan_no']) : null;

        return $data;
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'deposit_date' => $data['deposit_date'],
            'pay_mode_id' => $data['pay_mode_id'],
            'pay_type' => $data['pay_type'],
            'amount' => $data['amount'],
            'card_type' => $data['card_type'] ?? null,
            'card_name' => $data['card_name'] ?? null,
            'card_last4' => $data['card_last4'] ?? null,
            'pan_no' => $data['pan_no'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'remark' => $data['remark'] ?? null,
        ];
    }

    /**
     * You cannot hand back more than the guest has given you.
     *
     * On an edit, the row being changed is taken out of the running total
     * first, otherwise raising an existing refund would always look too big.
     */
    private function refundGuard(Reservation $reservation, array $data, ?AdvanceDeposit $ignore = null): ?string
    {
        if ($data['type'] !== 'refund') {
            return null;
        }

        $held = (float) $reservation->deposits()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END), 0) AS total")
            ->value('total');

        if ((float) $data['amount'] > $held) {
            return sprintf(
                'Only ₹%s is held against %s — you cannot refund ₹%s.',
                number_format($held, 2),
                $reservation->reservation_no,
                number_format((float) $data['amount'], 2)
            );
        }

        return null;
    }

    private function receipt(array $data, Reservation $reservation): string
    {
        return sprintf(
            '%s of ₹%s recorded against %s. Balance now ₹%s.',
            $data['type'] === 'refund' ? 'Refund' : 'Receipt',
            number_format((float) $data['amount'], 2),
            $reservation->reservation_no,
            number_format($reservation->fresh()->balance, 2)
        );
    }
}
