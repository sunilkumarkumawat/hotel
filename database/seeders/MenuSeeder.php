<?php

namespace Database\Seeders;

use App\Models\Common\Module;
use App\Models\Common\SubModule;
use Illuminate\Database\Seeder;

/**
 * The whole sidebar.
 *
 * Names and order match the live Tosskey PMS screen for screen. A sub-module's
 * `url` is both its link and its permission key, so adding a row here adds it
 * to the sidebar AND to the permission matrix on the Users screen.
 *
 * Screens whose routes are not written yet still belong here — the sidebar
 * shows them with a "Soon" pill until a route with that path exists, and the
 * pill disappears on its own the moment one does.
 */
class MenuSeeder extends Seeder
{
    /** [module name, icon, [ [sub-module name, url], ... ]] */
    public const MENU = [
        ['Dashboard', 'home', [
            ['Dashboard', '/'],
        ]],

        ['Reservation', 'calendar', [
            ['New Reservation', 'reservation/new-reservation'],
            ['Reservation Booking Details', 'reservation/booking-details'],
            ['Reservation Status View', 'reservation/status-view'],
            ['Reservation Calendar New', 'reservation/calendar-new'],
            ['Cancel Reservation List', 'reservation/cancel-list'],
            ['Reservation Calendar', 'reservation/calendar'],
            ['Advanced Deposit', 'reservation/advance-deposit'],
            ['Reservation Calendar Monthly', 'reservation/calendar-monthly'],
            ['Return / Paidup', 'reservation/return-paidup'],
            ['No Show Room Report', 'reservation/no-show-report'],
            ['Booking Sheet Accounts', 'reservation/booking-sheet-accounts'],
        ]],

        ['Front Office', 'desktop', [
            ['Pre Reg Card', 'front-office/pre-reg-card'],
            ['Check In Guest', 'front-office/check-in-guest'],
            ['Direct Check In Guest', 'front-office/direct-check-in-guest'],
            ['Check In Guest Details', 'front-office/check-in-guest-details'],
            ['Pax Checkin', 'front-office/pax-checkin'],
            ['Cancel Booking Details', 'front-office/cancel-booking-details'],
            ['Room Calendar', 'front-office/room-calendar'],
            ['Linked/UnLinked Report', 'front-office/linked-unlinked-report'],
            ['Check Out Guest', 'front-office/check-out-guest'],
            ['Calendar', 'front-office/calendar'],
            ['Guest Checkout Date Extend', 'front-office/guest-checkout-date-extend'],
            ['Paidup (Refund) Amount', 'front-office/paidup-refund-amount'],
            ['Post Room/Mic Charges', 'front-office/post-room-mic-charges'],
            ['Check Out Details', 'front-office/check-out-details'],
            ['Advance Deposit/Room-Transfer', 'front-office/advance-deposit-room-transfer'],
            ['Booking Linked/Unlinked', 'front-office/booking-linked-unlinked'],
            ['Settlement', 'front-office/settlement'],
            ['Room Wise Services', 'front-office/room-wise-services'],
            ['Customer Details', 'front-office/customer-details'],

            /*
             * Night Audit is ours rather than Tosskey's, so it goes on the end
             * of the list where it cannot push any existing sub-module's id
             * around — and with it, nobody's permissions.
             */
            ['Night Audit', 'front-office/night-audit'],
        ]],

        ['House Keeping', 'layers', [
            /*
             * The board comes first because it is what the floor looks at all
             * day; the Status list below it is the supervisor's paperwork.
             * Adding a row here only changes the sidebar's ORDER — every
             * sub-module keeps the id it already has, so no permission moves.
             */
            ['Housekeeping Board', 'house-keeping/board'],
            ['House Keeping Status', 'house-keeping/status'],
            ['Issue', 'house-keeping/issue'],
            ['Received', 'house-keeping/received'],
            ['Room Blocked', 'house-keeping/room-blocked'],
            ['Work Order', 'house-keeping/work-order'],
            ['Lost And Found Detail', 'house-keeping/lost-and-found-detail'],
        ]],

        ['Petty Cash', 'wallet', [
            ['Payment Expense', 'petty-cash/payment-expense'],
            ['Receipt', 'petty-cash/receipt'],
            ['Expense Summary Report', 'petty-cash/expense-summary-report'],
            ['Expense Head Master', 'masters/expense-head'],
            ['Receive Head Master', 'masters/receive-head'],
            ['Payment Approval', 'petty-cash/payment-approval'],
        ]],

        ['Accounting', 'file', [
            ['Ledger', 'accounting/ledger'],
            ['Group', 'accounting/group'],
            ['Vendor Payment', 'accounting/vendor-payment'],
            ['Customer Receipt', 'accounting/customer-receipt'],
            ['Day Book', 'accounting/day-book'],
            ['Cash Book', 'accounting/cash-book'],
            ['Bank Book', 'accounting/bank-book'],
            ['Payment Voucher', 'accounting/payment-voucher'],
            ['Receipt Voucher', 'accounting/receipt-voucher'],
            ['Contra Voucher', 'accounting/contra-voucher'],
            ['Ledger Statement', 'accounting/ledger-statement'],
            ['Journal', 'accounting/journal'],
            ['Trial Balance', 'accounting/trial-balance'],
            ['Profit & Loss', 'accounting/profit-loss'],
            ['Upload E-Invoice', 'accounting/upload-e-invoice'],
            ['Show E-Invoice', 'accounting/show-e-invoice'],
            ['All Receipt', 'accounting/all-receipt'],
        ]],

        // Not in the old sidebar, but a reservation cannot be made without it.
        ['Masters', 'cog', [
            ['All Masters', 'masters'],
            ['Room Category', 'masters/room-category'],
            ['Room Type', 'masters/room-type'],
            ['Plan Type', 'masters/plan-type'],
            ['Room', 'masters/room'],
            ['Tax', 'masters/tax'],
            ['Service', 'masters/service'],
            ['Company', 'masters/company'],
            ['Booked By', 'masters/booked-by'],
            ['Business Market', 'masters/business-market'],
            ['Visit Purpose', 'masters/visit-purpose'],
            ['Pick and Drop', 'masters/pick-drop'],
            ['Billing Instruction', 'masters/billing-instruction'],
            ['Pay Mode', 'masters/pay-mode'],
        ]],

        ['Administration', 'shield', [
            ['Users', 'users'],
            ['Role', 'role'],
            ['Branch', 'viewBranch'],
            ['Modules', 'modules'],
            // Who hears about what, on which channel — and where the mail
            // password and the WhatsApp key go.
            ['Notification Settings', 'notification-settings'],
        ]],

        /*
         * Point Of Sale — the restaurant, room service and the bar. Added at
         * the end on purpose: every module and sub-module above keeps the id it
         * already has, so an existing database picks POS up by re-seeding the
         * menu without a single permission moving. Move this block up the list
         * if you would rather it sat with the operations modules — the sidebar
         * follows the order here.
         *
         * Only the dashboard is built; the rest carry a Soon pill until their
         * routes exist, the same as everywhere else in the menu.
         */
        ['Point Of Sale', 'bag', [
            ['POS Dashboard', 'point-of-sale/dashboard'],
            ['POS', 'point-of-sale/pos'],
            ['Table Reservations', 'point-of-sale/table-reservations'],
            ['Kitchen Display System', 'point-of-sale/kitchen-display'],
            ['Room Service Orders', 'point-of-sale/room-service-orders'],
            ['POS Reports', 'point-of-sale/reports'],

            /*
             * Setup and the nine screens under it. The old sidebar nests these
             * inside a Setup fly-out; this menu is two levels deep, so Setup is
             * an index page that links to all nine and they also sit in the
             * sidebar directly under it. Each one carries its own permission
             * key, which is the point: a manager can be given Stewards without
             * being given the bill series and the GST number.
             */
            ['Setup', 'point-of-sale/setup'],
            ['Outlets', 'point-of-sale/setup/outlets'],
            ['Tables', 'point-of-sale/setup/tables'],
            ['Item Category', 'point-of-sale/setup/item-category'],
            ['Items', 'point-of-sale/setup/items'],
            ['Rate Plan', 'point-of-sale/setup/rate-plan'],
            ['Department', 'point-of-sale/setup/department'],
            ['KOT Printing Setup', 'point-of-sale/setup/kot-printing'],
            ['Slots', 'point-of-sale/setup/slots'],
            ['Stewards', 'point-of-sale/setup/stewards'],
            ['NC Types', 'point-of-sale/setup/nc-types'],
            ['Modifier Groups', 'point-of-sale/setup/modifier-groups'],
            ['Modifiers', 'point-of-sale/setup/modifiers'],
        ]],

        /*
         * Everything below is new, and it is at the end for the same reason
         * Point Of Sale is: every module and sub-module above keeps the id it
         * already has, so an existing database picks these up by re-seeding
         * the menu without a single permission moving.
         *
         * Reports is one permission key covering all eighteen reports — see
         * config/reports.php. A hotel that trusts somebody with the occupancy
         * report trusts them with the arrivals list.
         */
        ['Reports', 'chart', [
            ['Reports', 'reports'],
        ]],

        ['Pool', 'globe', [
            ['Pool Bookings', 'pool/bookings'],
            ['Pool Calendar', 'pool/calendar'],
            ['Pools', 'pool/setup'],
        ]],

        ['Banquet Hall', 'grid', [
            ['Hall Bookings', 'hall/bookings'],
            ['Hall Calendar', 'hall/calendar'],
            ['Halls', 'hall/setup'],
        ]],

        ['Car & Parking', 'package', [
            ['Parking', 'car/parking'],
            ['Pickup & Drop', 'car/trips'],
            ['Parking Slots', 'car/parking-slots'],
            ['Vehicles', 'car/vehicles'],
        ]],

        /*
         * Rate Management goes on the end rather than beside Reservation,
         * where it belongs by subject. A module keeps whatever `sort` it was
         * first given, so slotting a new one into the middle would leave two
         * modules claiming the same position and the sidebar picking between
         * them at random. The order can be changed afterwards, by hand, on
         * Administration -> Modules.
         */
        ['Rate Management', 'trending-up', [
            ['Rate Calendar', 'rates/calendar'],
            ['Rate Plans', 'rates/plans'],
            ['Rate Grid', 'rates/rules'],
            ['Seasons', 'rates/seasons'],
        ]],

        /*
         * Compliance is its own module rather than a corner of Reports,
         * because these are not reports: two of them are documents somebody
         * signs, and the register is the one screen in the system that can
         * show a whole ID number. A module of its own is also a permission
         * of its own, which is the point.
         */
        ['Compliance', 'shield', [
            ['Police Register', 'compliance/police-register'],
            ['Form C', 'compliance/form-c'],
            ['GST Returns', 'compliance/gst-returns'],
            ['Tally Export', 'compliance/tally-export'],
        ]],

        /*
         * Guest CRM is about the PERSON. Masters -> Customer Details is the
         * same table seen as a list to keep tidy; this is the same table seen
         * as somebody the hotel is trying to get back. Two screens, two jobs,
         * and deliberately two permissions — a clerk who may fix a typo in a
         * phone number should not automatically be able to blacklist anybody.
         */
        ['Guest CRM', 'star', [
            ['Guests', 'crm/guests'],
            ['Feedback', 'crm/feedback'],
            ['Birthdays', 'crm/occasions'],
        ]],

        /*
         * The store's permissions are deliberately split fine. Ordering,
         * receiving, issuing and writing stock off are four different levels
         * of trust — the storekeeper who hands out rice all day is not the
         * person who should be able to write a sack of it off the books.
         */
        ['Store', 'package', [
            ['Stock', 'store/stock'],
            ['Items', 'store/items'],
            ['Purchase Orders', 'store/purchase-orders'],
            ['Goods Receipts', 'store/grn'],
            ['Issues', 'store/issues'],
            ['Wastage', 'store/wastage'],
            ['Stock Adjustments', 'store/adjustments'],
            ['Stock Transfer', 'store/transfer'],
            ['Recipes', 'store/recipes'],
            ['Store Categories', 'store/categories'],
        ]],

        /*
         * Two screens, two permissions, and the split is the point. `My Shift`
         * is every cashier's own drawer and is what they are given; `Shift
         * Reports` is everybody's, and is a manager's screen. A cashier who
         * can see their own close does not thereby get to see how the person
         * before them counted.
         *
         * The two audit screens are view-only for everybody. There is no add,
         * edit or delete route behind them and there is not going to be one.
         */
        ['Shift & Audit', 'shield', [
            ['My Shift', 'shift/my-shift'],
            ['Shift Reports', 'shift/reports'],
            ['Audit Trail', 'audit/trail'],
            ['Sign-in History', 'audit/logins'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::MENU as $sort => [$name, $icon, $submodules]) {
            $module = Module::firstOrNew(['name' => $name]);

            $module->fill([
                // Never overwrite an icon or order the user changed by hand.
                'icon' => $module->icon ?: $icon,
                'sort' => $module->exists ? $module->sort : $sort + 1,
            ])->save();

            foreach ($submodules as $childSort => [$childName, $childUrl]) {
                SubModule::updateOrCreate(
                    ['url' => $childUrl],
                    [
                        'module_id' => $module->id,
                        'name' => $childName,
                        'sort' => $childSort + 1,
                    ]
                );
            }
        }
    }
}
