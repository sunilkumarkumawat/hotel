<?php

namespace App\Support;

use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\FolioCharge;
use App\Models\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A guest's folio: everything they owe, and everything they have paid.
 *
 * All the checkout arithmetic lives here rather than in the controller or the
 * view, so the screen, the proforma invoice and the saved bill can never
 * disagree about what a guest owes.
 *
 * Room rent is posted a night at a time (see postRoomCharges), which is what
 * makes extending a stay simply add nights and lets the bill name the date of
 * every night it charges for.
 */
class Folio
{
    public function __construct(private CheckIn $checkIn) {}

    public static function for(CheckIn $checkIn): self
    {
        return new self($checkIn);
    }

    /**
     * The rate one night of this stay is worth.
     *
     * Room rent **plus the plan's charge**, minus the discount — exactly the
     * arithmetic the booking screen used, from the figures stored on the stay
     * rather than looked up again. A guest quoted ₹3,600 + ₹1,400 for American
     * Plan owes ₹5,000 a night, and the bill has to say so.
     *
     * @return array{nightly: float, amount: float, tax_percent: float, tax_amount: float, net_amount: float}
     */
    public function nightlyFigures(): array
    {
        $checkIn = $this->checkIn;

        return Money::roomRow([
            'room_rent' => (float) $checkIn->room_rent,
            'discount' => (float) $checkIn->discount,
            'plan_charge' => (float) $checkIn->plan_charge,
            'no_of_days' => 1,
            'no_of_rooms' => 1,
            'tax_type' => $checkIn->tax_type,
            'tax_choice' => $checkIn->tax_choice,
            'tax_percent' => (float) $checkIn->tax_percent,
        ], $checkIn->branch_id);
    }

    /**
     * Make sure every night of the stay has a room charge on the folio.
     *
     * Idempotent on purpose: it is called on check-in, after an extension and
     * again when the checkout screen opens, and it must never double-charge a
     * night it has already posted.
     *
     * Nights already posted are re-priced to the stay's current rate while the
     * guest is still in the house, so a rate corrected on the booking reaches
     * the bill. Once the guest has checked out the Bill is a frozen copy and
     * nothing here can move it.
     *
     * @return int how many nights were added
     */
    public function postRoomCharges(?int $userId = null): int
    {
        $checkIn = $this->checkIn;

        $from = CarbonImmutable::parse($checkIn->checkin_date->toDateString());
        $to = CarbonImmutable::parse($checkIn->departsOn());

        // A stay that has not reached its second day is still one night.
        if ($to <= $from) {
            $to = $from->addDay();
        }

        $existing = FolioCharge::query()
            ->where('check_in_id', $checkIn->id)
            ->ofType('room')
            ->get()
            ->keyBy(fn (FolioCharge $c) => substr((string) $c->getRawOriginal('charge_date'), 0, 10));

        $figures = $this->nightlyFigures();
        $posted = 0;

        for ($night = $from; $night < $to; $night = $night->addDay()) {
            $key = $night->toDateString();

            $line = [
                'particulars' => $this->roomLabel($night->format('d/m/Y')),
                'qty' => 1,
                'price' => $figures['nightly'],
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'amount' => $figures['amount'],
                'total_amount' => $figures['net_amount'],
            ];

            if ($charge = $existing->get($key)) {
                $this->repair($charge, $line);

                continue;
            }

            FolioCharge::create($line + [
                'branch_id' => $checkIn->branch_id,
                'check_in_id' => $checkIn->id,
                'charge_date' => $key,
                'charge_type' => 'room',
                'created_by' => $userId,
            ]);

            $posted++;
        }

        return $posted;
    }

