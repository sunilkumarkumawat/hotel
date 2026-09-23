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
use App\Support\GuestCrm;
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

/**
 * Check Out Guest — the bill, and the guest leaving.
 *
 * Everything here works off `Folio`, so the screen, the proforma invoice and
 * the bill that is finally saved can never disagree about what is owed.
 *
 * Checkout itself is the only thing that writes a Bill. Up to that moment the
 * folio is live and can be changed; after it, the bill is a copy that does not
 * move even if a rate is edited later.
 */
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

        /*
         * An empty house is an ordinary morning, not an error — the sidebar
         * links straight here, so a 404 page would be the desk's first sight
         * of the screen. Send them where the guests are instead.
         */
        if ($inHouse->isEmpty() && ! $checkIn) {
            return redirect()->route('front-office.check-in-details')
                ->with('info', 'Nobody is checked in right now, so there is no bill to settle. Check a booking in first.');
        }

        /*
         * A stay that has already been settled must not come back to this
         * screen. Opening it would re-post its room nights and let the desk
         * take a second payment against a bill the guest has already paid, so
         * it goes to the bill instead.
         */
        if (! $checkIn->isInHouse()) {
            $bill = $checkIn->bill();

            return $bill
                ? redirect()->route('front-office.check-out-guest.invoice', $bill)
                    ->with('status', $checkIn->guest_name . ' has already checked out — here is the bill.')
                : redirect()->route('front-office.check-in-details')
                    ->with('error', $checkIn->guest_name . ' has already checked out.');
        }

        // Post anything the booking owes that the folio has not got yet — a
        // stay extended elsewhere, a rate corrected on the booking, and the
        // services the guest took when they booked.
        $folio = Folio::for($checkIn);
        $folio->post($request->user()->user_id);

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
            // "No Tax" first, and that is where the Add Folio dialog opens.
            'taxChoices' => Tax::options(Helper::getActiveBranchId()),
            'defaultTaxChoice' => Tax::defaultChoice(),
            'billingInstructions' => BillingInstruction::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'chargeTypes' => ['service' => 'Services', 'misc' => 'Miscellaneous'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Folio
    |--------------------------------------------------------------------------
    */

    /** Add Folio — a restaurant bill, laundry, anything the guest owes. */
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
            // Still accepted so an existing integration keeps working, but the
            // dropdown is what the screen posts and the dropdown wins.
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

        // Room nights are posted by the system from the stay's own dates.
        // Deleting one by hand would make the bill disagree with the calendar.
        if ($charge->isSystem()) {
            return back()->with('error', 'A room night cannot be removed by hand — change the checkout date instead.');
        }

        // Once a charge is settled it is part of a bill the guest has already
        // been charged and paid against — removing it now would leave that
        // payment on record for a line that no longer exists on the folio.
        if ($charge->is_settled) {
            return back()->with('error', 'This charge has already been settled and cannot be removed.');
        }

        $charge->delete();

        return back()->with('status', 'Charge removed from the folio.');
    }

    /*
    |--------------------------------------------------------------------------
    | Extend, pax
    |--------------------------------------------------------------------------
    */

    /** Guest Checkout Date Extend. */
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

        // Shortening a stay would leave nights on the folio nobody stayed, and
        // the guest may already have paid for them.
        if ($to < $current && $checkIn->charges()->ofType('room')->whereDate('charge_date', '>=', $to)->exists()) {
            return back()->with(
                'error',
                "Nights from {$to} are already on the folio. Remove the payment first, or extend rather than shorten."
            );
        }

        if ($checkIn->room_id && $to > $current) {
            // Somebody else may be booked into this room from tomorrow. The
            // guest's own booking row is not somebody else.
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

        DB::transaction(function () use ($checkIn, $to, $request) {
            $checkIn->update(['expected_checkout_date' => $to]);

            /*
             * The booking row has to follow the stay. Every screen built on
             * reservation_rooms — the tape chart, the status view the desk
             * sells from — would otherwise still show the guest leaving on the
             * old date and offer their room to the next caller. Only for a
             * single-room row: a row covering three rooms is not this one
             * guest's to move.
             */
            $row = $checkIn->reservationRoom;

            if ($row && (int) $row->no_of_rooms === 1 && $to > $row->checkout_date->toDateString()) {
                $row->moveCheckoutTo($to);
                $row->reservation?->refreshTotals();
            }

            Folio::for($checkIn->fresh())->postRoomCharges($request->user()->user_id);
        });

        return back()->with('status', sprintf(
            '%s now leaves on %s — %d night(s) on the folio.',
            $checkIn->guest_name,
            CarbonImmutable::parse($to)->format('d M Y'),
            $checkIn->fresh()->charges()->ofType('room')->count()
        ));
    }

    /** Pax Checkout — some of the people in the room leaving early. */
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

    /*
    |--------------------------------------------------------------------------
    | Settling
    |--------------------------------------------------------------------------
    */

    /** Take a payment without closing the stay — the Multiple Pay Mode case. */
    public function pay(Request $request, CheckIn $checkIn): RedirectResponse
    {
        $this->guard($checkIn);

        $data = $this->paymentRules($request);

        Settlement::create($this->paymentAttributes($data) + [
            'branch_id' => $checkIn->branch_id,
            'check_in_id' => $checkIn->id,
            'created_by' => $request->user()->user_id,
        ]);

        $totals = Folio::for($checkIn->fresh())->totals();

        GuestMessage::send('guest.payment', $checkIn->mobile, [
            'guest' => $checkIn->guest_name,
            'guest_email' => $checkIn->reservation?->email,
            'amount' => '₹ ' . number_format((float) $data['amount'], 2),
            'folio' => $checkIn->folio_no,
            'balance' => '₹ ' . number_format($totals['due'], 2),
        ], $checkIn->branch_id);

        Notify::event('payment.received')
            ->title('₹ ' . number_format((float) $data['amount'], 2) . ' taken — ' . $checkIn->guest_name)
            ->body('Folio ' . $checkIn->folio_no . ' · due now ₹ ' . number_format($totals['due'], 2))
            ->url(route('front-office.check-out-guest'))
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

    /**
     * Checkout (F10) — the bill is written and the guest leaves.
     */
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

        // Normalised once, here. `date` accepts 09/12/2026 as readily as
        // 2026-12-09, and the two sort differently as strings — a raw value
        // compared against a stored date would shorten the wrong stay.
        $data['checkout_date'] = CarbonImmutable::parse($data['checkout_date'])->toDateString();

        if ($data['checkout_date'] < $checkIn->checkin_date->toDateString()) {
            return back()->withInput()->with('error', 'A guest cannot leave before the day they arrived.');
        }

        $folio = Folio::for($checkIn);
        $discount = $folio->discountOf($data['discount_mode'] ?? 'amount', (float) ($data['discount_value'] ?? 0));
        $totals = $folio->totals($discount);

        // A last payment can be taken on the way out.
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

            // A guest leaving before their booked checkout date gives back
            // those nights now, before the bill is written — otherwise the
            // room is freed to resell in this same transaction while the
            // folio, and the bill about to be copied from it, still charge
            // for nights nobody is going to spend in it. Harmless to call on
            // an on-time or late checkout too: there is nothing past the
            // checkout date to trim, so it matches no rows.
            Folio::for($checkIn)->trimRoomCharges($data['checkout_date']);

            // Re-read after the payment and the trim, so the bill records
            // what was actually taken and actually owed, not what was true a
            // moment ago.
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

            // The room is empty and needs making up before it is sold again.
            if ($checkIn->room_id) {
                Room::whereKey($checkIn->room_id)->update(['housekeeping_status' => 'dirty']);
            }

            /*
             * A guest leaving early gives their remaining nights back. The
             * booking row goes on holding the room until its own checkout
             * date, so without this the desk could not sell a room that is
             * standing empty, and the tape chart would keep drawing a bar over
             * it. The money is settled on the bill; this only shortens what
             * the booking is still holding.
             */
            $row = $checkIn->reservationRoom;

            if ($row && (int) $row->no_of_rooms === 1 && $data['checkout_date'] < $row->checkout_date->toDateString()) {
                $row->moveCheckoutTo($data['checkout_date']);
                $row->reservation?->refreshTotals();
            }

            // With the last guest gone the booking is closed, which is what
            // releases the nights it was still holding on that room.
            $checkIn->reservation?->refreshCheckOutStatus();

            return $bill;
        });

        /*
         * Two things happen on the way out, and neither may take the checkout
         * down with it — the bill is already written and the guest is already
         * in the car park.
         *
         * The points are awarded from room revenue only: a guest should not
         * earn loyalty on the restaurant bill they signed for somebody else,
         * and awardStay refuses to pay the same stay twice.
         */
        if ($checkIn->guest_id) {
            rescue(fn () => GuestCrm::awardStay(
                (int) $checkIn->guest_id,
                (int) $checkIn->id,
                (float) $checkIn->charges()->ofType('room')->sum('amount'),
                (int) $checkIn->branch_id
            ), null, false);

            rescue(fn () => GuestCrm::recount($checkIn->guest), null, false);
        }

        /*
         * The feedback link is made here rather than when the guest answers,
         * so "we asked and they did not reply" is a fact the hotel holds. It
         * rides out on the checkout message; if that message is switched off,
         * the row still exists and the link can be sent by hand.
         */
        $feedback = rescue(
            fn () => GuestCrm::feedbackFor((int) $checkIn->branch_id, (int) $checkIn->id, $checkIn->guest_id),
            null,
            false
        );

        GuestMessage::send('guest.checkout', $checkIn->mobile, [
            'guest' => $checkIn->guest_name,
            'guest_email' => $checkIn->reservation?->email,
            'bill_no' => $bill->bill_no,
            'room' => $checkIn->room?->room_no,
            'amount' => '₹ ' . number_format((float) $bill->net_amount, 2),
            'paid' => '₹ ' . number_format((float) $bill->paid_amount, 2),
            'balance' => (float) $bill->balance_amount > 0
                ? '₹ ' . number_format((float) $bill->balance_amount, 2)
                : null,
            'feedback_url' => $feedback ? route('guest-feedback', $feedback->token) : null,
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

    /** Proforma Invoice — the bill before the guest has left. */
    public function proforma(Request $request, CheckIn $checkIn): View
    {
        $this->guard($checkIn, allowCheckedOut: true);

        $folio = Folio::for($checkIn);

        // A proforma is often the first thing the desk opens, before the
        // checkout screen has ever been looked at — so the nights have to be
        // posted here too or the guest is handed a blank bill. Never for a
        // stay that has left: its bill is closed.
        if ($checkIn->isInHouse()) {
            $folio->post($request->user()->user_id);
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

    /** The saved bill, reprinted exactly as it was written. */
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

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

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
            // Card details only make sense for a card payment.
            'card_type' => $isCard ? ($data['card_type'] ?? null) : null,
            'card_name' => $isCard ? ($data['card_name'] ?? null) : null,
            'card_last4' => $isCard ? ($data['card_last4'] ?? null) : null,
            'pan_no' => ($data['pan_no'] ?? null) ? strtoupper($data['pan_no']) : null,
            'reference_no' => $data['reference_no'] ?? null,
            'remark' => $data['remark'] ?? null,
        ];
    }

    /**
     * Why the room is not free over those nights, or null.
     *
     * `$exceptRow` is the guest's own booking row: a stay is always sitting on
     * top of the booking that created it, so without the exception every
     * extension would report the guest clashing with themselves.
     */
    private function clashOn(int $roomId, string $from, string $to, int $exceptCheckIn, ?int $exceptRow = null): ?string
    {
        $booking = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('rr.room_id', $roomId)
            // "Checked in" counts too: a booking with a second room still to
            // come is a live hold on this room, however its status reads.
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
