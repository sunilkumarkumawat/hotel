<?php

namespace Database\Seeders;

use App\Models\HouseKeeping\HkItem;
use App\Models\Pos\Outlet;
use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\ExpenseHead;
use App\Models\Master\PayMode;
use App\Models\Master\PickDrop;
use App\Models\Master\PlanType;
use App\Models\Master\ReceiveHead;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\Master\RoomType;
use App\Models\Master\Service;
use App\Models\Master\TaxMaster;
use App\Models\Master\Vendor;
use App\Models\Master\VisitPurpose;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosNcType;
use App\Models\Pos\PosReservationSlot;
use App\Models\Pos\PosTable;
use App\Models\Pos\PosTableGroup;
use Illuminate\Database\Seeder;

/**
 * Enough master data for the New Reservation screen to be usable the moment
 * you sign in. Delete any of it from Masters once your own data is in.
 */
class MasterSeeder extends Seeder
{
    public function run(int $branchId = 1): void
    {
        /* Small lists ------------------------------------------------------ */

        $this->fill(BusinessMarket::class, $branchId, [
            'Corporate', 'Leisure', 'Government', 'OTA', 'Walk In', 'Wedding',
        ]);

        $this->fill(VisitPurpose::class, $branchId, [
            'Business', 'Holiday', 'Wedding', 'Conference', 'Medical', 'Transit',
        ]);

        $this->fill(BillingInstruction::class, $branchId, [
            'Guest pays all', 'Company pays room only', 'Company pays all', 'Room and tax only',
        ]);

        $this->fill(ExpenseHead::class, $branchId, [
            'Fuel', 'Stationery', 'Repairs & Maintenance', 'Staff Welfare', 'Conveyance', 'Miscellaneous',
        ]);

        $this->fill(ReceiveHead::class, $branchId, [
            'Scrap Sale', 'Deposit Returned', 'Miscellaneous Income',
        ]);

        foreach ([
            ['Airport Pickup', 800],
            ['Airport Drop', 800],
            ['Railway Station Pickup', 350],
            ['Railway Station Drop', 350],
            ['Both Ways', 1500],
        ] as $sort => [$name, $charge]) {
            PickDrop::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['charge' => $charge, 'status' => 1]
            );
        }