    /**
     * Charge the services taken with the booking.
     *
     * An airport transfer added at booking time is money the guest owes, and
     * it used to sit on the reservation and never reach the bill. One booking
     * can cover several rooms, so each service is posted once — against
     * whichever stay is asked first.
     *
     * "Already posted" is stamped on the reservation's own service row, not
     * worked out from the folio line. The line can be removed at the desk when
     * a guest does not take the transfer after all, and a removed charge must
     * stay removed rather than coming back the next time a screen opens.
     *
     * @return int how many services were added
     */
    public function postReservationServices(?int $userId = null): int
    {
        $checkIn = $this->checkIn;
        $reservation = $checkIn->reservation;

        if (! $reservation) {
            return 0;
        }

        $services = $reservation->services()->whereNull('posted_at')->get();
        $posted = 0;

        foreach ($services as $service) {
            FolioCharge::create([
                'branch_id' => $checkIn->branch_id,
                'check_in_id' => $checkIn->id,
                'charge_date' => $checkIn->checkin_date->toDateString(),
                'charge_type' => 'service',
                'service_id' => $service->service_id,
                'reservation_service_id' => $service->id,
                'tax_choice' => $service->tax_choice,
                'particulars' => Str::limit(
                    $service->service_name . ' (booked with ' . $reservation->reservation_no . ')',
                    250
                ),
                'qty' => $service->qty,
                'price' => $service->price,
                'tax_percent' => $service->tax_percent,
                'tax_amount' => $service->tax_amount,
                'amount' => $service->amount,
                'total_amount' => $service->total_amount,
                'remark' => $service->remark,
                'created_by' => $userId,
            ]);

            $service->update(['posted_at' => now()]);

            $posted++;
        }

        return $posted;
    }

    /**
     * Give the booking's services back so they can be charged again.
     *
     * Used when an arrival is undone: the folio goes with the check-in, so the
     * services have to become postable again for whoever arrives instead.
     */
    public function releaseReservationServices(): void
    {
        $ids = FolioCharge::query()
            ->where('check_in_id', $this->checkIn->id)
            ->whereNotNull('reservation_service_id')
            ->pluck('reservation_service_id');

        if ($ids->isNotEmpty()) {
            ReservationService::whereIn('id', $ids)->update(['posted_at' => null]);
        }
    }

    /** Everything the booking owes that the folio has not got yet. */
    public function post(?int $userId = null): int
    {
        return $this->postRoomCharges($userId) + $this->postReservationServices($userId);
    }

