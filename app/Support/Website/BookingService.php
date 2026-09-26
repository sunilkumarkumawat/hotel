<?php

namespace App\Support\Website;

use App\Models\Country\Country;
use App\Models\Master\Guest;
use App\Models\Master\RoomType;
use App\Models\Reservation\AdvanceDeposit;
use App\Models\Reservation\Reservation;
use App\Models\Website\WebsitePayment;
use App\Support\Money;
use App\Support\Tax;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid website booking into a real reservation.
 *
 * Deliberately its own, independent path rather than a refactor of
 * ReservationController::store() — that controller is the one every desk
 * clerk relies on today, and the risk of a slip while extracting shared
 * code from it is worse than the small amount of duplication here. This
 * class mirrors its logic where it matters (guest matching, Money/Tax
 * pricing, the same columns saveRooms() writes) so a web booking looks, on
 * the reservation screen, exactly like one a clerk typed in by hand — with
 * one deliberate difference: {@see RoomAvailability::assertAvailable()}
 * takes a real row lock and re-checks inventory immediately before the
 * insert, which the admin flow does not. The admin desk has a person
 * glancing at the screen before they hit save; an unattended public website
 * does not, so it needs the database itself to be the thing that stops two
 * guests both winning the last room.
 */
class BookingService
{
    /**
     * Price a stay without touching the database's row locks — safe to call
     * as often as the guest changes dates or room count while browsing.
     *
     * @return array{room_type: RoomType, nights: int, nightly: float, amount: float, tax_percent: float, tax_amount: float, net_amount: float}
     */
    public static function quote(int $roomTypeId, int $branchId, string $from, string $to, int $noOfRooms): array
    {
        $roomType = RoomType::query()->forBranch($branchId)->active()->findOrFail($roomTypeId);
        $nights = Money::nights($from, $to);

        $figures = Money::roomRow([
            'room_rent' => (float) $roomType->base_rent,
            'discount' => 0,
            'plan_charge' => 0,
            'no_of_days' => $nights,
            'no_of_rooms' => max(1, $noOfRooms),
            'tax_type' => 'exclusive',
            'tax_choice' => Tax::defaultChoice(),
        ], $branchId);

        return [
            'room_type' => $roomType,
            'nights' => $nights,
            'nightly' => $figures['nightly'],
            'amount' => $figures['amount'],
            'tax_percent' => $figures['tax_percent'],
            'tax_amount' => $figures['tax_amount'],
            'net_amount' => $figures['net_amount'],
        ];
    }

