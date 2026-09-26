<?php

namespace App\Http\Controllers;

use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Ledger;
use App\Models\Branch\Branch;
use App\Models\City\City;
use App\Models\Country\Country;
use App\Models\FrontOffice\Bill;
use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\CheckInPax;
use App\Models\FrontOffice\FolioCharge;
use App\Models\FrontOffice\Settlement;
use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Models\Master\Guest;
use App\Models\Master\PayMode;
use App\Models\Master\PickDrop;
use App\Models\Master\PlanType;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\Master\RoomType;
use App\Models\Master\Service as ServiceMaster;
use App\Models\Master\VisitPurpose;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosInvoice;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosPayment;
use App\Models\Pos\PosTable;
use App\Models\Pos\PosTableGroup;
use App\Models\Reservation\AdvanceDeposit;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use App\Models\Reservation\ReservationService;
use App\Models\State\State;
use App\Support\GuestCrm;
use App\Support\Money;
use App\Support\Vouchers;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class DevSeedRestoreController extends Controller
{
    private const SECRET_KEY = 'pms-restore-seed-6d4b28fa';

    private const TAG = '[DEMO SEED]';

    private const EMAIL_SUFFIX = '@example-demo.test';

    private const TARGET_GUESTS = 130;

    private const TARGET_RESERVATIONS = 215;

    private const TARGET_CHECK_INS = 116;

    private const TARGET_BILLS = 100;

    private const TARGET_DEPOSITS = 146;

    private const TARGET_VOUCHERS = 110;

    private const TARGET_POS_ORDERS = 130;

    private const FIRST_NAMES_MALE = [
        'Amit', 'Rajesh', 'Suresh', 'Vikram', 'Arjun', 'Rohan', 'Karan', 'Sanjay',
        'Manoj', 'Deepak', 'Anil', 'Vivek', 'Ashok', 'Gaurav', 'Nikhil', 'Pankaj',
        'Rahul', 'Sandeep', 'Vijay', 'Yash', 'Ramesh', 'Naveen', 'Harish', 'Sunil',
    ];

    private const FIRST_NAMES_FEMALE = [
        'Priya', 'Sunita', 'Anjali', 'Kavita', 'Neha', 'Pooja', 'Meena', 'Rekha',
        'Swati', 'Deepa', 'Anita', 'Kiran', 'Shweta', 'Ritu', 'Nisha', 'Sonal',
        'Divya', 'Preeti', 'Geeta', 'Lata', 'Asha', 'Manisha', 'Radhika', 'Simran',
    ];

    private const SURNAMES = [
        'Sharma', 'Verma', 'Gupta', 'Kumar', 'Singh', 'Patel', 'Reddy', 'Nair',
        'Iyer', 'Joshi', 'Mehta', 'Chopra', 'Agarwal', 'Malhotra', 'Rao', 'Desai',
        'Kapoor', 'Bansal', 'Saxena', 'Trivedi', 'Pillai', 'Menon', 'Bhatt', 'Rastogi',
    ];

    private const LOCALITIES = [
        'MG Road', 'Station Road', 'Civil Lines', 'Model Town', 'Sector 15',
        'Park Street', 'Ring Road', 'Gandhi Nagar', 'Nehru Place', 'Church Street',
        'Lake View Road', 'Mall Road',
    ];

    private const ID_TYPES_WEIGHTED = [
        'Aadhaar', 'Aadhaar', 'Aadhaar', 'Aadhaar', 'Aadhaar', 'Aadhaar', 'Aadhaar',
        'Passport', 'Driving License', 'Driving License', 'Voter ID',
    ];

    private const CANCEL_REASONS = [
        'Guest changed travel plans', 'Booked a different property',
        'Duplicate booking raised in error', 'Payment not received in time',
        'Guest requested cancellation by phone', 'Event the stay was booked around was postponed',
    ];

    private const EXPENSE_NARRATIONS = [
        'Electricity bill', 'Generator diesel', 'Linen and housekeeping supplies',
        'Plumbing repair', 'Internet and WiFi bill', 'Pest control service',
        'Stationery and printing', 'Vehicle maintenance', 'Guest amenities restock',
        'Bank charges', 'OTA commission settlement', 'Staff refreshments',
    ];

    private const INCOME_NARRATIONS = [
        'Banquet hall advance received', 'Miscellaneous income', 'Old newspaper and scrap sale',
        'Corporate client advance received', 'Forfeited booking amount', 'Interest credited by bank',
    ];

    private const FALLBACK_MENU = [
        'Masala Chai' => 40, 'Filter Coffee' => 50, 'Veg Sandwich' => 120,
        'Paneer Butter Masala' => 260, 'Dal Makhani' => 220, 'Butter Naan' => 45,
        'Veg Biryani' => 240, 'Chicken Curry' => 320, 'Cold Coffee' => 110,
        'Gulab Jamun' => 80, 'Fresh Lime Soda' => 70,
    ];

    private const WALKIN_NAMES = [
        'Mr. Aggarwal', 'Mrs. Nair', 'Ms. Kapoor', 'Mr. Bose', 'Mr. Chauhan',
        'Mrs. Iyengar', 'Mr. Shetty', 'Ms. Khan', 'Mr. Pandey', 'Mrs. Thakur',
    ];

    public function run(Request $request): JsonResponse
    {
        if ($request->query('key') !== self::SECRET_KEY) {
            abort(404);
        }

        if (app()->environment('production')) {
            abort(404);
        }

        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(404);
        }

        set_time_limit(300);

        $branchId = (int) (Room::query()->whereNotNull('branch_id')->value('branch_id') ?: 0);

        if (! $branchId) {
            $branchId = (int) (Branch::query()->where('status', 1)->value('id') ?: 0);
        }

        if (! $branchId) {
            return response()->json([
                'ok' => false,
                'error' => 'No branch could be resolved (no room carries a branch_id, and no active branch exists). Nothing to seed against.',
            ], 422);
        }

        $rooms = Room::query()->where('branch_id', $branchId)->active()->get();

        if ($rooms->isEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => "Branch {$branchId} has no active rooms. Nothing to check anybody into.",
            ], 422);
        }

        $roomTypes = RoomType::query()->forBranch($branchId)->active()->get();

        if ($roomTypes->isEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => "Branch {$branchId} has no active room types. Nothing to book a reservation_room against.",
            ], 422);
        }

        $masters = $this->loadMasters($branchId);
        $errors = [];
        $counts = array_fill_keys([
            'guests', 'reservations', 'reservation_rooms', 'reservation_services',
            'check_ins', 'check_in_pax', 'folio_charges', 'bills', 'settlements',
            'advance_deposits', 'vouchers', 'voucher_entries',
            'pos_orders', 'pos_order_items', 'pos_invoices', 'pos_payments',
        ], 0);
        $fallbackMasters = [];

        $summary = DB::transaction(function () use (
            $branchId, $rooms, $roomTypes, $masters, &$errors, &$counts, &$fallbackMasters
        ) {

            $guests = [];
            $plan = ['rows' => [], 'breakdown' => []];
            $meta = [];

            try {
                $guests = $this->seedGuests($branchId, $masters, self::TARGET_GUESTS, $errors);
                $counts['guests'] = count($guests);
            } catch (\Throwable $e) {
                $errors[] = 'guest phase aborted: ' . $e->getMessage();
            }

            try {
                $plan = $this->buildReservationPlan($rooms->count());
                $meta = $this->seedReservations(
                    $branchId, $plan, $guests, $masters, $roomTypes, $rooms, $errors, $counts
                );
            } catch (\Throwable $e) {
                $errors[] = 'reservation phase aborted: ' . $e->getMessage();
            }

            try {
                $this->seedAdvanceDeposits($branchId, $meta, $masters, $errors, $counts);
            } catch (\Throwable $e) {
                $errors[] = 'advance deposit phase aborted: ' . $e->getMessage();
            }

            try {
                $this->seedBillsAndSettlements($branchId, $meta, $masters, $errors, $counts);
            } catch (\Throwable $e) {
                $errors[] = 'bill phase aborted: ' . $e->getMessage();
            }

            try {
                $this->recountGuests($guests, $errors);
            } catch (\Throwable $e) {
                $errors[] = 'guest CRM recount phase aborted: ' . $e->getMessage();
            }

            try {
                $fallbackMasters['accounting'] = $this->seedVouchers($branchId, $masters, $errors, $counts);
            } catch (\Throwable $e) {
                $errors[] = 'voucher phase aborted: ' . $e->getMessage();
            }

            try {
                $fallbackMasters['pos'] = $this->seedPosOrders($branchId, $masters, $rooms, $errors, $counts);
            } catch (\Throwable $e) {
                $errors[] = 'POS phase aborted: ' . $e->getMessage();
            }

            return [
                'branch_id' => $branchId,
                'room_count' => $rooms->count(),
                'reservation_plan' => $plan['breakdown'],
            ];
        });

        return response()->json([
            'ok' => true,
            'branch_id' => $summary['branch_id'],
            'room_count' => $summary['room_count'],
            'created' => $counts,
            'reservation_breakdown' => $summary['reservation_plan'],
            'fallback_masters_created' => array_filter(array_merge(
                $fallbackMasters['accounting'] ?? [],
                $fallbackMasters['pos'] ?? []
            )),
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 25),
            'note' => 'created_by is NULL throughout. remark/narration reads exactly "[DEMO SEED]" on '
                . 'guests, reservations, check_ins, bills, advance_deposits, vouchers (narration) and '
                . 'pos_orders. Everything else these point at is real structure the property already '
                . 'had, or — see fallback_masters_created — a small amount added because a list was '
                . 'empty; either way it is not tagged and a bulk-delete by remark would not touch it.',
        ]);
    }

    
    /** @return array<string, mixed> */
    private function loadMasters(int $branchId): array
    {
        $india = Country::query()->where('name', 'India')->first();

        return [
            'india' => $india,
            'states' => $india ? State::query()->where('country_id', $india->id)->get() : collect(),
            'roomCategories' => RoomCategory::query()->forBranch($branchId)->active()->get(),
            'planTypes' => PlanType::query()->forBranch($branchId)->active()->get(),
            'payModes' => PayMode::query()->forBranch($branchId)->active()->get(),
            'services' => ServiceMaster::query()->forBranch($branchId)->active()->get(),
            'billingInstructions' => BillingInstruction::query()->forBranch($branchId)->active()->get(),
            'bookedBys' => BookedBy::query()->forBranch($branchId)->active()->get(),
            'businessMarkets' => BusinessMarket::query()->forBranch($branchId)->active()->get(),
            'visitPurposes' => VisitPurpose::query()->forBranch($branchId)->active()->get(),
            'pickDrops' => PickDrop::query()->forBranch($branchId)->active()->get(),
            'companies' => Company::query()->forBranch($branchId)->active()->get(),
            'branch' => Branch::find($branchId),
        ];
    }

    /** @return array<int, Guest> */
    private function seedGuests(int $branchId, array $masters, int $count, array &$errors): array
    {
        $created = [];
        /** @var Collection $states */
        $states = $masters['states'];
        $india = $masters['india'];
        /** @var Collection $companies */
        $companies = $masters['companies'];

        for ($i = 1; $i <= $count; $i++) {
            try {
                $isFemale = random_int(1, 100) <= 45;
                $first = Arr::random($isFemale ? self::FIRST_NAMES_FEMALE : self::FIRST_NAMES_MALE);
                $last = Arr::random(self::SURNAMES);
                $title = $isFemale ? Arr::random(['Mrs.', 'Ms.']) : 'Mr.';
                $state = $this->pickOrNull($states);
                $city = $state ? City::query()->where('state_id', $state->id)->inRandomOrder()->first() : null;
                $idType = Arr::random(self::ID_TYPES_WEIGHTED);

                $guest = Guest::create([
                    'branch_id' => $branchId,
                    'title' => $title,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $this->randomEmail($first, $last, $i),
                    'mobile' => $this->randomMobile(),
                    'address' => random_int(1, 999) . ', ' . Arr::random(self::LOCALITIES),
                    'dob' => CarbonImmutable::now()->subYears(random_int(22, 64))->subDays(random_int(0, 365))->toDateString(),
                    'gender' => $isFemale ? 'female' : 'male',
                    'country_id' => $india?->id,
                    'state_id' => $state?->id,
                    'city_id' => $city?->id,
                    'zip_code' => (string) random_int(110001, 999999),
                    'id_type' => $idType,
                    'id_number' => $this->randomIdNumber($idType),
                    'company_id' => ($companies->isNotEmpty() && random_int(1, 100) <= 15) ? $companies->random()->id : null,
                    'is_blacklisted' => 0,
                    'remark' => self::TAG,
                    'status' => 1,
                ]);

                $created[] = $guest;
            } catch (\Throwable $e) {
                $errors[] = "guest #{$i}: " . $e->getMessage();
            }
        }

        return $created;
    }

    /** @return array{rows: list<array<string, mixed>>, breakdown: array<string, int>} */
    private function buildReservationPlan(int $roomCount): array
    {

        $inHouse = max(1, min(10, $roomCount > 2 ? $roomCount - 2 : $roomCount));
        $checkedOut = self::TARGET_CHECK_INS - $inHouse;
        $noShow = 20;
        $cancelled = 24;
        $future = self::TARGET_RESERVATIONS - ($checkedOut + $inHouse + $noShow + $cancelled);

        $rows = [];

        for ($i = 0; $i < $checkedOut; $i++) {
            $arrivalOffset = -random_int(6, 90);
            $nights = random_int(1, 5);
            $lead = random_int(0, 25);

            $rows[] = $this->planRow('checked_out', $arrivalOffset, $arrivalOffset + $nights, $lead);
        }

        for ($i = 0; $i < $inHouse; $i++) {
            $arrivalOffset = -random_int(0, 3);
            $checkoutOffset = random_int(1, 4);
            $lead = random_int(0, 20);

            $rows[] = $this->planRow('in_house', $arrivalOffset, $checkoutOffset, $lead);
        }

        for ($i = 0; $i < $noShow; $i++) {
            $arrivalOffset = -random_int(1, 45);
            $nights = random_int(1, 4);
            $lead = random_int(1, 20);

            $rows[] = $this->planRow('no_show', $arrivalOffset, $arrivalOffset + $nights, $lead);
        }

        for ($i = 0; $i < $cancelled; $i++) {
            $inPast = $i % 2 === 0;
            $arrivalOffset = $inPast ? -random_int(1, 60) : random_int(1, 45);
            $nights = random_int(1, 4);
            $lead = random_int(1, 20);
            $row = $this->planRow('cancelled', $arrivalOffset, $arrivalOffset + $nights, $lead);
            $row['cancelled_on'] = CarbonImmutable::today()->subDays(random_int(0, 30))->toDateString();
            $row['cancel_reason'] = Arr::random(self::CANCEL_REASONS);
            $rows[] = $row;
        }

        for ($i = 0; $i < $future; $i++) {
            $arrivalOffset = random_int(1, 60);
            $nights = random_int(1, 6);
            $lead = random_int(0, 20);
            $row = $this->planRow('future', $arrivalOffset, $arrivalOffset + $nights, $lead);
            $row['reservation_type'] = random_int(1, 100) <= 22 ? 'tentative' : 'confirm';
            $row['status'] = $row['reservation_type'] === 'tentative' ? 'tentative' : 'confirmed';
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'breakdown' => [
                'checked_out_historical' => $checkedOut,
                'in_house_today' => $inHouse,
                'no_show' => $noShow,
                'cancelled' => $cancelled,
                'future_confirmed_or_tentative' => $future,
                'total' => count($rows),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function planRow(string $category, int $arrivalOffset, int $checkoutOffset, int $leadDays): array
    {
        $arrival = CarbonImmutable::today()->addDays($arrivalOffset);
        $checkout = CarbonImmutable::today()->addDays($checkoutOffset);
        $reservationDate = $arrival->subDays($leadDays);

        if ($reservationDate->isFuture()) {
            $reservationDate = CarbonImmutable::today();
        }

        return [
            'category' => $category,
            'arrival' => $arrival->toDateString(),
            'checkout' => $checkout->toDateString(),
            'reservation_date' => $reservationDate->toDateString(),
            'lead_days' => $leadDays,
            'reservation_type' => 'confirm',
            'status' => 'confirmed',
        ];
    }


    /**
     * @param  array<int, Guest>  $guests
     * @return list<array<string, mixed>> one row of bookkeeping per reservation, used by the deposit/bill phases
     */
    private function seedReservations(
        int $branchId,
        array $plan,
        array $guests,
        array $masters,
        Collection $roomTypes,
        Collection $rooms,
        array &$errors,
        array &$counts
    ): array {
        $guestPool = $this->weightedGuestPool(count($guests));
        $roomOccupancy = [];
        $meta = [];

        $checkedOutIndexes = [];
        foreach ($plan['rows'] as $i => $row) {
            if ($row['category'] === 'checked_out') {
                $checkedOutIndexes[] = $i;
            }
        }
        shuffle($checkedOutIndexes);
        $billedIndexes = array_flip(array_slice(
            $checkedOutIndexes, 0, min(self::TARGET_BILLS, count($checkedOutIndexes))
        ));

        foreach ($plan['rows'] as $i => $row) {
            DB::beginTransaction();

            try {
                $guest = $guests[$guestPool[array_rand($guestPool)]];

                $roomType = $roomTypes->random();
                $planType = $this->pickOrNull($masters['planTypes']);
                $planCharge = $planType ? (float) $planType->charge : 0.0;
                $roomRentBase = $this->roomRentFor($roomType, $rooms);
                $nightlyPreTax = max(0, $roomRentBase + $planCharge);
                $taxPercent = $nightlyPreTax > 7500 ? 18.0 : 12.0;
                $nights = max(1, (int) CarbonImmutable::parse($row['arrival'])->diffInDays($row['checkout']));

                $roomRow = Money::roomRow([
                    'room_rent' => $roomRentBase,
                    'discount' => 0,
                    'plan_charge' => $planCharge,
                    'no_of_days' => $nights,
                    'no_of_rooms' => 1,
                    'tax_type' => 'exclusive',
                    'tax_choice' => 'fixed',
                    'tax_percent' => $taxPercent,
                ], $branchId);

                $pax = $this->randomPax($guest);

                $reservation = Reservation::create([
                    'branch_id' => $branchId,
                    'reservation_no' => Reservation::nextNumber($branchId),
                    'reservation_date' => $row['reservation_date'],
                    'guest_id' => $guest->id,
                    'title' => $guest->title,
                    'first_name' => $guest->first_name,
                    'last_name' => $guest->last_name,
                    'email' => $guest->email,
                    'mobile' => $guest->mobile,
                    'address' => $guest->address,
                    'dob' => $guest->dob,
                    'gender' => $guest->gender,
                    'country_id' => $guest->country_id,
                    'state_id' => $guest->state_id,
                    'city_id' => $guest->city_id,
                    'zip_code' => $guest->zip_code,
                    'reservation_type' => $row['reservation_type'],
                    'pick_drop_id' => $this->pickOrNull($masters['pickDrops'])?->id,
                    'visit_purpose_id' => $this->pickOrNull($masters['visitPurposes'])?->id,
                    'booked_by_id' => $this->pickOrNull($masters['bookedBys'])?->id,
                    'business_market_id' => $this->pickOrNull($masters['businessMarkets'])?->id,
                    'company_id' => $guest->company_id,
                    'company_gst_no' => null,
                    'billing_instruction_id' => $this->pickOrNull($masters['billingInstructions'])?->id,
                    'pay_mode_id' => $this->pickOrNull($masters['payModes'])?->id,
                    'remark' => self::TAG,
                    'room_total' => 0,
                    'service_total' => 0,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'net_amount' => 0,
                    'advance_paid' => 0,
                    'status' => 'confirmed',
                    'created_by' => null,
                ]);
                $counts['reservations']++;

                $reservationRoom = ReservationRoom::create([
                    'reservation_id' => $reservation->id,
                    'arrival_date' => $row['arrival'],
                    'checkout_date' => $row['checkout'],
                    'guest_type' => $row['lead_days'] <= 0 ? 'walk_in' : 'adv_booking',
                    'room_category_id' => $roomType->room_category_id,
                    'room_type_id' => $roomType->id,
                    'plan_type_id' => $planType?->id,
                    'room_id' => null,
                    'room_no' => null,
                    'no_of_days' => $nights,
                    'no_of_rooms' => 1,
                    'tax_type' => 'exclusive',
                    'room_rent' => $roomRentBase,
                    'discount' => 0,
                    'plan_charge' => $planCharge,
                    'male' => $pax['male'],
                    'female' => $pax['female'],
                    'child' => $pax['child'],
                    'amount' => $roomRow['amount'],
                    'tax_percent' => $roomRow['tax_percent'],
                    'tax_choice' => 'fixed',
                    'tax_amount' => $roomRow['tax_amount'],
                    'net_amount' => $roomRow['net_amount'],
                ]);
                $counts['reservation_rooms']++;

                if (random_int(1, 100) <= 25 && $masters['services']->isNotEmpty()) {
                    $this->addReservationService($reservation, $masters['services'], $branchId, $counts);
                }

                $checkInId = null;

                if (in_array($row['category'], ['checked_out', 'in_house'], true)) {
                    $room = $this->pickRoom($rooms, $row['arrival'], $row['checkout'], $roomOccupancy);

                    if (! $room) {
                        throw new \RuntimeException('no free room for ' . $row['arrival'] . '..' . $row['checkout']);
                    }

                    $reservationRoom->update(['room_id' => $room->id, 'room_no' => $room->room_no]);

                    $checkIn = CheckIn::create([
                        'branch_id' => $branchId,
                        'reservation_id' => $reservation->id,
                        'reservation_room_id' => $reservationRoom->id,
                        'guest_id' => $guest->id,
                        'room_id' => $room->id,
                        'folio_no' => CheckIn::nextFolio($branchId),
                        'guest_name' => $reservation->guest_name,
                        'mobile' => $guest->mobile,
                        'checkin_date' => $row['arrival'],
                        'expected_checkout_date' => $row['checkout'],
                        'actual_checkout_date' => $row['category'] === 'checked_out' ? $row['checkout'] : null,
                        'plan_type_id' => $planType?->id,
                        'room_rent' => $roomRentBase,
                        'discount' => 0,
                        'plan_charge' => $planCharge,
                        'tax_type' => 'exclusive',
                        'tax_percent' => $taxPercent,
                        'tax_choice' => 'fixed',
                        'male' => $pax['male'],
                        'female' => $pax['female'],
                        'child' => $pax['child'],
                        'status' => 'in_house',
                        'is_direct' => 0,
                        'id_type' => $guest->id_type,
                        'id_number' => $guest->id_number,
                        'is_foreign' => false,
                        'remark' => self::TAG,
                        'created_by' => null,
                    ]);
                    $counts['check_ins']++;
                    $checkInId = $checkIn->id;

                    $this->addExtraPax($checkIn, $pax, $errors, $counts);

                    $reservation->refreshCheckInStatus();

                    $this->postFolioNights(
                        $branchId, $checkIn, $roomRow['nightly'], $taxPercent, $nights,
                        $row['arrival'], $row['category'] === 'checked_out', $errors, $counts
                    );

                    if ($row['category'] === 'checked_out') {
                        $checkIn->update(['status' => 'checked_out']);
                        $reservation->refreshCheckOutStatus();
                    }
                } elseif ($row['category'] === 'no_show') {
                    $reservation->update(['status' => 'no_show']);
                } elseif ($row['category'] === 'cancelled') {
                    $reservation->update([
                        'status' => 'cancelled',
                        'cancelled_on' => $row['cancelled_on'],
                        'cancel_reason' => $row['cancel_reason'],
                    ]);
                } else {
                    $reservation->update(['status' => $row['status']]);
                }

                $reservation->refreshTotals();

                $meta[] = [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'category' => $row['category'],
                    'check_in_id' => $checkInId,
                    'will_bill' => $checkInId !== null && $row['category'] === 'checked_out' && isset($billedIndexes[$i]),
                    'billing_instruction_id' => $reservation->billing_instruction_id,
                    'pay_mode_id' => $reservation->pay_mode_id,
                    'company_id' => $guest->company_id,
                ];

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = "reservation plan #{$i} ({$row['category']}): " . $e->getMessage();
            }
        }

        return $meta;
    }

    private function roomRentFor(RoomType $roomType, Collection $rooms): float
    {
        $base = (float) ($roomType->base_rent ?: 0);

        if ($base <= 0) {
            $roomWithRent = $rooms->first(fn (Room $r) => (float) $r->base_rent > 0);
            $base = $roomWithRent ? (float) $roomWithRent->base_rent : 2500.0;
        }

        $variance = random_int(-8, 12) / 100;

        return round(max(500, $base * (1 + $variance)), -1);
    }

    /** @return array{male: int, female: int, child: int} */
    private function randomPax(Guest $guest): array
    {
        $roll = random_int(1, 100);

        if ($roll <= 70) {
            return $guest->gender === 'female'
                ? ['male' => 0, 'female' => 1, 'child' => 0]
                : ['male' => 1, 'female' => 0, 'child' => 0];
        }

        if ($roll <= 95) {
            return ['male' => 1, 'female' => 1, 'child' => 0];
        }

        return ['male' => 1, 'female' => 1, 'child' => random_int(1, 2)];
    }

    private function addReservationService(Reservation $reservation, Collection $services, int $branchId, array &$counts): void
    {
        $service = $services->random();
        $qty = random_int(1, 2);
        $line = Money::serviceRow([
            'qty' => $qty,
            'price' => (float) $service->price,
            'tax_type' => 'exclusive',
            'tax_choice' => 'fixed',
            'tax_percent' => 12.0,
        ], $branchId);

        ReservationService::create([
            'reservation_id' => $reservation->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'tax_type' => 'exclusive',
            'qty' => $qty,
            'price' => $service->price,
            'tax_percent' => $line['tax_percent'],
            'tax_amount' => $line['tax_amount'],
            'amount' => $line['amount'],
            'total_amount' => $line['total_amount'],
            'remark' => null,
        ]);
        $counts['reservation_services']++;
    }

    private function addExtraPax(CheckIn $checkIn, array $pax, array &$errors, array &$counts): void
    {
        $extra = ($pax['male'] + $pax['female'] + $pax['child']) - 1;

        for ($i = 0; $i < $extra; $i++) {
            try {
                $isChild = $i >= ($pax['male'] + $pax['female'] - 1);
                $isFemale = ! $isChild && random_int(0, 1) === 1;

                CheckInPax::create([
                    'check_in_id' => $checkIn->id,
                    'name' => Arr::random($isFemale ? self::FIRST_NAMES_FEMALE : self::FIRST_NAMES_MALE) . ' ' . Arr::random(self::SURNAMES),
                    'age' => $isChild ? random_int(3, 14) : random_int(22, 60),
                    'gender' => $isChild ? Arr::random(['male', 'female']) : ($isFemale ? 'female' : 'male'),
                    'relation' => $isChild ? 'Child' : 'Spouse',
                ]);
                $counts['check_in_pax']++;
            } catch (\Throwable $e) {
                $errors[] = "check_in_pax for check_in {$checkIn->id}: " . $e->getMessage();
            }
        }
    }

    private function postFolioNights(
        int $branchId, CheckIn $checkIn, float $nightlyRate, float $taxPercent, int $nights,
        string $arrival, bool $settled, array &$errors, array &$counts
    ): void {
        for ($n = 0; $n < $nights; $n++) {
            try {
                $split = Money::split($nightlyRate, $taxPercent, 'exclusive');

                FolioCharge::create([
                    'branch_id' => $branchId,
                    'check_in_id' => $checkIn->id,
                    'charge_date' => CarbonImmutable::parse($arrival)->addDays($n)->toDateString(),
                    'charge_type' => 'room',
                    'service_id' => null,
                    'particulars' => 'Room Charge',
                    'qty' => 1,
                    'price' => $nightlyRate,
                    'tax_percent' => $taxPercent,
                    'tax_choice' => 'fixed',
                    'tax_amount' => $split['tax'],
                    'amount' => $split['amount'],
                    'total_amount' => $split['net'],
                    'is_settled' => $settled ? 1 : 0,
                    'remark' => null,
                    'created_by' => null,
                ]);
                $counts['folio_charges']++;
            } catch (\Throwable $e) {
                $errors[] = "folio charge night {$n} for check_in {$checkIn->id}: " . $e->getMessage();
            }
        }
    }


    /** @param  list<array<string, mixed>>  $meta */
    private function seedAdvanceDeposits(int $branchId, array $meta, array $masters, array &$errors, array &$counts): void
    {
        $byCategory = ['future' => [], 'in_house' => [], 'checked_out' => [], 'cancelled' => [], 'no_show' => []];

        foreach ($meta as $row) {
            $byCategory[$row['category']][] = $row['reservation_id'];
        }

        $eligible = array_merge(
            $byCategory['future'],
            $byCategory['in_house'],
            $byCategory['checked_out'],
            array_slice($byCategory['cancelled'], 0, min(8, count($byCategory['cancelled']))),
            array_slice($byCategory['no_show'], 0, min(4, count($byCategory['no_show'])))
        );

        $queue = $this->cycleToExactly($eligible, self::TARGET_DEPOSITS);
        $refundEvery = 30; // a handful come back as refunds rather than deposits, for variety

        foreach ($queue as $i => $reservationId) {
            try {
                $mode = $this->pickOrNull($masters['payModes']);
                $pay = $this->paymentLine($mode);
                $isRefund = $i > 0 && $i % $refundEvery === 0;

                AdvanceDeposit::create([
                    'branch_id' => $branchId,
                    'reservation_id' => $reservationId,
                    'deposit_date' => CarbonImmutable::today()->subDays(random_int(0, 60))->toDateString(),
                    'pay_mode_id' => $pay['pay_mode_id'],
                    'amount' => round(random_int(1000, 12000), -2),
                    'reference_no' => $pay['reference_no'],
                    'type' => $isRefund ? 'refund' : 'deposit',
                    'remark' => self::TAG,
                    'pay_type' => $pay['pay_type'],
                    'card_type' => $pay['card_type'],
                    'card_name' => $pay['card_name'],
                    'card_last4' => $pay['card_last4'],
                    'pan_no' => null,
                    'created_by' => null,
                ]);
                $counts['advance_deposits']++;
            } catch (\Throwable $e) {
                $errors[] = "advance deposit #{$i} (reservation {$reservationId}): " . $e->getMessage();
            }
        }

        foreach (array_unique($queue) as $reservationId) {
            try {
                Reservation::find($reservationId)?->refreshAdvancePaid();
            } catch (\Throwable $e) {
                $errors[] = "refreshAdvancePaid reservation {$reservationId}: " . $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bills and settlements
    |--------------------------------------------------------------------------
    */

    /**
     * @param  list<array<string, mixed>>  $meta
     */
    private function seedBillsAndSettlements(int $branchId, array $meta, array $masters, array &$errors, array &$counts): void
    {
        $billed = array_filter($meta, fn ($row) => $row['will_bill']);
        $branch = $masters['branch'];

        foreach ($billed as $i => $row) {
            try {
                $reservation = Reservation::find($row['reservation_id']);
                $checkIn = CheckIn::find($row['check_in_id']);

                if (! $reservation || ! $checkIn) {
                    continue;
                }

                $isSettled = random_int(1, 100) <= 85;
                $advance = (float) $reservation->advance_paid;
                $due = max(0, (float) $reservation->net_amount - $advance);
                $paid = $isSettled ? $due : round($due * (random_int(30, 80) / 100), 2);
                $balance = round($due - $paid, 2);

                $withGst = $row['company_id'] && $branch?->gst_state_code;

                $bill = Bill::create([
                    'branch_id' => $branchId,
                    'check_in_id' => $checkIn->id,
                    'guest_id' => $row['guest_id'],
                    'buyer_gstin' => $withGst ? Company::find($row['company_id'])?->gst_no : null,
                    'buyer_name' => $withGst ? Company::find($row['company_id'])?->name : null,
                    'buyer_address' => null,
                    'place_of_supply' => $branch?->gst_state_code,
                    'place_of_supply_name' => $branch?->gst_state_name,
                    'is_igst' => false,
                    'bill_no' => Bill::nextNumber($branchId),
                    'group_no' => null,
                    'bill_date' => $checkIn->actual_checkout_date ?? $checkIn->expected_checkout_date,
                    'room_total' => $reservation->room_total,
                    'service_total' => $reservation->service_total,
                    'discount_total' => $reservation->discount_total,
                    'advance_amount' => $advance,
                    'tax_total' => $reservation->tax_total,
                    'net_amount' => $reservation->net_amount,
                    'paid_amount' => $paid,
                    'refund_amount' => 0,
                    'balance_amount' => $balance,
                    'status' => $isSettled ? 'settled' : 'partial',
                    'billing_instruction_id' => $row['billing_instruction_id'],
                    'remark' => self::TAG,
                    'created_by' => null,
                ]);
                $counts['bills']++;

                $this->postSettlements($branchId, $bill, $checkIn, $paid, $masters['payModes'], $errors, $counts);
            } catch (\Throwable $e) {
                $errors[] = "bill #{$i} (reservation {$row['reservation_id']}): " . $e->getMessage();
            }
        }
    }

    private function postSettlements(int $branchId, Bill $bill, CheckIn $checkIn, float $paid, Collection $payModes, array &$errors, array &$counts): void
    {
        if ($paid <= 0) {
            return;
        }

        $split = random_int(1, 100) <= 15;
        $portions = $split ? [round($paid * 0.6, 2), 0] : [$paid];

        if ($split) {
            $portions[1] = round($paid - $portions[0], 2);
        }

        foreach ($portions as $amount) {
            if ($amount <= 0) {
                continue;
            }

            try {
                $pay = $this->paymentLine($this->pickOrNull($payModes));

                Settlement::create([
                    'branch_id' => $branchId,
                    'bill_id' => $bill->id,
                    'check_in_id' => $checkIn->id,
                    'settle_date' => $bill->bill_date,
                    'pay_mode_id' => $pay['pay_mode_id'],
                    'pay_type' => $pay['pay_type'],
                    'card_type' => $pay['card_type'],
                    'card_name' => $pay['card_name'],
                    'card_last4' => $pay['card_last4'],
                    'pan_no' => null,
                    'amount' => $amount,
                    'reference_no' => $pay['reference_no'],
                    'remark' => null,
                    'created_by' => null,
                ]);
                $counts['settlements']++;
            } catch (\Throwable $e) {
                $errors[] = "settlement for bill {$bill->id}: " . $e->getMessage();
            }
        }
    }


    /** @param  array<int, Guest>  $guests */
    private function recountGuests(array $guests, array &$errors): void
    {
        foreach ($guests as $guest) {
            try {
                GuestCrm::recount($guest);
            } catch (\Throwable $e) {
                $errors[] = "GuestCrm::recount guest {$guest->id}: " . $e->getMessage();
            }
        }
    }

    /** @return list<string> names of any fallback ledgers this had to create */
    private function seedVouchers(int $branchId, array $masters, array &$errors, array &$counts): array
    {
        $fallback = [];

        $cashLedger = Ledger::query()->forBranch($branchId)->ofCashType('cash')->active()->first();
        if (! $cashLedger) {
            $cashLedger = Ledger::create([
                'branch_id' => $branchId, 'account_group_id' => null, 'name' => 'Cash',
                'opening_balance' => 0, 'balance_type' => 'dr', 'cash_type' => 'cash', 'is_system' => 0, 'status' => 1,
            ]);
            $fallback[] = 'ledger: ' . $cashLedger->name . ' (cash)';
        }

        $bankLedger = Ledger::query()->forBranch($branchId)->ofCashType('bank')->active()->first();
        if (! $bankLedger) {
            $bankLedger = Ledger::create([
                'branch_id' => $branchId, 'account_group_id' => null, 'name' => 'Bank Account',
                'opening_balance' => 0, 'balance_type' => 'dr', 'cash_type' => 'bank', 'is_system' => 0, 'status' => 1,
            ]);
            $fallback[] = 'ledger: ' . $bankLedger->name . ' (bank)';
        }

        $incomeLedgers = $this->ledgersOfNature($branchId, 'income');
        if ($incomeLedgers->isEmpty()) {
            $l = Ledger::create([
                'branch_id' => $branchId, 'account_group_id' => null, 'name' => 'Room Revenue',
                'opening_balance' => 0, 'balance_type' => 'cr', 'cash_type' => 'none', 'is_system' => 0, 'status' => 1,
            ]);
            $incomeLedgers = collect([$l]);
            $fallback[] = 'ledger: ' . $l->name . ' (income)';
        }

        $expenseLedgers = $this->ledgersOfNature($branchId, 'expense');
        if ($expenseLedgers->isEmpty()) {
            $l = Ledger::create([
                'branch_id' => $branchId, 'account_group_id' => null, 'name' => 'Miscellaneous Expenses',
                'opening_balance' => 0, 'balance_type' => 'dr', 'cash_type' => 'none', 'is_system' => 0, 'status' => 1,
            ]);
            $expenseLedgers = collect([$l]);
            $fallback[] = 'ledger: ' . $l->name . ' (expense)';
        }

        try {
            $vendorLedgerIds = DB::table('vendors')->whereNotNull('ledger_id')->pluck('ledger_id');
            $vendorLedgers = $vendorLedgerIds->isEmpty()
                ? collect()
                : Ledger::query()->forBranch($branchId)->whereIn('id', $vendorLedgerIds)->active()->get();
        } catch (\Throwable $e) {
            $vendorLedgers = collect();
            $errors[] = 'vendor ledgers lookup skipped: ' . $e->getMessage();
        }

        $pool = array_merge(
            array_fill(0, 35, 'receipt'),
            array_fill(0, 35, 'payment'),
            array_fill(0, 15, 'contra'),
            array_fill(0, 25, 'journal'),
        );
        $types = $this->cycleToExactly($pool, self::TARGET_VOUCHERS);

        foreach ($types as $i => $type) {
            try {
                $date = CarbonImmutable::today()->subDays(random_int(0, 90))->toDateString();
                $lines = $this->voucherLines($type, $cashLedger, $bankLedger, $incomeLedgers, $expenseLedgers, $vendorLedgers);

                Vouchers::post([
                    'branch_id' => $branchId,
                    'voucher_type' => $type,
                    'voucher_date' => $date,
                    'narration' => self::TAG,
                    'created_by' => null,
                ], $lines);

                $counts['vouchers']++;
                $counts['voucher_entries'] += count($lines);
            } catch (\Throwable $e) {
                $errors[] = "voucher #{$i} ({$type}): " . $e->getMessage();
            }
        }

        return $fallback;
    }

    /** @return list<array<string, mixed>> */
    private function voucherLines(string $type, Ledger $cash, Ledger $bank, Collection $income, Collection $expense, Collection $vendors): array
    {
        $cashOrBank = fn () => random_int(0, 1) === 1 ? $bank : $cash;

        return match ($type) {
            'receipt' => [
                ['ledger_id' => $cashOrBank()->id, 'debit' => $amount = round(random_int(1500, 45000), -2), 'narration' => null],
                ['ledger_id' => $income->random()->id, 'credit' => $amount, 'narration' => Arr::random(self::INCOME_NARRATIONS)],
            ],
            'payment' => [
                [
                    'ledger_id' => ($vendors->isNotEmpty() && random_int(1, 100) <= 50) ? $vendors->random()->id : $expense->random()->id,
                    'debit' => $amount = round(random_int(800, 60000), -2),
                    'narration' => Arr::random(self::EXPENSE_NARRATIONS),
                ],
                ['ledger_id' => $cashOrBank()->id, 'credit' => $amount, 'narration' => null],
            ],
            'contra' => $this->contraLines($cash, $bank),
            default => [
                ['ledger_id' => $expense->random()->id, 'debit' => $amount = round(random_int(1000, 40000), -2), 'narration' => Arr::random(self::EXPENSE_NARRATIONS)],
                [
                    'ledger_id' => ($vendors->isNotEmpty() && random_int(1, 100) <= 50) ? $vendors->random()->id : $income->random()->id,
                    'credit' => $amount,
                    'narration' => null,
                ],
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    private function contraLines(Ledger $cash, Ledger $bank): array
    {
        $amount = round(random_int(5000, 200000), -2);

        return random_int(0, 1) === 1
            ? [
                ['ledger_id' => $bank->id, 'debit' => $amount, 'narration' => 'Cash deposited to bank'],
                ['ledger_id' => $cash->id, 'credit' => $amount, 'narration' => null],
            ]
            : [
                ['ledger_id' => $cash->id, 'debit' => $amount, 'narration' => 'Cash withdrawn from bank'],
                ['ledger_id' => $bank->id, 'credit' => $amount, 'narration' => null],
            ];
    }

    private function ledgersOfNature(int $branchId, string $nature): Collection
    {
        $groupIds = AccountGroup::query()->forBranch($branchId)->active()->where('nature', $nature)->pluck('id');

        if ($groupIds->isEmpty()) {
            return collect();
        }

        return Ledger::query()->forBranch($branchId)->active()->whereIn('account_group_id', $groupIds)->get();
    }

    /** @return list<string> names of any fallback POS masters this had to create */
    private function seedPosOrders(int $branchId, array $masters, Collection $rooms, array &$errors, array &$counts): array
    {
        $fallback = [];

        $outlet = Outlet::query()->forBranch($branchId)->active()->get()
            ->sortByDesc(fn (Outlet $o) => $o->kind === 'restaurant')->first();

        if (! $outlet) {
            $outlet = Outlet::create([
                'branch_id' => $branchId, 'name' => 'Restaurant', 'code' => 'REST', 'kind' => 'restaurant',
                'bill_series' => 'REST-' . $branchId . '-', 'bill_start_no' => 1, 'pos_dine_in' => 1, 'status' => 1,
            ]);
            $fallback[] = 'outlet: ' . $outlet->name;
        }

        $tableGroup = PosTableGroup::query()->where('outlet_id', $outlet->id)->first();
        if (! $tableGroup) {
            $tableGroup = PosTableGroup::create([
                'branch_id' => $branchId, 'outlet_id' => $outlet->id, 'name' => 'Main Hall', 'kind' => 'table', 'status' => 1,
            ]);
            $fallback[] = 'table group: ' . $tableGroup->name;
        }

        $tables = PosTable::query()->where('outlet_id', $outlet->id)->active()->get();
        if ($tables->count() < 4) {
            for ($i = $tables->count() + 1; $i <= 4; $i++) {
                $t = PosTable::create([
                    'branch_id' => $branchId, 'outlet_id' => $outlet->id, 'pos_table_group_id' => $tableGroup->id,
                    'name' => 'T' . $i, 'capacity' => 4, 'status' => 1,
                ]);
                $tables->push($t);
                $fallback[] = 'table: ' . $t->name;
            }
        }

        $department = PosDepartment::query()->forBranch($branchId)->active()->first();
        if (! $department) {
            $department = PosDepartment::create(['branch_id' => $branchId, 'name' => 'Kitchen', 'status' => 1]);
            $fallback[] = 'department: ' . $department->name;
        }

        $categoryIds = PosMenuCategory::query()
            ->where('outlet_id', $outlet->id)->orWhereNull('outlet_id')
            ->pluck('id');
        $menuItems = $categoryIds->isEmpty() ? collect() : PosMenuItem::query()
            ->whereIn('pos_menu_category_id', $categoryIds)->active()->get();

        if ($menuItems->isEmpty()) {
            $category = PosMenuCategory::create([
                'branch_id' => $branchId, 'outlet_id' => $outlet->id, 'name' => 'Main Menu', 'type' => 'category', 'status' => 1,
            ]);
            $fallback[] = 'menu category: ' . $category->name;

            foreach (self::FALLBACK_MENU as $name => $price) {
                $item = PosMenuItem::create([
                    'branch_id' => $branchId, 'pos_menu_category_id' => $category->id, 'pos_department_id' => $department->id,
                    'name' => $name, 'price' => $price, 'is_veg' => 1, 'status' => 1,
                ]);
                $menuItems->push($item);
                $fallback[] = 'menu item: ' . $item->name;
            }
        }

        $statusPool = array_merge(array_fill(0, 114, 'settled'), array_fill(0, 16, 'cancelled'));
        $statuses = $this->cycleToExactly($statusPool, self::TARGET_POS_ORDERS);

        $typePool = array_merge(
            array_fill(0, 91, 'dine_in'), array_fill(0, 20, 'room_service'),
            array_fill(0, 13, 'take_away'), array_fill(0, 6, 'delivery'),
        );
        $types = $this->cycleToExactly($typePool, self::TARGET_POS_ORDERS);

        foreach ($statuses as $idx => $status) {
            try {
                $this->createPosOrder(
                    $branchId, $outlet, $tables, $department, $menuItems, $rooms,
                    $masters['payModes'], $types[$idx], $status, $idx, $errors, $counts
                );
            } catch (\Throwable $e) {
                $errors[] = "pos order #{$idx}: " . $e->getMessage();
            }
        }

        return $fallback;
    }

    private function createPosOrder(
        int $branchId, Outlet $outlet, Collection $tables, PosDepartment $department, Collection $menuItems,
        Collection $rooms, Collection $payModes, string $orderType, string $status, int $idx,
        array &$errors, array &$counts
    ): void {
        $openedAt = CarbonImmutable::today()->subDays(random_int(0, 45))
            ->setTime(Arr::random([8, 9, 13, 14, 19, 20, 21]), Arr::random([0, 15, 30, 45]));
        $closedAt = $openedAt->addMinutes(random_int(20, 75));

        $isDineIn = $orderType === 'dine_in';
        $table = ($isDineIn && $tables->isNotEmpty()) ? $tables->random() : null;

        $lines = [];
        $subTotal = 0.0;
        $taxTotal = 0.0;

        foreach (range(1, random_int(1, 5)) as $n) {
            $item = $menuItems->random();
            $qty = random_int(1, 3);
            $gross = round((float) $item->price * $qty, 2);
            $split = Money::split($gross, 5.0, 'exclusive');

            $lines[] = [
                'menu_item_id' => $item->id, 'name' => $item->name, 'qty' => $qty,
                'price' => $item->price, 'tax_amount' => $split['tax'], 'amount' => $split['amount'], 'total' => $split['net'],
            ];
            $subTotal += $split['amount'];
            $taxTotal += $split['tax'];
        }

        $netAmount = round($subTotal + $taxTotal, 2);

        $order = PosOrder::create([
            'branch_id' => $branchId,
            'outlet_id' => $outlet->id,
            'order_no' => PosOrder::nextNumber($branchId),
            'order_type' => $orderType,
            'table_no' => $table?->name,
            'room_id' => $orderType === 'room_service' ? $rooms->random()->id : null,
            'check_in_id' => null,
            'guest_name' => $orderType === 'room_service' ? null : Arr::random(self::WALKIN_NAMES),
            'pax' => random_int(1, 4),
            'pos_table_id' => $table?->id,
            'pos_steward_id' => null,
            'pos_rate_plan_id' => null,
            'nc_type_id' => null,
            'nc_department_id' => null,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'is_complimentary' => 0,
            'kot_count' => 1,
            'sub_total' => round($subTotal, 2),
            'discount_percent' => 0,
            'discount_total' => 0,
            'service_charge' => 0,
            'tax_total' => round($taxTotal, 2),
            'tax_choice' => 'item',
            'round_off' => 0,
            'net_amount' => $netAmount,
            'status' => $status,
            'remark' => self::TAG,
            'created_by' => null,
        ]);
        $counts['pos_orders']++;

        foreach ($lines as $sort => $line) {
            PosOrderItem::create([
                'pos_order_id' => $order->id,
                'pos_menu_item_id' => $line['menu_item_id'],
                'pos_department_id' => $department->id,
                'kot_no' => 1,
                'fired_at' => $openedAt,
                'kitchen_status' => 'served',
                'is_nc' => 0,
                'sort' => $sort,
                'item_name' => $line['name'],
                'qty' => $line['qty'],
                'price' => $line['price'],
                'discount' => 0,
                'tax_percent' => 5,
                'tax_amount' => $line['tax_amount'],
                'amount' => $line['amount'],
                'total_amount' => $line['total'],
                'remark' => null,
            ]);
            $counts['pos_order_items']++;
        }

        if ($status !== 'settled') {
            return;
        }

        $invoice = PosInvoice::create([
            'branch_id' => $branchId,
            'outlet_id' => $outlet->id,
            'pos_order_id' => $order->id,
            'invoice_no' => PosInvoice::nextNumber($branchId, $outlet),
            'invoice_at' => $closedAt,
            'guest_name' => $order->guest_name,
            'check_in_id' => null,
            'folio_charge_id' => null,
            'folio_amount' => 0,
            'settled_at' => $closedAt,
            'sub_total' => $order->sub_total,
            'discount_total' => 0,
            'tax_total' => $order->tax_total,
            'net_amount' => $order->net_amount,
            'paid_amount' => $order->net_amount,
            'print_count' => 1,
            'status' => 'settled',
            'created_by' => null,
        ]);
        $counts['pos_invoices']++;

        $pay = $this->paymentLine($this->pickOrNull($payModes));
        PosPayment::create([
            'branch_id' => $branchId,
            'pos_invoice_id' => $invoice->id,
            'pay_mode_id' => $pay['pay_mode_id'],
            'amount' => $invoice->net_amount,
            'reference_no' => $pay['reference_no'],
            'paid_at' => $closedAt,
            'created_by' => null,
        ]);
        $counts['pos_payments']++;
    }
    /**
     * @return list<int> guest array indexes, ready for array_rand()
     */
    private function weightedGuestPool(int $guestCount): array
    {
        $pool = [];

        for ($i = 0; $i < $guestCount; $i++) {
            $pool[] = $i;

            if ($i < 30) {
                for ($k = 0; $k < 4; $k++) {
                    $pool[] = $i;
                }
            }
        }

        return $pool;
    }

    /**
     * @param  list<mixed>  $pool
     * @return list<mixed>
     */
    private function cycleToExactly(array $pool, int $target): array
    {
        if ($pool === [] || $target <= 0) {
            return [];
        }

        $out = [];

        while (count($out) < $target) {
            shuffle($pool);
            $out = array_merge($out, $pool);
        }

        return array_slice($out, 0, $target);
    }

    private function pickRoom(Collection $rooms, string $from, string $to, array &$occupancy): ?Room
    {
        foreach ($rooms->shuffle() as $room) {
            $busy = false;

            foreach ($occupancy[$room->id] ?? [] as [$bFrom, $bTo]) {
                if ($from < $bTo && $bFrom < $to) {
                    $busy = true;
                    break;
                }
            }

            if (! $busy) {
                $occupancy[$room->id][] = [$from, $to];

                return $room;
            }
        }

        return null;
    }

    private function pickOrNull(?Collection $collection)
    {
        return ($collection && $collection->isNotEmpty()) ? $collection->random() : null;
    }

    /** @return array{pay_mode_id: ?int, pay_type: string, card_type: ?string, card_name: ?string, card_last4: ?string, reference_no: ?string} */
    private function paymentLine(?PayMode $mode): array
    {
        $type = $mode?->type ?? 'cash';
        $out = ['pay_mode_id' => $mode?->id, 'card_type' => null, 'card_name' => null, 'card_last4' => null, 'reference_no' => null];

        switch ($type) {
            case 'card':
                $out['pay_type'] = random_int(0, 1) ? 'credit_card' : 'debit_card';
                $out['card_type'] = Arr::random(['visa', 'mastercard', 'rupay', 'amex']);
                $out['card_name'] = 'Card ending';
                $out['card_last4'] = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $out['reference_no'] = 'APP' . random_int(100000, 999999);
                break;
            case 'upi':
                $out['pay_type'] = 'upi';
                $out['reference_no'] = 'UPI' . random_int(100000000, 999999999);
                break;
            case 'bank':
                $out['pay_type'] = random_int(0, 1) ? 'neft_rtgs' : 'net_banking';
                $out['reference_no'] = 'TXN' . random_int(100000, 999999);
                break;
            case 'cheque':
                $out['pay_type'] = 'cheque';
                $out['reference_no'] = 'CHQ' . random_int(100000, 999999);
                break;
            default:
                $out['pay_type'] = 'cash';
        }

        return $out;
    }

    private function randomMobile(): string
    {
        $prefix = Arr::random([6, 7, 8, 9]);

        return $prefix . $this->randomDigits(9);
    }

    private function randomDigits(int $length): string
    {
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= random_int(0, 9);
        }

        return $out;
    }

    private function randomIdNumber(string $type): string
    {
        return match ($type) {
            'Passport' => chr(random_int(65, 90)) . $this->randomDigits(7),
            'Driving License' => 'DL' . $this->randomDigits(2) . '-' . $this->randomDigits(11),
            'Voter ID' => chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90)) . $this->randomDigits(7),
            default => $this->randomDigits(12),
        };
    }

    private function randomEmail(string $first, string $last, int $seq): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $first . '.' . $last));

        return trim($slug, '.') . '.' . $seq . self::EMAIL_SUFFIX;
    }
}