    /** Every line on the folio, oldest first. */
    public function charges(): Collection
    {
        return FolioCharge::query()
            ->where('check_in_id', $this->checkIn->id)
            ->orderBy('charge_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * The folio grouped the way the checkout screen shows it — one row per
     * department, with the lines behind it.
     */
    public function departments(): Collection
    {
        return $this->charges()
            ->groupBy('charge_type')
            ->map(fn (Collection $lines, string $type) => [
                'type' => $type,
                'label' => FolioCharge::TYPES[$type] ?? ucfirst($type),
                'lines' => $lines,
                'amount' => round($lines->sum(fn ($l) => (float) $l->amount), 2),
                'tax' => round($lines->sum(fn ($l) => (float) $l->tax_amount), 2),
                'total' => round($lines->sum(fn ($l) => (float) $l->total_amount), 2),
            ])
            ->values();
    }

    /**
     * What the guest has already given the hotel.
     *
     * The advance sits on the reservation, not the check-in, and one booking
     * can cover several rooms — so a room's share is the advance split across
     * the rooms checked in against that booking. Charging the whole advance to
     * the first room to leave would make the other rooms look unpaid.
     */
    public function advance(): float
    {
        $reservation = $this->checkIn->reservation;

        if (! $reservation) {
            return 0.0;
        }

        $rooms = max(1, $reservation->checkIns()->where('status', '!=', 'cancelled')->count());

        return round((float) $reservation->advance_paid / $rooms, 2);
    }

    /** Money taken at checkout against this stay. */
    public function paid(): float
    {
        return round((float) $this->checkIn->settlements()->sum('amount'), 2);
    }

    /**
     * The whole bill.
     *
     * `discount` is what the desk is knocking off right now — it is passed in
     * rather than stored, so the screen can show the effect of a discount
     * before anybody commits to it.
     *
     * @return array<string, float|int>
     */
    public function totals(float $discount = 0.0): array
    {
        $charges = $this->charges();

        $amount = round($charges->sum(fn ($l) => (float) $l->amount), 2);
        $tax = round($charges->sum(fn ($l) => (float) $l->tax_amount), 2);
        $room = round($charges->where('charge_type', 'room')->sum(fn ($l) => (float) $l->total_amount), 2);
        $service = round($charges->where('charge_type', '!=', 'room')->sum(fn ($l) => (float) $l->total_amount), 2);

        // Never discount more than the bill is worth.
        $discount = max(0, min(round($discount, 2), $amount + $tax));

        $net = round($amount + $tax - $discount, 2);
        $advance = $this->advance();
        $paid = $this->paid();

        $due = round($net - $advance - $paid, 2);

        return [
            'sub_total' => $amount,
            'tax' => $tax,
            'room_total' => $room,
            'service_total' => $service,
            'discount' => $discount,
            'advance' => $advance,
            'paid' => $paid,
            'net' => $net,
            // A guest who paid more than the bill gets the difference back, so
            // one of these two is always zero.
            'due' => max(0, $due),
            'refund' => max(0, -$due),
            'nights' => $charges->where('charge_type', 'room')->count(),
        ];
    }

    /**
     * Give back nights the guest is not staying for after all.
     *
     * Called right before checkout freezes the bill, whenever the guest may be
     * leaving earlier than the nights already posted cover. Safe to call on
     * every checkout, early or not: the date filter matches nothing when there
     * is nothing to give back, and it never touches a night that has already
     * been settled — same rule repair() uses while the guest is still in house.
     *
     * Without this, a bill can be written from totals() that still include
     * nights the desk is, in the same transaction, freeing the room to resell.
     */
    public function trimRoomCharges(string $onOrAfter): int
    {
        return FolioCharge::query()
            ->where('check_in_id', $this->checkIn->id)
            ->ofType('room')
            ->where('is_settled', 0)
            ->whereDate('charge_date', '>=', $onOrAfter)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * What a room night is called on the bill.
     *
     * The plan is named when it costs something, because a guest looking at
     * ₹5,000 for a ₹3,600 room deserves to see where the rest came from.
     */
    private function roomLabel(string $date): string
    {
        $room = $this->checkIn->room?->room_no ?? '—';
        $plan = (float) $this->checkIn->plan_charge > 0
            ? ' · ' . ($this->checkIn->plan?->code ?: $this->checkIn->plan?->name)
            : '';

        return Str::limit('Room Tariff - ' . $room . rtrim($plan) . ' (' . $date . ')', 250);
    }

    /**
     * Bring an already-posted night back in line with the stay's rate.
     *
     * Only while the guest is in the house and the line is unsettled — a night
     * on a bill that has been paid is history, not a working figure.
     */
    private function repair(FolioCharge $charge, array $line): void
    {
        if (! $this->checkIn->isInHouse() || $charge->is_settled) {
            return;
        }

        $same = (float) $charge->price === (float) $line['price']
            && (float) $charge->tax_percent === (float) $line['tax_percent']
            && (float) $charge->total_amount === (float) $line['total_amount']
            && $charge->particulars === $line['particulars'];

        if (! $same) {
            $charge->update($line);
        }
    }

    /**
     * Work a discount out from what the desk typed.
     *
     * A percentage is of the taxed total, which is what a guest understands by
     * "10% off" when they are looking at the bill.
     */
    public function discountOf(string $mode, float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($mode !== 'percent') {
            return round($value, 2);
        }

        $totals = $this->totals();

        return round(($totals['sub_total'] + $totals['tax']) * min($value, 100) / 100, 2);
    }
}