    /**
     * Create the reservation a guest already paid for.
     *
     * Everything in here runs inside one transaction, and the availability
     * check is the first thing it does — while holding a lock that makes
     * every other concurrent call to this method for the same room type
     * queue up behind it (see RoomAvailability::assertAvailable()). Nothing
     * is written until that check passes.
     *
     * @param  array{title?:string,first_name:string,last_name?:string,email?:string,mobile:string,arrival_date:string,checkout_date:string,room_type_id:int,no_of_rooms:int,adults?:int,children?:int,remark?:string}  $booking
     *
     * @throws RoomsUnavailableException when the rooms sold out between the
     *                                    quote and the payment landing
     */
    public static function createPaidBooking(array $booking, int $branchId, WebsitePayment $payment): Reservation
    {
        return DB::transaction(function () use ($booking, $branchId, $payment) {
            $from = $booking['arrival_date'];
            $to = $booking['checkout_date'];
            $roomTypeId = (int) $booking['room_type_id'];
            $noOfRooms = max(1, (int) $booking['no_of_rooms']);

            // Locked, and re-checked here rather than trusted from whenever
            // the order was created — see the class docblock.
            RoomAvailability::assertAvailable($roomTypeId, $branchId, $from, $to, $noOfRooms);

            $roomType = RoomType::query()->forBranch($branchId)->findOrFail($roomTypeId);
            $countryId = Country::query()->where('name', 'India')->value('id');

            $guest = self::syncGuest($booking, $branchId, $countryId);

            $reservation = Reservation::create([
                'branch_id' => $branchId,
                'reservation_no' => Reservation::nextNumber($branchId),
                'reservation_date' => now()->toDateString(),
                'guest_id' => $guest->id,
                'title' => $booking['title'] ?? null,
                'first_name' => $booking['first_name'],
                'last_name' => $booking['last_name'] ?? null,
                'email' => $booking['email'] ?? null,
                'mobile' => $booking['mobile'],
                'country_id' => $countryId,
                'reservation_type' => 'confirm',
                'status' => 'confirmed',
                'remark' => trim(implode("\n\n", array_filter([
                    trim((string) ($booking['remark'] ?? '')) ?: null,
                    'Booked online via the hotel website.',
                ]))),
                'created_by' => null,
            ]);

            $nights = Money::nights($from, $to);

            $figures = Money::roomRow([
                'room_rent' => (float) $roomType->base_rent,
                'discount' => 0,
                'plan_charge' => 0,
                'no_of_days' => $nights,
                'no_of_rooms' => $noOfRooms,
                'tax_type' => 'exclusive',
                'tax_choice' => Tax::defaultChoice(),
            ], $branchId);

            $reservation->rooms()->create([
                'arrival_date' => $from,
                'arrival_time' => config('pms.default_arrival_time'),
                'checkout_date' => $to,
                'checkout_time' => config('pms.default_checkout_time'),
                'guest_type' => 'adv_booking',
                'room_category_id' => $roomType->room_category_id,
                'room_type_id' => $roomTypeId,
                'plan_type_id' => null,
                // Allotted at check-in, same as every other booking path.
                'room_id' => null,
                'room_no' => null,
                'no_of_days' => $nights,
                'no_of_rooms' => $noOfRooms,
                'tax_type' => 'exclusive',
                'tax_choice' => Tax::normalise(Tax::defaultChoice()),
                'room_rent' => (float) $roomType->base_rent,
                'discount' => 0,
                'plan_charge' => 0,
                // The public form asks for "adults", not a male/female split —
                // everything downstream only ever sums male + female back
                // together into one guest count, so putting the whole adult
                // count in one bucket is a safe simplification, not a guess
                // at something the guest was never asked.
                'male' => max(1, (int) ($booking['adults'] ?? 1)),
                'female' => 0,
                'child' => (int) ($booking['children'] ?? 0),
                'amount' => $figures['amount'],
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'net_amount' => $figures['net_amount'],
            ]);

            $reservation->refreshTotals();

            // The guest already paid in full through Razorpay before this
            // reservation existed — recorded as a deposit so the front desk
            // opens this booking and sees it is paid, not owing.
            AdvanceDeposit::create([
                'branch_id' => $branchId,
                'reservation_id' => $reservation->id,
                'deposit_date' => now()->toDateString(),
                'pay_mode_id' => null,
                'amount' => (float) $payment->amount,
                'reference_no' => 'Razorpay ' . $payment->gateway_payment_id,
                'type' => 'deposit',
                'remark' => 'Paid online via the hotel website (Razorpay).',
                'created_by' => null,
            ]);

            $reservation->refreshAdvancePaid();

            return $reservation;
        });
    }

    /**
     * Match or create the guest record, the same way
     * ReservationController::syncGuest() does — same mobile, same branch, is
     * the same person.
     *
     * Gentler than the admin version on an existing match, though: the admin
     * form overwrites every field because a blank field there means a clerk
     * cleared it. The website's form only ever asks for name, mobile and
     * email, so a blank here means "not asked", not "cleared" — a returning
     * guest's address, DOB or gender already on file from an earlier stay
     * must not be wiped out just because the short website form never
     * collected them.
     */
    private static function syncGuest(array $data, int $branchId, ?int $countryId): Guest
    {
        $match = Guest::query()->forBranch($branchId)->where('mobile', $data['mobile'])->first();

        $fresh = array_filter([
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'country_id' => $countryId,
        ], fn ($value) => filled($value));

        if ($match) {
            $match->update($fresh);

            return $match;
        }

        return Guest::create($fresh + [
            'branch_id' => $branchId,
            'mobile' => $data['mobile'],
        ]);
    }
}