        foreach ([
            ['Cash', 'cash'], ['UPI', 'upi'], ['Credit Card', 'card'],
            ['Debit Card', 'card'], ['Bank Transfer', 'bank'], ['Cheque', 'cheque'],
        ] as [$name, $type]) {
            PayMode::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['type' => $type, 'status' => 1]
            );
        }

        foreach ([
            ['Walk In', 0], ['Front Desk', 0], ['Website', 0],
            ['MakeMyTrip', 15], ['Booking.com', 15], ['Goibibo', 12], ['Travel Agent', 10],
        ] as [$name, $commission]) {
            BookedBy::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['commission_percent' => $commission, 'status' => 1]
            );
        }

        /* Tax --------------------------------------------------------------- */

        foreach ([
            ['GST 0%', 0, 0], ['GST 5%', 5, 0], ['GST 12%', 12, 1], ['GST 18%', 18, 0], ['GST 28%', 28, 0],
        ] as [$name, $percent, $default]) {
            TaxMaster::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['percent' => $percent, 'is_default' => $default, 'status' => 1]
            );
        }

        /* Plans ------------------------------------------------------------- */

        foreach ([
            ['European Plan', 'EP', 0, 'Room only'],
            ['Continental Plan', 'CP', 400, 'Room + breakfast'],
            ['Modified American Plan', 'MAP', 900, 'Room + breakfast + one meal'],
            ['American Plan', 'AP', 1400, 'Room + all meals'],
        ] as [$name, $code, $charge, $remark]) {
            PlanType::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['code' => $code, 'charge' => $charge, 'remark' => $remark, 'status' => 1]
            );
        }

        /* Rooms ------------------------------------------------------------- */

        $categories = [];

        foreach ([['Standard', 'STD'], ['Deluxe', 'DLX'], ['Suite', 'STE'], ['Cottage', 'CTG']] as $sort => [$name, $code]) {
            $categories[$name] = RoomCategory::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['code' => $code, 'sort' => $sort + 1, 'status' => 1]
            );
        }

        $types = [];

        foreach ([
            ['Standard Single', 'Standard', 1800, 1, 1],
            ['Standard Double', 'Standard', 2400, 2, 1],
            ['Deluxe Double', 'Deluxe', 3600, 2, 2],
            ['Deluxe Triple', 'Deluxe', 4500, 3, 1],
            ['Executive Suite', 'Suite', 7800, 2, 2],
            ['Family Cottage', 'Cottage', 9500, 4, 2],
        ] as [$name, $category, $rent, $adults, $children]) {
            $types[$name] = RoomType::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                [
                    'room_category_id' => $categories[$category]->id,
                    'base_rent' => $rent,
                    'max_adult' => $adults,
                    'max_child' => $children,
                    'status' => 1,
                ]
            );
        }

        // 101–106 standard, 201–206 deluxe, 301–302 suite, C1–C2 cottage.
        $rooms = [];

        foreach (range(101, 103) as $no) {
            $rooms[] = [$no, 'Standard Single', 'First'];
        }
        foreach (range(104, 106) as $no) {
            $rooms[] = [$no, 'Standard Double', 'First'];
        }
        foreach (range(201, 204) as $no) {
            $rooms[] = [$no, 'Deluxe Double', 'Second'];
        }
        foreach (range(205, 206) as $no) {
            $rooms[] = [$no, 'Deluxe Triple', 'Second'];
        }
        foreach (range(301, 302) as $no) {
            $rooms[] = [$no, 'Executive Suite', 'Third'];
        }
        foreach (['C1', 'C2'] as $no) {
            $rooms[] = [$no, 'Family Cottage', 'Ground'];
        }

        foreach ($rooms as [$number, $typeName, $floor]) {
            $type = $types[$typeName];

            Room::firstOrCreate(
                ['room_no' => (string) $number, 'branch_id' => $branchId],
                [
                    'room_category_id' => $type->room_category_id,
                    'room_type_id' => $type->id,
                    'floor' => $floor,
                    'base_rent' => $type->base_rent,
                    'max_pax' => $type->max_adult + $type->max_child,
                    'housekeeping_status' => 'clean',
                    'status' => 1,
                ]
            );
        }

        /* Services ---------------------------------------------------------- */

        $gst18 = TaxMaster::where('branch_id', $branchId)->where('percent', 18)->value('id');
        $gst5 = TaxMaster::where('branch_id', $branchId)->where('percent', 5)->value('id');

        foreach ([
            ['Extra Bed', 'EXB', 800, $gst18],
            ['Laundry', 'LND', 250, $gst18],
            ['Airport Transfer', 'ATR', 1200, $gst5],
            ['Breakfast (extra pax)', 'BFT', 350, $gst5],
            ['Late Checkout', 'LCO', 1000, $gst18],
            ['Conference Hall (per hour)', 'CNF', 2500, $gst18],
        ] as [$name, $code, $price, $taxId]) {
            Service::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['code' => $code, 'price' => $price, 'tax_master_id' => $taxId, 'status' => 1]
            );
        }

        /* Linen and the laundry ---------------------------------------------
         *
         * Enough for the House Keeping issue screen to be usable the moment
         * the app is installed. Rates are the per-piece laundry contract; a
         * real hotel edits them from the issue screen's "New item" popup.
         */

        foreach ([
            ['Bed Sheet', 'pcs', 12, 20],
            ['Pillow Cover', 'pcs', 5, 9],
            ['Bath Towel', 'pcs', 10, 16],
            ['Hand Towel', 'pcs', 6, 10],
            ['Face Towel', 'pcs', 4, 7],
            ['Bath Mat', 'pcs', 8, 14],
            ['Duvet Cover', 'pcs', 25, 40],
            ['Table Cloth', 'pcs', 15, 25],
            ['Curtain', 'pcs', 60, 95],
            ['Staff Uniform', 'set', 30, 50],
        ] as [$name, $unit, $std, $exp]) {
            HkItem::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['unit' => $unit, 'std_rate' => $std, 'exp_rate' => $exp, 'status' => 1]
            );
        }

        foreach ([
            ['Shree Laundry Service', '9876500021'],
            ['Pali Dry Cleaners', '9876500022'],
        ] as [$name, $mobile]) {
            Vendor::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['mobile' => $mobile, 'status' => 1]
            );
        }

        /* Point of Sale outlets ----------------------------------------------
         *
         * Setup data, not sales: the POS Dashboard needs to know which tills
         * exist before anything has rung through them, so its legend and its
         * outlet chart have real names from the first day.
         */

        foreach ([
            ['Restaurant', 'RST', 'restaurant', ['pos_dine_in' => 1, 'pos_take_away' => 1]],
            ['Room Service', 'RMS', 'room_service', ['pos_dine_in' => 0, 'pos_room_service' => 1]],
            ['Bar', 'BAR', 'bar', ['pos_dine_in' => 1, 'split_liquor_bill' => 1]],
            ['Banquet', 'BQT', 'banquet', ['pos_dine_in' => 1]],
        ] as [$name, $code, $kind, $flags]) {
            Outlet::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                array_merge([
                    'code' => $code,
                    'kind' => $kind,
                    'status' => 1,
                    'bill_series' => $code,
                    'bill_start_no' => 1,
                    'page_width' => 80,
                    'print_margin' => 6,
                    'header_font' => 'Arial',
                    'header_font_bold' => 1,
                    'guest_signature_print' => 1,
                ], $flags)
            );
        }

        /* The small POS lists ------------------------------------------------
         *
         * Named the way an Indian hotel names them, so the first time somebody
         * opens Setup there is something recognisable to edit rather than eight
         * empty screens.
         */

        $this->fill(PosDepartment::class, $branchId, ['Kitchen', 'Bar', 'Room Service', 'Bakery']);

        foreach ([
            ['Complimentary', 0],
            ['In-House', 1],
            ['Promotional', 0],
            ['Staff Meal', 1],
        ] as [$name, $needsDepartment]) {
            PosNcType::firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['requires_department' => $needsDepartment, 'status' => 1]
            );
        }

        $this->fill(PosMenuCategory::class, $branchId, [
            'Starters', 'Main Course', 'Indian Breads', 'Beverages', 'Desserts',
        ]);

        $restaurant = Outlet::query()->where('branch_id', $branchId)->where('name', 'Restaurant')->first();

        if ($restaurant) {
            $group = PosTableGroup::firstOrCreate(
                ['name' => 'Non AC', 'outlet_id' => $restaurant->id],
                ['branch_id' => $branchId, 'kind' => 'table', 'status' => 1]
            );

            foreach ([['1', 4], ['2', 4], ['3', 6], ['4', 2]] as [$number, $seats]) {
                PosTable::firstOrCreate(
                    ['name' => $number, 'pos_table_group_id' => $group->id],
                    [
                        'branch_id' => $branchId,
                        'outlet_id' => $restaurant->id,
                        'capacity' => $seats,
                        'status' => 1,
                    ]
                );
            }

            // Lunch and dinner, the two sittings every restaurant takes.
            foreach ([['12:30:00', 5], ['13:30:00', 5], ['19:30:00', 8], ['20:30:00', 8]] as [$time, $max]) {
                PosReservationSlot::firstOrCreate(
                    ['branch_id' => $branchId, 'outlet_id' => $restaurant->id, 'slot_time' => $time],
                    ['max_booking' => $max, 'status' => 1]
                );
            }
        }
    }

    /** Create a list of name-only rows if they are not already there. */
    private function fill(string $model, int $branchId, array $names): void
    {
        foreach ($names as $name) {
            $model::firstOrCreate(['name' => $name, 'branch_id' => $branchId], ['status' => 1]);
        }
    }
}
