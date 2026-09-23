<?php

use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
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

/*
|--------------------------------------------------------------------------
| Masters
|--------------------------------------------------------------------------
| Every entry here becomes a working list + add + edit + delete screen at
| /masters/<key>, rendered by one controller (MasterController) and one set of
| views. Adding a master is adding an entry here — no new controller, no new
| blade file.
|
| Each field is:  'column' => [label, type, rules, and type-specific extras]
|
| Types: text | number | money | percent | select | textarea | switch
| A `select` takes either `options` (a plain array) or `source` (a model class
| whose active rows are listed by `name`).
|
| `columns` decides what the list table shows; anything not listed still lives
| on the form.
*/

return [

    'room-category' => [
        'model' => RoomCategory::class,
        'label' => 'Room Category',
        'plural' => 'Room Categories',
        'icon' => 'grid',
        'intro' => 'The broad class of room — Deluxe, Suite, Cottage. Room types sit inside a category.',
        'columns' => ['name', 'code', 'sort'],
        'fields' => [
            'name' => ['label' => 'Category name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Deluxe'],
            'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'placeholder' => 'DLX'],
            'sort' => ['label' => 'Sort', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:127', 'help' => 'Lower shows first.'],
        ],
    ],

    'room-type' => [
        'model' => RoomType::class,
        'label' => 'Room Type',
        'plural' => 'Room Types',
        'icon' => 'layers',
        'intro' => 'Single, Double, Triple. The base rent here is what a new reservation starts from.',
        'columns' => ['name', 'room_category_id', 'base_rent', 'max_adult', 'max_child'],
        'with' => ['category'],
        'fields' => [
            'name' => ['label' => 'Type name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Double'],
            'room_category_id' => ['label' => 'Category', 'type' => 'select', 'source' => RoomCategory::class, 'rules' => 'nullable|integer'],
            'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
            'base_rent' => ['label' => 'Base rent', 'type' => 'money', 'rules' => 'nullable|numeric|min:0', 'help' => 'Per room, per night.'],
            'max_adult' => ['label' => 'Max adults', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:20'],
            'max_child' => ['label' => 'Max children', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:20'],
        ],
    ],

    'plan-type' => [
        'model' => PlanType::class,
        'label' => 'Plan Type',
        'plural' => 'Plan Types',
        'icon' => 'inbox',
        'intro' => 'EP, CP, MAP, AP. The charge is added to the room rent for every room-night.',
        'columns' => ['name', 'code', 'charge'],
        'fields' => [
            'name' => ['label' => 'Plan name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Continental Plan'],
            'code' => ['label' => 'Short code', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'placeholder' => 'CP'],
            'charge' => ['label' => 'Charge per night', 'type' => 'money', 'rules' => 'nullable|numeric|min:0', 'help' => 'Added on top of the room rent.'],
            'remark' => ['label' => 'What it includes', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'placeholder' => 'Room + breakfast'],
        ],
    ],

    'room' => [
        'model' => Room::class,
        'label' => 'Room',
        'plural' => 'Rooms',
        'icon' => 'home',
        'intro' => 'The physical rooms. A room can only be allotted to one stay at a time.',
        'columns' => ['room_no', 'room_type_id', 'floor', 'base_rent', 'housekeeping_status'],
        'with' => ['type'],
        'order' => 'room_no',
        'search' => 'room_no',
        'fields' => [
            'room_no' => ['label' => 'Room no.', 'type' => 'text', 'rules' => 'required|string|max:30', 'placeholder' => '101'],
            'floor' => ['label' => 'Floor', 'type' => 'text', 'rules' => 'nullable|string|max:30', 'placeholder' => 'Ground'],
            'room_category_id' => ['label' => 'Category', 'type' => 'select', 'source' => RoomCategory::class, 'rules' => 'nullable|integer'],
            'room_type_id' => ['label' => 'Room type', 'type' => 'select', 'source' => RoomType::class, 'rules' => 'required|integer'],
            'base_rent' => ['label' => 'Rent', 'type' => 'money', 'rules' => 'nullable|numeric|min:0', 'help' => 'Leave 0 to use the room type rent.'],
            'max_pax' => ['label' => 'Max guests', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:20'],
            'housekeeping_status' => ['label' => 'Housekeeping', 'type' => 'select', 'options' => Room::HOUSEKEEPING, 'rules' => 'nullable|in:clean,dirty,inspected,out_of_order'],
        ],
    ],

    'tax' => [
        'model' => TaxMaster::class,
        'label' => 'Tax',
        'plural' => 'Taxes',
        'icon' => 'credit-card',
        'intro' => 'GST slabs. Mark one as default and new rooms and services pick it up.',
        'columns' => ['name', 'percent', 'is_default'],
        'fields' => [
            'name' => ['label' => 'Tax name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'GST 12%'],
            'percent' => ['label' => 'Percent', 'type' => 'percent', 'rules' => 'required|numeric|min:0|max:100'],
            'is_default' => ['label' => 'Use as the default tax', 'type' => 'switch', 'rules' => 'nullable|boolean'],
        ],
    ],

    'service' => [
        'model' => Service::class,
        'label' => 'Service',
        'plural' => 'Services',
        'icon' => 'bag',
        'intro' => 'Anything charged on top of the room — laundry, airport pickup, extra bed.',
        'columns' => ['name', 'code', 'price', 'tax_master_id'],
        'with' => ['tax'],
        'fields' => [
            'name' => ['label' => 'Service name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Laundry'],
            'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:30'],
            'price' => ['label' => 'Price', 'type' => 'money', 'rules' => 'nullable|numeric|min:0'],
            'tax_master_id' => ['label' => 'Tax', 'type' => 'select', 'source' => TaxMaster::class, 'rules' => 'nullable|integer'],
        ],
    ],

    'company' => [
        'model' => Company::class,
        'label' => 'Company',
        'plural' => 'Companies',
        'icon' => 'package',
        'intro' => 'Corporate accounts. Picking one on a reservation fills the GST number in.',
        'columns' => ['name', 'gst_no', 'mobile', 'credit_limit'],
        'fields' => [
            'name' => ['label' => 'Company name', 'type' => 'text', 'rules' => 'required|string|max:255'],
            'gst_no' => ['label' => 'GST no.', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'placeholder' => '08AAACX1234C1ZK'],
            'contact_person' => ['label' => 'Contact person', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
            'mobile' => ['label' => 'Mobile', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
            'email' => ['label' => 'Email', 'type' => 'text', 'rules' => 'nullable|email|max:255'],
            'credit_limit' => ['label' => 'Credit limit', 'type' => 'money', 'rules' => 'nullable|numeric|min:0'],
            'address' => ['label' => 'Address', 'type' => 'textarea', 'rules' => 'nullable|string|max:255', 'wide' => true],
        ],
    ],

    // The same table Purchase Orders and Goods Receipts pick a supplier
    // from, and the one House Keeping → Issue's quick-add already writes
    // to — this is the list + edit screen that table never had.
    'vendor' => [
        'model' => Vendor::class,
        'label' => 'Vendor',
        'plural' => 'Vendors',
        'icon' => 'package',
        'intro' => 'Who the store buys from. Used on Purchase Orders, Goods Receipts, and the laundry issue note.',
        'columns' => ['name', 'mobile', 'gst_no'],
        'fields' => [
            'name' => ['label' => 'Vendor name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Fresh Farms Suppliers'],
            'mobile' => ['label' => 'Mobile', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
            'email' => ['label' => 'Email', 'type' => 'text', 'rules' => 'nullable|email|max:255'],
            'gst_no' => ['label' => 'GST no.', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'placeholder' => '08AAACX1234C1ZK'],
            'address' => ['label' => 'Address', 'type' => 'textarea', 'rules' => 'nullable|string|max:255', 'wide' => true],
            'remark' => ['label' => 'Remark', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
        ],
    ],

    'booked-by' => [
        'model' => BookedBy::class,
        'label' => 'Booked By',
        'plural' => 'Booked By',
        'icon' => 'user',
        'intro' => 'Who brought the booking in — travel agent, OTA, walk-in, or a staff member.',
        'columns' => ['name', 'mobile', 'commission_percent'],
        'fields' => [
            'name' => ['label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'MakeMyTrip'],
            'mobile' => ['label' => 'Mobile', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
            'email' => ['label' => 'Email', 'type' => 'text', 'rules' => 'nullable|email|max:255'],
            'commission_percent' => ['label' => 'Commission', 'type' => 'percent', 'rules' => 'nullable|numeric|min:0|max:100'],
        ],
    ],

    'business-market' => [
        'model' => BusinessMarket::class,
        'label' => 'Business Market',
        'plural' => 'Business Markets',
        'icon' => 'trending-up',
        'intro' => 'The segment a booking belongs to — Corporate, Leisure, Government, OTA.',
        'columns' => ['name'],
        'fields' => [
            'name' => ['label' => 'Market name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Corporate'],
            'remark' => ['label' => 'Remark', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
        ],
    ],

    'visit-purpose' => [
        'model' => VisitPurpose::class,
        'label' => 'Visit Purpose',
        'plural' => 'Visit Purposes',
        'icon' => 'star',
        'intro' => 'Why the guest is here — Business, Holiday, Wedding, Medical.',
        'columns' => ['name'],
        'fields' => [
            'name' => ['label' => 'Purpose', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Business'],
        ],
    ],

    'pick-drop' => [
        'model' => PickDrop::class,
        'label' => 'Pick and Drop',
        'plural' => 'Pick and Drop',
        'icon' => 'refresh',
        'intro' => 'Transfer options offered to the guest, with what each one costs.',
        'columns' => ['name', 'charge'],
        'fields' => [
            'name' => ['label' => 'Facility', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Airport pickup'],
            'charge' => ['label' => 'Charge', 'type' => 'money', 'rules' => 'nullable|numeric|min:0'],
        ],
    ],

    'billing-instruction' => [
        'model' => BillingInstruction::class,
        'label' => 'Billing Instruction',
        'plural' => 'Billing Instructions',
        'icon' => 'file',
        'intro' => 'How the folio should be settled — Guest pays, Company pays, Room only.',
        'columns' => ['name'],
        'fields' => [
            'name' => ['label' => 'Instruction', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Company pays room only'],
        ],
    ],

    'pay-mode' => [
        'model' => PayMode::class,
        'label' => 'Pay Mode',
        'plural' => 'Pay Modes',
        'icon' => 'wallet',
        'intro' => 'How money is taken — Cash, Card, UPI, Bank transfer, Cheque.',
        'columns' => ['name', 'type'],
        'fields' => [
            'name' => ['label' => 'Pay mode', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'UPI'],
            'type' => ['label' => 'Kind', 'type' => 'select', 'rules' => 'required|in:cash,bank,card,upi,cheque,other', 'options' => [
                'cash' => 'Cash', 'bank' => 'Bank', 'card' => 'Card', 'upi' => 'UPI', 'cheque' => 'Cheque', 'other' => 'Other',
            ]],
        ],
    ],

    'expense-head' => [
        'model' => ExpenseHead::class,
        'label' => 'Expense Head',
        'plural' => 'Expense Heads',
        'icon' => 'activity',
        'intro' => 'What petty cash goes out for — Fuel, Stationery, Repairs.',
        'columns' => ['name'],
        'fields' => [
            'name' => ['label' => 'Head', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Fuel'],
        ],
    ],

    'receive-head' => [
        'model' => ReceiveHead::class,
        'label' => 'Receive Head',
        'plural' => 'Receive Heads',
        'icon' => 'activity',
        'intro' => 'What petty cash comes in against — Scrap sale, Deposit returned.',
        'columns' => ['name'],
        'fields' => [
            'name' => ['label' => 'Head', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'Scrap sale'],
        ],
    ],
];
