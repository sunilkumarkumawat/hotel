<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\Bill;
use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\FolioCharge;
use App\Models\FrontOffice\PaxCheckout;
use App\Models\FrontOffice\Settlement;
use App\Models\Master\BillingInstruction;
use App\Models\Master\PayMode;
use App\Models\Master\Room;
use App\Models\Master\Service;
use App\Support\Folio;
use App\Support\FolioRefused;
use App\Support\GuestCrm;
use App\Support\GuestDocument;
use App\Support\GuestMessage;
use App\Support\Money;
use App\Support\Notify;
use App\Support\Tax;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckOutController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $inHouse = CheckIn::query()
            ->where('branch_id', $branchId)
            ->inHouse()
            ->with('room')
            ->orderBy('folio_no')
            ->get();

        $checkIn = $request->integer('check_in')
            ? $this->checkIn($request->integer('check_in'))
            : $inHouse->first();

        if ($inHouse->isEmpty() && ! $checkIn) {
            return redirect()->route('front-office.check-in-details')
                ->with('info', 'Nobody is checked in right now, so there is no bill to settle. Check a booking in first.');
        }

        if (! $checkIn->isInHouse()) {
            $bill = $checkIn->bill();

            return $bill
                ? redirect()->route('front-office.check-out-guest.invoice', $bill)
                    ->with('status', $checkIn->guest_name . ' has already checked out — here is the bill.')
                : redirect()->route('front-office.check-in-details')
                    ->with('error', $checkIn->guest_name . ' has already checked out.');
        }

        $folio = Folio::for($checkIn);

        try {
            $folio->post($request->user()->user_id);
        } catch (FolioRefused $e) {
            return redirect()->route('front-office.check-in-details')
                ->with('error', $e->getMessage());
        }

        $discount = $folio->discountOf(
            $request->string('discount_mode')->toString() ?: 'amount',
            (float) $request->input('discount_value', 0)
        );

        return view('front-office.check-out', [
            'inHouse' => $inHouse,
            'checkIn' => $checkIn->fresh([
                'room', 'plan', 'reservation.company', 'reservation.billingInstruction', 'reservation.rooms',
            ]),
            'departments' => $folio->departments(),
            'nightly' => $folio->nightlyFigures(),
            'totals' => $folio->totals($discount),
            'discount' => [
                'mode' => $request->string('discount_mode')->toString() ?: 'amount',
                'value' => (float) $request->input('discount_value', 0),
            ],
            'settlements' => $checkIn->settlements()->with('payMode')->orderBy('id')->get(),
            'payModes' => PayMode::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'payTypes' => Settlement::PAY_TYPES,
            'cardTypes' => Settlement::CARD_TYPES,
            'needsCard' => Settlement::CARD_TYPES_NEED_CARD,
            'services' => Service::query()->forBranch()->active()->with('tax')->orderBy('name')->get(),
            'taxChoices' => Tax::options(Helper::getActiveBranchId()),
            'defaultTaxChoice' => Tax::defaultChoice(),
            'billingInstructions' => BillingInstruction::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'chargeTypes' => ['service' => 'Services', 'misc' => 'Miscellaneous'],
        ]);
    }
    public function addCharge(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn);

        $data = $request->validate([
            'charge_date' => 'required|date',
            'charge_type' => ['required', Rule::in(['service', 'misc'])],
            'service_id' => 'nullable|integer',
            'particulars' => 'required|string|max:255',
            'qty' => 'required|numeric|min:0.01|max:9999',
            'price' => 'required|numeric|min:0|max:9999999',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'tax_choice' => Tax::rule($checkIn->branch_id),
            'tax_type' => 'required|in:exclusive,inclusive',
            'remark' => 'nullable|string|max:255',
        ]);

        $choice = Tax::normalise($data['tax_choice'] ?? Tax::defaultChoice());

        $figures = Money::serviceRow([
            'qty' => (float) $data['qty'],
            'price' => (float) $data['price'],
            'tax_percent' => (float) ($data['tax_percent'] ?? 0),
            'tax_choice' => $choice,
            'tax_type' => $data['tax_type'],
        ], $checkIn->branch_id);

        FolioCharge::create([
            'branch_id' => $checkIn->branch_id,
            'check_in_id' => $checkIn->id,
            'charge_date' => $data['charge_date'],
            'charge_type' => $data['charge_type'],
            'service_id' => $data['service_id'] ?: null,
            'particulars' => $data['particulars'],
            'qty' => $data['qty'],
            'price' => $data['price'],
            'tax_choice' => $choice,
            'tax_percent' => $figures['tax_percent'],
            'tax_amount' => $figures['tax_amount'],
            'amount' => $figures['amount'],
            'total_amount' => $figures['total_amount'],
            'remark' => $data['remark'] ?? null,
            'created_by' => $request->user()->user_id,
        ]);

        Notify::event('folio.charge')
            ->title($data['particulars'] . ' — ₹ ' . number_format($figures['total_amount'], 2))
            ->body('Folio ' . $checkIn->folio_no . ' · ' . $checkIn->guest_name)
            ->url(route('front-office.check-out-guest', ['checkIn' => $checkIn->id]))
            ->send();

        return back()->with('status', sprintf(
            '%s added to %s — ₹%s.',
            $data['particulars'],
            $checkIn->folio_no,
            number_format($figures['total_amount'], 2)
        ));
    }

    public function removeCharge(FolioCharge $charge): RedirectResponse
    {
        abort_unless($charge->branch_id === Helper::getActiveBranchId(), 404);

        if ($charge->isSystem()) {
            return back()->with('error', 'A room night cannot be removed by hand — change the checkout date instead.');
        }

        if ($charge->is_settled) {
            return back()->with('error', 'This charge has already been settled and cannot be removed.');
        }

        $charge->delete();

        return back()->with('status', 'Charge removed from the folio.');
    }

    public function extend(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn);

        $data = $request->validate([
            'to_date' => 'required|date',
        ]);

        $from = $checkIn->checkin_date->toDateString();
        $to = CarbonImmutable::parse($data['to_date'])->toDateString();

        if ($to <= $from) {
            return back()->with('error', 'A stay has to end after the day it started.');
        }

        $current = $checkIn->expected_checkout_date->toDateString();

        if ($to < $current && $checkIn->charges()->ofType('room')->whereDate('charge_date', '>=', $to)->exists()) {
            return back()->with(
                'error',
                "Nights from {$to} are already on the folio. Remove the payment first, or extend rather than shorten."
            );
        }

        if ($checkIn->room_id && $to > $current) {
            $clash = $this->clashOn(
                $checkIn->room_id,
                $current,
                $to,
                $checkIn->id,
                $checkIn->reservation_room_id
            );

            if ($clash) {
                return back()->with('error', "Room {$checkIn->room?->room_no} is not free until {$to} — {$clash}.");
            }
        }

        try {
            DB::transaction(function () use ($checkIn, $to, $request) {
                $checkIn->update(['expected_checkout_date' => $to]);

                $row = $checkIn->reservationRoom;

                if ($row && (int) $row->no_of_rooms === 1 && $to > $row->checkout_date->toDateString()) {
                    $row->moveCheckoutTo($to);
                    $row->reservation?->refreshTotals();
                }

                Folio::for($checkIn->fresh())->postRoomCharges($request->user()->user_id);
            });
        } catch (FolioRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', sprintf(
            '%s now leaves on %s — %d night(s) on the folio.',
            $checkIn->guest_name,
            CarbonImmutable::parse($to)->format('d M Y'),
            $checkIn->fresh()->charges()->ofType('room')->count()
        ));
    }

    public function paxCheckout(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn);

        $data = $request->validate([
            'checkout_date' => 'required|date',
            'pax' => 'required|integer|min:1|max:50',
            'remark' => 'nullable|string|max:255',
        ]);

        $left = $checkIn->paxRemaining();

        if ($data['pax'] > $left) {
            return back()->with('error', "Only {$left} guest(s) are still in room {$checkIn->room?->room_no}.");
        }

        PaxCheckout::create([
            'branch_id' => $checkIn->branch_id,
            'check_in_id' => $checkIn->id,
            'checkout_date' => $data['checkout_date'],
            'pax' => $data['pax'],
            'remark' => $data['remark'] ?? null,
            'created_by' => $request->user()->user_id,
        ]);

        return back()->with('status', sprintf(
            '%d guest(s) checked out of %s — %d still in the room.',
            $data['pax'],
            $checkIn->room?->room_no ?? $checkIn->folio_no,
            $checkIn->fresh()->paxRemaining()
        ));
    }

    public function pay(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn, true);

        $data = $this->paymentRules($request);

        $bill = $checkIn->status === 'checked_out'
            ? Bill::where('check_in_id', $checkIn->id)->latest('id')->first()
            : null;

        Settlement::create($this->paymentAttributes($data) + [
            'branch_id' => $checkIn->branch_id,
            'check_in_id' => $checkIn->id,
            'bill_id' => $bill?->id,
            'created_by' => $request->user()->user_id,
        ]);

        if ($bill) {
            $bill->paid_amount = round((float) $bill->paid_amount + (float) $data['amount'], 2);
            $bill->balance_amount = round((float) $bill->balance_amount - (float) $data['amount'], 2);
            $bill->status = $bill->balance_amount > 0 ? 'partial' : 'settled';
            $bill->save();
        }

        $totals = Folio::for($checkIn->fresh())->totals();

        GuestMessage::send('guest.payment', $checkIn->mobile, [
            'guest' => $checkIn->guest_name,
            'guest_email' => $checkIn->reservation?->email,
            'guest_phone' => $checkIn->mobile,
            'room' => $checkIn->room?->room_no,
            'amount' => '₹ ' . number_format((float) $data['amount'], 2),
            'mode' => PayMode::find($data['pay_mode_id'] ?? null)?->name ?: 'Not specified',
            'folio_no' => $checkIn->folio_no,
            'balance' => $totals['due'] > 0 ? '₹ ' . number_format($totals['due'], 2) : null,
        ], $checkIn->branch_id);

        Notify::event('payment.received')
            ->title('₹ ' . number_format((float) $data['amount'], 2) . ' taken — ' . $checkIn->guest_name)
            ->body('Folio ' . $checkIn->folio_no . ' · due now ₹ ' . number_format($totals['due'], 2))
            ->url($bill
                ? route('front-office.check-out-guest.invoice', $bill)
                : route('front-office.check-out-guest'))
            ->send();

        return back()->with('status', sprintf(
            '₹%s taken. Due now ₹%s.',
            number_format((float) $data['amount'], 2),
            number_format($totals['due'], 2)
        ));
    }

    public function removePayment(Settlement $settlement): RedirectResponse
    {
        abort_unless($settlement->branch_id === Helper::getActiveBranchId(), 404);

        if ($settlement->bill_id) {
            return back()->with('error', 'This payment is on a settled bill — cancel the bill first.');
        }

        $amount = number_format((float) $settlement->amount, 2);
        $settlement->delete();

        return back()->with('status', "Payment of ₹{$amount} removed.");
    }

    public function checkout(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn);

        $data = $request->validate([
            'discount_mode' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'checkout_date' => 'required|date',
            'billing_instruction_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0|max:99999999',
            'pay_mode_id' => 'nullable|integer',
            'pay_type' => ['nullable', Rule::in(array_keys(Settlement::PAY_TYPES))],
            'card_type' => ['nullable', Rule::in(array_keys(Settlement::CARD_TYPES))],
            'card_name' => 'nullable|string|max:255',
            'card_last4' => 'nullable|digits:4',
            'pan_no' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'reference_no' => 'nullable|string|max:60',
        ], [
            'card_last4.digits' => 'Enter the last 4 digits of the card only.',
            'pan_no.regex' => 'A PAN looks like ABCDE1234F.',
        ]);

        $data['checkout_date'] = CarbonImmutable::parse($data['checkout_date'])->toDateString();

        if ($data['checkout_date'] < $checkIn->checkin_date->toDateString()) {
            return back()->withInput()->with('error', 'A guest cannot leave before the day they arrived.');
        }

        $folio = Folio::for($checkIn);
        $discount = $folio->discountOf($data['discount_mode'] ?? 'amount', (float) ($data['discount_value'] ?? 0));
        $totals = $folio->totals($discount);

        $paying = (float) ($data['amount'] ?? 0);

        if ($paying > 0 && ! ($data['pay_mode_id'] ?? null)) {
            return back()->withInput()->with('error', 'Pick a pay mode for the amount being received.');
        }

        $bill = DB::transaction(function () use ($checkIn, $data, $totals, $paying, $request, $discount) {
            $billNo = Bill::nextNumber($checkIn->branch_id);

            if ($paying > 0) {
                Settlement::create($this->paymentAttributes($data) + [
                    'branch_id' => $checkIn->branch_id,
                    'check_in_id' => $checkIn->id,
                    'settle_date' => $data['checkout_date'],
                    'created_by' => $request->user()->user_id,
                ]);
            }

            Folio::for($checkIn)->trimRoomCharges($data['checkout_date']);

            $final = Folio::for($checkIn->fresh())->totals($discount);

            $bill = Bill::create([
                'branch_id' => $checkIn->branch_id,
                'check_in_id' => $checkIn->id,
                'guest_id' => $checkIn->guest_id,
                'bill_no' => $billNo,
                'bill_date' => $data['checkout_date'],
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
                'created_by' => $request->user()->user_id,
            ]);

            $checkIn->settlements()->whereNull('bill_id')->update(['bill_id' => $bill->id]);
            $checkIn->charges()->update(['is_settled' => 1]);

            $checkIn->update([
                'status' => 'checked_out',
                'actual_checkout_date' => $data['checkout_date'],
                'actual_checkout_time' => now()->format('H:i'),
            ]);

            if ($checkIn->room_id) {
                Room::whereKey($checkIn->room_id)->update(['housekeeping_status' => 'dirty']);
            }

            $row = $checkIn->reservationRoom;

            if ($row && (int) $row->no_of_rooms === 1 && $data['checkout_date'] < $row->checkout_date->toDateString()) {
                $row->moveCheckoutTo($data['checkout_date']);
                $row->reservation?->refreshTotals();
            }

            $checkIn->reservation?->refreshCheckOutStatus();

            return $bill;
        });

        if ($checkIn->guest_id) {
            rescue(fn () => GuestCrm::awardStay(
                (int) $checkIn->guest_id,
                (int) $checkIn->id,
                (float) $checkIn->charges()->ofType('room')->sum('amount'),
                (int) $checkIn->branch_id
            ), null, false);

            rescue(fn () => GuestCrm::recount($checkIn->guest), null, false);
        }

        $feedback = rescue(
            fn () => GuestCrm::feedbackFor((int) $checkIn->branch_id, (int) $checkIn->id, $checkIn->guest_id),
            null,
            false
        );

        GuestMessage::send('guest.checkout', $checkIn->mobile, [
            'guest' => $checkIn->guest_name,
            'guest_email' => $checkIn->reservation?->email,
            'guest_phone' => $checkIn->mobile,
            'folio_no' => $checkIn->folio_no,
            'bill_no' => $bill->bill_no,
            'room' => $checkIn->room?->room_no,
            'room_type' => (string) ($checkIn->room?->type?->name ?? 'Room'),
            'meal_plan' => (string) ($checkIn->plan?->name ?? 'Room Only'),
            'checkin_date' => $checkIn->checkin_date->format('d M Y'),
            'departure' => optional($checkIn->actual_checkout_date)->format('d M Y'),
            'nights' => $checkIn->nights,
            'room_total' => (float) $bill->room_total > 0 ? '₹ ' . number_format((float) $bill->room_total, 2) : null,
            'service_total' => (float) $bill->service_total > 0 ? '₹ ' . number_format((float) $bill->service_total, 2) : null,
            'discount_total' => (float) $bill->discount_total > 0 ? '₹ ' . number_format((float) $bill->discount_total, 2) : null,
            'tax_total' => (float) $bill->tax_total > 0 ? '₹ ' . number_format((float) $bill->tax_total, 2) : null,
            'amount' => '₹ ' . number_format((float) $bill->net_amount, 2),
            'paid' => '₹ ' . number_format((float) $bill->paid_amount, 2),
            'balance' => (float) $bill->balance_amount > 0
                ? '₹ ' . number_format((float) $bill->balance_amount, 2)
                : null,
            'feedback_url' => $feedback ? GuestDocument::base() . '/feedback/' . $feedback->token : null,
        ], $checkIn->branch_id);

        Notify::event('checkout.done')
            ->title($checkIn->guest_name . ' checked out')
            ->body(sprintf(
                'Bill %s · ₹ %s%s',
                $bill->bill_no,
                number_format((float) $bill->net_amount, 2),
                (float) $bill->balance_amount > 0
                    ? ' · ₹ ' . number_format((float) $bill->balance_amount, 2) . ' still due'
                    : ' · settled'
            ))
            ->level((float) $bill->balance_amount > 0 ? 'warning' : 'success')
            ->url(route('front-office.check-out-guest.invoice', $bill))
            ->send();

        return redirect()
            ->route('front-office.check-out-guest.invoice', $bill)
            ->with('status', sprintf(
                '%s checked out. Bill %s — ₹%s%s',
                $checkIn->guest_name,
                $bill->bill_no,
                number_format((float) $bill->net_amount, 2),
                $bill->balance_amount > 0
                    ? ', ₹' . number_format((float) $bill->balance_amount, 2) . ' still due.'
                    : ', settled in full.'
            ));
    }

    public function proforma(Request $request, CheckIn $checkIn): View|RedirectResponse
    {
        $this->guard($checkIn, allowCheckedOut: true);

        $folio = Folio::for($checkIn);

        if ($checkIn->isInHouse()) {
            try {
                $folio->post($request->user()->user_id);
            } catch (FolioRefused $e) {
                return redirect()->route('front-office.check-out-guest', ['check_in' => $checkIn->id])
                    ->with('error', $e->getMessage());
            }
        }

        $discount = $folio->discountOf(
            $request->string('discount_mode')->toString() ?: 'amount',
            (float) $request->input('discount_value', 0)
        );

        return view('front-office.invoice', [
            'branch' => Helper::activeBranch(),
            'checkIn' => $checkIn->load(['room', 'plan', 'reservation.company', 'reservation.country']),
            'charges' => $folio->charges(),
            'totals' => $folio->totals($discount),
            'settlements' => $checkIn->settlements()->with('payMode')->get(),
            'bill' => null,
            'title' => 'Provisional Invoice',
            'back' => route('front-office.check-out-guest', ['check_in' => $checkIn->id]),
        ]);
    }

    public function invoice(Bill $bill): View
    {
        abort_unless($bill->branch_id === Helper::getActiveBranchId(), 404);

        $checkIn = $bill->checkIn;

        return view('front-office.invoice', [
            'branch' => Helper::activeBranch(),
            'checkIn' => $checkIn?->load(['room', 'plan', 'reservation.company', 'reservation.country']),
            'charges' => $checkIn ? Folio::for($checkIn)->charges() : collect(),
            'totals' => [
                'sub_total' => (float) $bill->room_total + (float) $bill->service_total - (float) $bill->tax_total,
                'tax' => (float) $bill->tax_total,
                'room_total' => (float) $bill->room_total,
                'service_total' => (float) $bill->service_total,
                'discount' => (float) $bill->discount_total,
                'advance' => (float) $bill->advance_amount,
                'paid' => (float) $bill->paid_amount,
                'net' => (float) $bill->net_amount,
                'due' => (float) $bill->balance_amount,
                'refund' => (float) $bill->refund_amount,
                'nights' => 0,
            ],
            'settlements' => $bill->settlements()->with('payMode')->get(),
            'bill' => $bill,
            'title' => 'Client Payment Statement',
            'back' => route('front-office.check-in-details'),
        ]);
    }


    private function checkIn(int $id): CheckIn
    {
        $checkIn = CheckIn::query()
            ->where('branch_id', Helper::getActiveBranchId())
            ->with(['room', 'plan', 'reservation'])
            ->find($id);

        abort_unless($checkIn, 404, 'That stay is not in this branch.');

        return $checkIn;
    }

    private function guard(CheckIn $checkIn, bool $allowCheckedOut = false): void
    {
        abort_unless($checkIn->branch_id === Helper::getActiveBranchId(), 404);

        abort_unless(
            $allowCheckedOut || $checkIn->isInHouse(),
            403,
            $checkIn->guest_name . ' has already checked out.'
        );
    }

    /** @return array<string, mixed> */
    private function paymentRules(Request $request): array
    {
        return $request->validate([
            'settle_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'pay_mode_id' => ['required', 'integer', Rule::exists('pay_mode', 'id')],
            'pay_type' => ['required', Rule::in(array_keys(Settlement::PAY_TYPES))],
            'card_type' => ['nullable', Rule::in(array_keys(Settlement::CARD_TYPES))],
            'card_name' => 'nullable|string|max:255',
            'card_last4' => 'nullable|digits:4',
            'pan_no' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'reference_no' => 'nullable|string|max:60',
            'remark' => 'nullable|string|max:255',
        ], [
            'card_last4.digits' => 'Enter the last 4 digits of the card only.',
            'pan_no.regex' => 'A PAN looks like ABCDE1234F.',
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentAttributes(array $data): array
    {
        $isCard = in_array($data['pay_type'] ?? null, Settlement::CARD_TYPES_NEED_CARD, true);

        return [
            'settle_date' => $data['settle_date'] ?? ($data['checkout_date'] ?? now()->toDateString()),
            'amount' => $data['amount'],
            'pay_mode_id' => $data['pay_mode_id'] ?? null,
            'pay_type' => $data['pay_type'] ?? null,
            'card_type' => $isCard ? ($data['card_type'] ?? null) : null,
            'card_name' => $isCard ? ($data['card_name'] ?? null) : null,
            'card_last4' => $isCard ? ($data['card_last4'] ?? null) : null,
            'pan_no' => ($data['pan_no'] ?? null) ? strtoupper($data['pan_no']) : null,
            'reference_no' => $data['reference_no'] ?? null,
            'remark' => $data['remark'] ?? null,
        ];
    }

    private function clashOn(int $roomId, string $from, string $to, int $exceptCheckIn, ?int $exceptRow = null): ?string
    {
        $booking = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('rr.room_id', $roomId)
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->when($exceptRow, fn ($q, $id) => $q->where('rr.id', '!=', $id))
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->value('r.reservation_no');

        if ($booking) {
            return "{$booking} is booked into it";
        }

        $guest = DB::table('check_ins')
            ->where('room_id', $roomId)
            ->where('id', '!=', $exceptCheckIn)
            ->where('status', 'in_house')
            ->where('checkin_date', '<', $to)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$from])
            ->value('guest_name');

        if ($guest) {
            return "{$guest} is in it";
        }

        return DB::table('room_blocks')
            ->where('room_id', $roomId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $to)
            ->where('to_date', '>', $from)
            ->exists() ? 'it is blocked' : null;
    }
}
