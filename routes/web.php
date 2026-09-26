<?php

use App\Http\Controllers\AdvanceDepositController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Audit\ShiftController;
use App\Http\Controllers\Audit\TrailController;
use App\Http\Controllers\Authenticate\LoginController;
use App\Http\Controllers\Authenticate\RoleController;
use App\Http\Controllers\Authenticate\UserController;
use App\Http\Controllers\BookingCalendarController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\Crm\FeedbackController;
use App\Http\Controllers\Crm\GuestController as CrmGuestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevSeedCleanupController;
use App\Http\Controllers\DevSeedRestoreController;
use App\Http\Controllers\GuestFeedbackFormController;
use App\Http\Controllers\GuestOrderController;
use App\Http\Controllers\Store\DocController;
use App\Http\Controllers\Store\ItemController as StoreItemController;
use App\Http\Controllers\Store\RecipeController;
use App\Http\Controllers\Store\StockController;
use App\Http\Controllers\Store\TransferController;
use App\Http\Controllers\GroupBillController;
use App\Http\Controllers\GuestDocumentController;
use App\Http\Controllers\HkIssueController;
use App\Http\Controllers\HkReceivedController;
use App\Http\Controllers\HouseKeepingController;
use App\Http\Controllers\HousekeepingBoardController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MonthlyCalendarController;
use App\Http\Controllers\NightAuditController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pos\DepartmentController;
use App\Http\Controllers\Pos\GuestRequestController;
use App\Http\Controllers\Pos\ItemCategoryController;
use App\Http\Controllers\Pos\ItemController;
use App\Http\Controllers\Pos\KitchenController;
use App\Http\Controllers\Pos\ModifierController;
use App\Http\Controllers\Pos\ModifierGroupController;
use App\Http\Controllers\Pos\NcTypeController;
use App\Http\Controllers\Pos\OutletController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\PosListController;
use App\Http\Controllers\Pos\RatePlanController;
use App\Http\Controllers\Pos\SetupController;
use App\Http\Controllers\Pos\SlotController;
use App\Http\Controllers\Pos\StewardController;
use App\Http\Controllers\Pos\TableController;
use App\Http\Controllers\PosDashboardController;
use App\Http\Controllers\PreRegCardController;
use App\Http\Controllers\Rate\RateCalendarController;
use App\Http\Controllers\Rate\RateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationCalendarController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReservationStatusController;
use App\Http\Controllers\RoomBlockedController;
use App\Http\Controllers\RoomCalendarController;
use App\Http\Controllers\RoomServiceReportController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'index'])->name('login');
    Route::post('loginIn', [LoginController::class, 'loginIn'])->name('loginIn');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| The guest's own document
|--------------------------------------------------------------------------
| Deliberately outside every middleware group. WhatsApp's gateway fetches the
| PDF itself — no account, no session, no cookie — and so does the guest when
| they tap it on their phone. The forty random characters in the link are what
| stands in for a login: nothing to guess and nothing to count up through.
*/
Route::get('guest-doc/{token}', [GuestDocumentController::class, 'show'])
    // The trailing .pdf is optional and ignored by the lookup — it exists
    // only so the link can be given a real file extension when something
    // fetching it decides what a file is by looking at the URL rather than
    // its Content-Type header. See the note in GuestDocumentController.
    ->where('token', '[A-Za-z0-9]{40}(?:\.pdf)?')
    ->name('guest-doc');

/*
 * The feedback form, on the same terms and for the same reason: a guest opens
 * it on their phone from a link in a message, days after they left, with no
 * account and nothing to log in to.
 */
Route::get('feedback/{token}', [GuestFeedbackFormController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('guest-feedback');

Route::post('feedback/{token}', [GuestFeedbackFormController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('guest-feedback.store');

/*
 * The self-order menu a guest reaches by scanning the QR on their table —
 * same terms as the two links above: no account, and `code` (PosTable::code())
 * is what stands in for one. `status/{token}` is the guest's own link back to
 * "what happened to my order", handed to them once, right after they send it,
 * the same way the feedback link is handed out in a message.
 */
Route::prefix('order/{table}/{code}')
    ->where(['table' => '[0-9]+', 'code' => '[A-Za-z0-9]{6}'])
    ->group(function () {
        Route::get('/', [GuestOrderController::class, 'menu'])->name('guest-order.menu');
        Route::post('/', [GuestOrderController::class, 'store'])->name('guest-order.store');

        Route::prefix('status/{token}')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
            Route::get('/', [GuestOrderController::class, 'status'])->name('guest-order.status');
            Route::get('poll', [GuestOrderController::class, 'poll'])->name('guest-order.poll');
        });
    });

/*
 * "Add to Home Screen" — a phone fetches this the moment the page loads,
 * login screen included, long before there is a session to be signed in
 * with. Carries nothing but this installation's own name, colours and icon
 * (see ManifestController), so it belongs out here with the other routes
 * that ask for no account.
 */
Route::get('manifest.webmanifest', [ManifestController::class, 'show'])->name('manifest');

/*
|--------------------------------------------------------------------------
| Signed in
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');

    // Dashboard AI Assistant — read-only, same figures the dashboard already
    // shows, so it needs no extra permission beyond being signed in.
    Route::post('ai/chat', [AiAssistantController::class, 'chat'])->name('ai.chat');

    // Self-service — open to everyone who can sign in
    Route::get('user-profile', [UserController::class, 'userProfile'])->name('user-profile');
    Route::post('profile-update', [UserController::class, 'userProfileUpdate'])->name('profile-update');
    Route::get('change-password', [ProfileController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('change-password', [ProfileController::class, 'changePassword'])->name('password.update');

    // Branch switcher
    Route::post('changeBranch', [BranchController::class, 'changeBranch'])->name('changeBranch');
    Route::get('get-state-id/{country}', [BranchController::class, 'getState'])->name('get-state');
    Route::get('get-city-id/{state}', [BranchController::class, 'getCity'])->name('get-city');

    /*
    |----------------------------------------------------------------------
    | Masters
    |----------------------------------------------------------------------
    | One controller serves every list in config/masters.php.
    */
    Route::controller(MasterController::class)->prefix('masters')->name('masters.')
        // {master} may only be a key from config/masters.php, so an unknown
        // master is a clean 404 rather than a 405 or a stack trace.
        ->whereIn('master', array_keys(config('masters')))
        ->whereNumber('id')
        ->group(function () {
            Route::get('/', 'home')->name('home')->middleware('permission:masters,view');
            Route::get('{master}', 'index')->name('index')->middleware('permission:masters,view');
            Route::get('{master}/create', 'create')->name('create')->middleware('permission:masters,add');
            // The grid for typing several at once. Same permission as adding
            // one, because that is what it is.
            Route::get('{master}/add-many', 'createMany')->name('create-many')->middleware('permission:masters,add');
            Route::post('{master}/add-many', 'storeMany')->name('store-many')->middleware('permission:masters,add');
            Route::post('{master}', 'store')->name('store')->middleware('permission:masters,add');
            Route::get('{master}/{id}/edit', 'edit')->name('edit')->middleware('permission:masters,edit');
            Route::put('{master}/{id}', 'update')->name('update')->middleware('permission:masters,edit');
            Route::post('{master}/{id}/toggle', 'toggle')->name('toggle')->middleware('permission:masters,edit');
            Route::delete('{master}/{id}', 'destroy')->name('destroy')->middleware('permission:masters,delete');
        });

    /*
    |----------------------------------------------------------------------
    | Reservation
    |----------------------------------------------------------------------
    | The sub-module urls seeded into the menu are the permission keys, so
    | `reservation/new-reservation` gates both the link and the route.
    */
    Route::controller(ReservationController::class)->prefix('reservation')->name('reservation.')->group(function () {
        Route::get('new-reservation', 'create')->name('create')
            ->middleware('permission:reservation/new-reservation,add');
        Route::post('new-reservation', 'store')->name('store')
            ->middleware('permission:reservation/new-reservation,add');

        Route::get('booking-details', 'index')->name('index')
            ->middleware('permission:reservation/booking-details,view');

        Route::get('cancel-list', 'cancelled')->name('cancelled')
            ->middleware('permission:reservation/cancel-list,view');

        // Lookups the form calls while you type.
        Route::get('guests', 'searchGuests')->name('guests')
            ->middleware('permission:reservation/new-reservation,view');
        Route::get('availability', 'availability')->name('availability')
            ->middleware('permission:reservation/new-reservation,view');
        Route::get('room-types', 'roomTypes')->name('room-types')
            ->middleware('permission:reservation/new-reservation,view');

        // whereNumber keeps these from swallowing sibling paths — the
        // calendar and status screens live at reservation/<word> too.
        Route::get('{reservation}', 'show')->name('show')->whereNumber('reservation')
            ->middleware('permission:reservation/booking-details,view');
        Route::get('{reservation}/edit', 'edit')->name('edit')->whereNumber('reservation')
            ->middleware('permission:reservation/new-reservation,edit');
        Route::put('{reservation}', 'update')->name('update')->whereNumber('reservation')
            ->middleware('permission:reservation/new-reservation,edit');
        Route::post('{reservation}/cancel', 'cancel')->name('cancel')->whereNumber('reservation')
            ->middleware('permission:reservation/cancel-list,delete');
        Route::post('{reservation}/deposit', 'deposit')->name('deposit')->whereNumber('reservation')
            ->middleware('permission:reservation/advance-deposit,edit');
    });

    // Reservation Status View — the availability calendar
    Route::controller(ReservationStatusController::class)->prefix('reservation')->name('reservation.status')
        ->middleware('permission:reservation/status-view,view')
        ->group(function () {
            Route::get('status-view', 'index');
            Route::get('status-view/export', 'export')->name('.export');
            Route::get('status-view/cell', 'cell')->name('.cell');
        });

    // Reservation Calendar — the current / advance grid
    Route::controller(BookingCalendarController::class)->prefix('reservation')
        ->name('reservation.booking-calendar')
        ->middleware('permission:reservation/calendar,view')
        ->group(function () {
            Route::get('calendar', 'index');
            Route::get('calendar/details', 'details')->name('.details');
            Route::get('calendar/export', 'export')->name('.export');
        });

    // Reservation Calendar New — the tape chart
    Route::controller(ReservationCalendarController::class)->prefix('reservation')->name('reservation.calendar')
        ->group(function () {
            Route::get('calendar-new', 'index')
                ->middleware('permission:reservation/calendar-new,view');
            Route::get('calendar-new/free-rooms', 'freeRooms')->name('.free-rooms')
                ->middleware('permission:reservation/calendar-new,view');

            // Booking and blocking a room both count as adding a reservation.
            Route::post('calendar-new/block', 'block')->name('.block')
                ->middleware('permission:reservation/new-reservation,add');
            Route::post('calendar-new/unblock', 'unblock')->name('.unblock')
                ->middleware('permission:reservation/new-reservation,add');
            Route::post('calendar-new/assign', 'assign')->name('.assign')
                ->middleware('permission:reservation/new-reservation,edit');

            // Dragging a bar to other nights changes that booking's dates.
            Route::post('calendar-new/move', 'move')->name('.move')
                ->middleware('permission:reservation/new-reservation,edit');
        });

    // Reservation Calendar Monthly — the position of the house
    Route::controller(MonthlyCalendarController::class)->prefix('reservation')
        ->name('reservation.calendar-monthly')
        ->middleware('permission:reservation/calendar-monthly,view')
        ->group(function () {
            Route::get('calendar-monthly', 'index');
            Route::get('calendar-monthly/export', 'export')->name('.export');
        });

    // Advance Deposit Details — money in and money back out
    Route::controller(AdvanceDepositController::class)->prefix('reservation')
        ->name('reservation.advance-deposit')
        // Same reason as the reservation wildcards above: without this,
        // `advance-deposit/{deposit}` would claim `advance-deposit/guests`.
        ->whereNumber('deposit')
        ->group(function () {
            Route::get('advance-deposit', 'index')
                ->middleware('permission:reservation/advance-deposit,view');
            Route::get('advance-deposit/guests', 'guests')->name('.guests')
                ->middleware('permission:reservation/advance-deposit,view');
            Route::post('advance-deposit', 'store')->name('.store')
                ->middleware('permission:reservation/advance-deposit,add');
            Route::put('advance-deposit/{deposit}', 'update')->name('.update')
                ->middleware('permission:reservation/advance-deposit,edit');
            Route::delete('advance-deposit/{deposit}', 'destroy')->name('.destroy')
                ->middleware('permission:reservation/advance-deposit,delete');
        });

    /*
    |----------------------------------------------------------------------
    | Front Office
    |----------------------------------------------------------------------
    | Check-in is a separate act from booking: a reservation holds a room,
    | a check-in says the guest is in it. The two permission keys match the
    | two menu items.
    */
    /*
    |----------------------------------------------------------------------
    | Point Of Sale
    |----------------------------------------------------------------------
    | The restaurant, room service and the bar. Only the dashboard is built;
    | the rest of the module carries a Soon pill until its routes exist.
    */
    Route::controller(PosDashboardController::class)->prefix('point-of-sale')->name('point-of-sale.')
        ->group(function () {
            Route::get('dashboard', 'index')->name('dashboard')
                ->middleware('permission:point-of-sale/dashboard,view');
        });

    /*
     * The till itself.
     *
     * One permission key for the whole of it — `point-of-sale/pos` — because a
     * cashier who may open a table is a cashier who may add to it and print it.
     * The four actions are what separate the roles: view reads the floor, add
     * opens orders and puts things on them, edit changes what is already there
     * and moves tables, delete cancels.
     */
    Route::prefix('point-of-sale/pos')->name('point-of-sale.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('pos')
            ->middleware('permission:point-of-sale/pos,view');

        /* ── Opening ──────────────────────────────────────────────────── */
        Route::post('table/{table}', [PosController::class, 'openTable'])->name('pos.table')
            ->middleware('permission:point-of-sale/pos,add');
        Route::post('room/{stay}', [PosController::class, 'openRoom'])->name('pos.room')
            ->middleware('permission:point-of-sale/pos,add');
        Route::post('counter', [PosController::class, 'openCounter'])->name('pos.counter')
            ->middleware('permission:point-of-sale/pos,add');

        /* ── One order ────────────────────────────────────────────────── */
        Route::get('order/{order}', [PosController::class, 'order'])->name('pos.order')
            ->middleware('permission:point-of-sale/pos,view');
        Route::post('order/{order}/item', [PosController::class, 'addItem'])->name('pos.order.item')
            ->middleware('permission:point-of-sale/pos,add');
        Route::post('order/{order}/barcode', [PosController::class, 'scanItem'])->name('pos.order.barcode')
            ->middleware('permission:point-of-sale/pos,add');
        Route::put('order/{order}/item/{line}', [PosController::class, 'updateItem'])->name('pos.order.item.update')
            ->middleware('permission:point-of-sale/pos,edit');
        Route::delete('order/{order}/item/{line}', [PosController::class, 'removeItem'])->name('pos.order.item.destroy')
            ->middleware('permission:point-of-sale/pos,delete');
        Route::put('order/{order}', [PosController::class, 'header'])->name('pos.order.header')
            ->middleware('permission:point-of-sale/pos,edit');
        Route::post('order/{order}/kot', [PosController::class, 'kot'])->name('pos.order.kot')
            ->middleware('permission:point-of-sale/pos,add');
        Route::post('order/{order}/bill', [PosController::class, 'bill'])->name('pos.order.bill')
            ->middleware('permission:point-of-sale/pos,edit');
        Route::post('order/{order}/settle', [PosController::class, 'settle'])->name('pos.order.settle')
            ->middleware('permission:point-of-sale/pos,edit');
        Route::post('order/{order}/shift', [PosController::class, 'shift'])->name('pos.order.shift')
            ->middleware('permission:point-of-sale/pos,edit');
        Route::post('order/{order}/cancel', [PosController::class, 'cancel'])->name('pos.order.cancel')
            ->middleware('permission:point-of-sale/pos,delete');

        /* ── Paper ────────────────────────────────────────────────────── */
        Route::get('order/{order}/print', [PosController::class, 'printBill'])->name('pos.order.print')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('order/{order}/kot/{number}', [PosController::class, 'printKot'])->name('pos.order.kot.print')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('table/{table}/card', [PosController::class, 'tableCard'])->name('pos.table.card')
            ->middleware('permission:point-of-sale/pos,view');
        // The older use of the same code: a signed-in staff member jumping
        // straight to a table's order screen. The card itself now points
        // guests at guest-order.menu instead (see PosController::tableCard).
        Route::get('scan/{table}', [PosController::class, 'scan'])->name('pos.scan')
            ->middleware('permission:point-of-sale/pos,add');

        /* ── The six screens beside Dine In ───────────────────────────── */
        Route::get('live-orders', [PosListController::class, 'live'])->name('pos.live-orders')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('unsettled', [PosListController::class, 'unsettled'])->name('pos.unsettled')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('cash-balance', [PosListController::class, 'cash'])->name('pos.cash-balance')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('invoices', [PosListController::class, 'invoices'])->name('pos.invoices')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('outlet-orders', [PosListController::class, 'outletOrders'])->name('pos.outlet-orders')
            ->middleware('permission:point-of-sale/pos,view');
        Route::get('collections', [PosListController::class, 'collections'])->name('pos.collections')
            ->middleware('permission:point-of-sale/pos,view');

        /*
         * What guests have asked for from their own phones, waiting on
         * somebody here to say yes or no. Approve is `,add` because it puts
         * items on the order, same as opening a table does; Decline is
         * `,edit` because it only ever changes the request's own status.
         */
        Route::get('guest-requests', [GuestRequestController::class, 'index'])->name('pos.guest-requests')
            ->middleware('permission:point-of-sale/pos,view');
        Route::post('guest-requests/{guestRequest}/approve', [GuestRequestController::class, 'approve'])
            ->name('pos.guest-requests.approve')
            ->middleware('permission:point-of-sale/pos,add');
        Route::post('guest-requests/{guestRequest}/reject', [GuestRequestController::class, 'reject'])
            ->name('pos.guest-requests.reject')
            ->middleware('permission:point-of-sale/pos,edit');
    });

    /*
     * The two room-wise reports.
     *
     * They answer questions that sound alike and are not, so they sit under the
     * module that owns the data: what a room was charged on its folio is Front
     * Office's, what the kitchen sent up is the POS's.
     */
    Route::controller(RoomServiceReportController::class)->group(function () {
        Route::get('front-office/room-wise-services', 'services')
            ->name('front-office.room-wise-services')
            ->middleware('permission:front-office/room-wise-services,view');
        Route::get('front-office/room-wise-services/export', 'exportServices')
            ->name('front-office.room-wise-services.export')
            ->middleware('permission:front-office/room-wise-services,view');

        Route::get('point-of-sale/room-service-orders', 'orders')
            ->name('point-of-sale.room-service-orders')
            ->middleware('permission:point-of-sale/room-service-orders,view');
        Route::get('point-of-sale/room-service-orders/export', 'exportOrders')
            ->name('point-of-sale.room-service-orders.export')
            ->middleware('permission:point-of-sale/room-service-orders,view');
    });

    /*
     * Kitchen Display System — its own permission, because the screen on the
     * kitchen wall is logged in all evening and must not also be able to
     * settle bills.
     */
    Route::prefix('point-of-sale/kitchen-display')->name('point-of-sale.')
        ->group(function () {
            Route::get('/', [KitchenController::class, 'index'])->name('kitchen-display')
                ->middleware('permission:point-of-sale/kitchen-display,view');
            Route::get('feed', [KitchenController::class, 'feed'])->name('kitchen-display.feed')
                ->middleware('permission:point-of-sale/kitchen-display,view');
            Route::post('advance', [KitchenController::class, 'advance'])->name('kitchen-display.advance')
                ->middleware('permission:point-of-sale/kitchen-display,edit');
        });

    /*
     * Setup — everything a till has to be told before it can sell anything.
     *
     * Each screen carries its own permission key rather than sharing one, so a
     * hotel can let a manager add stewards without also handing them the bill
     * series and the tax numbers.
     */
    Route::get('point-of-sale/setup', [SetupController::class, 'index'])
        ->name('point-of-sale.setup')
        ->middleware('permission:point-of-sale/setup,view');

    Route::prefix('point-of-sale/setup')->name('point-of-sale.setup.')->group(function () {
        /* ── Outlets ──────────────────────────────────────────────────── */
        Route::get('outlets', [OutletController::class, 'index'])->name('outlets')
            ->middleware('permission:point-of-sale/setup/outlets,view');
        Route::get('outlets/create', [OutletController::class, 'create'])->name('outlets.create')
            ->middleware('permission:point-of-sale/setup/outlets,add');
        Route::post('outlets', [OutletController::class, 'store'])->name('outlets.store')
            ->middleware('permission:point-of-sale/setup/outlets,add');
        Route::get('outlets/{id}/edit', [OutletController::class, 'edit'])->name('outlets.edit')
            ->middleware('permission:point-of-sale/setup/outlets,edit');
        Route::put('outlets/{id}', [OutletController::class, 'update'])->name('outlets.update')
            ->middleware('permission:point-of-sale/setup/outlets,edit');
        Route::delete('outlets/{id}', [OutletController::class, 'destroy'])->name('outlets.destroy')
            ->middleware('permission:point-of-sale/setup/outlets,delete');
        // Restore undoes a delete, so it answers to the delete permission.
        Route::post('outlets/{id}/restore', [OutletController::class, 'restore'])->name('outlets.restore')
            ->middleware('permission:point-of-sale/setup/outlets,delete');

        /* ── Tables: groups and the seats inside them ─────────────────── */
        Route::get('tables', [TableController::class, 'index'])->name('tables')
            ->middleware('permission:point-of-sale/setup/tables,view');
        // One endpoint for add and edit: the dialog posts an id or it does not.
        Route::post('tables/group', [TableController::class, 'storeGroup'])->name('tables.group')
            ->middleware('permission:point-of-sale/setup/tables,add');
        Route::delete('tables/group/{id}', [TableController::class, 'destroyGroup'])->name('tables.group.destroy')
            ->middleware('permission:point-of-sale/setup/tables,delete');
        Route::post('tables/group/{id}/restore', [TableController::class, 'restoreGroup'])->name('tables.group.restore')
            ->middleware('permission:point-of-sale/setup/tables,delete');
        Route::post('tables/table', [TableController::class, 'storeTable'])->name('tables.table')
            ->middleware('permission:point-of-sale/setup/tables,add');
        Route::delete('tables/table/{id}', [TableController::class, 'destroyTable'])->name('tables.table.destroy')
            ->middleware('permission:point-of-sale/setup/tables,delete');
        Route::post('tables/table/{id}/restore', [TableController::class, 'restoreTable'])->name('tables.table.restore')
            ->middleware('permission:point-of-sale/setup/tables,delete');

        /* ── Table reservation slots ──────────────────────────────────── */
        Route::get('slots', [SlotController::class, 'index'])->name('slots')
            ->middleware('permission:point-of-sale/setup/slots,view');
        Route::post('slots', [SlotController::class, 'store'])->name('slots.store')
            ->middleware('permission:point-of-sale/setup/slots,add');
        Route::delete('slots/{id}', [SlotController::class, 'destroy'])->name('slots.destroy')
            ->middleware('permission:point-of-sale/setup/slots,delete');
        Route::post('slots/{id}/restore', [SlotController::class, 'restore'])->name('slots.restore')
            ->middleware('permission:point-of-sale/setup/slots,delete');

        /*
         * The five type-straight-into-it lists. They are the same screen with
         * different columns, so they are the same five routes with different
         * controllers — written once rather than copied five times.
         */
        $lists = [
            'item-category' => ItemCategoryController::class,
            'items' => ItemController::class,
            'rate-plan' => RatePlanController::class,
            'department' => DepartmentController::class,
            'stewards' => StewardController::class,
            'nc-types' => NcTypeController::class,
            'modifier-groups' => ModifierGroupController::class,
            'modifiers' => ModifierController::class,
        ];

        foreach ($lists as $path => $controller) {
            $key = "point-of-sale/setup/{$path}";

            Route::get($path, [$controller, 'index'])->name($path)
                ->middleware("permission:{$key},view");
            Route::post($path, [$controller, 'store'])->name($path . '.store')
                ->middleware("permission:{$key},add");
            // Several rows in one save. Only the lists whose definition says
            // `bulk` answer it; the rest return 404 from the controller.
            Route::post($path . '/bulk', [$controller, 'storeMany'])->name($path . '.store-many')
                ->middleware("permission:{$key},add");
            Route::put($path . '/{id}', [$controller, 'update'])->name($path . '.update')
                ->middleware("permission:{$key},edit");
            Route::delete($path . '/{id}', [$controller, 'destroy'])->name($path . '.destroy')
                ->middleware("permission:{$key},delete");
            Route::post($path . '/{id}/restore', [$controller, 'restore'])->name($path . '.restore')
                ->middleware("permission:{$key},delete");
        }

        /*
         * A picture for one item, outside the five-lists loop above because
         * only Items carries one — none of the other five type-straight-in
         * lists has anything to photograph.
         */
        Route::post('items/{id}/photo', [ItemController::class, 'photo'])->name('items.photo')
            ->middleware('permission:point-of-sale/setup/items,edit');
    });

    /*
    |----------------------------------------------------------------------
    | House Keeping
    |----------------------------------------------------------------------
    */
    Route::controller(HouseKeepingController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('status', 'index')->name('status')
                ->middleware('permission:house-keeping/status,view');
            Route::post('status', 'update')->name('status.update')
                ->middleware('permission:house-keeping/status,edit');
        });

    /*
     * The board — the same rooms as the Status screen, seen as work rather than
     * as a list. Its own permission key because the two screens are used by two
     * different people: the Status list is the supervisor's paperwork, the board
     * is what the floor stares at all day.
     */
    Route::controller(HousekeepingBoardController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('board', 'index')->name('board')
                ->middleware('permission:house-keeping/board,view');
            Route::post('board/move', 'move')->name('board.move')
                ->middleware('permission:house-keeping/board,edit');
            Route::post('board/assign', 'assign')->name('board.assign')
                ->middleware('permission:house-keeping/board,edit');
        });

    /*
     * Issue and Received are the two halves of one cycle — linen out to the
     * laundry, linen back — so they share the Laundry sum that says what each
     * vendor is still holding. The item and vendor lists are set up from the
     * issue screen itself, which is the only place anybody ever needs them,
     * so they sit under its permission key rather than earning menu entries
     * of their own.
     */
    Route::controller(HkIssueController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('issue', 'index')->name('issue')
                ->middleware('permission:house-keeping/issue,view');
            Route::get('issue/new', 'create')->name('issue.create')
                ->middleware('permission:house-keeping/issue,add');
            Route::get('issue/pending', 'pending')->name('issue.pending')
                ->middleware('permission:house-keeping/issue,view');
            Route::post('issue', 'store')->name('issue.store')
                ->middleware('permission:house-keeping/issue,add');
            Route::post('issue/item', 'storeItem')->name('issue.item')
                ->middleware('permission:house-keeping/issue,add');
            Route::post('issue/vendor', 'storeVendor')->name('issue.vendor')
                ->middleware('permission:house-keeping/issue,add');

            // whereNumber keeps this from swallowing issue/new and issue/pending.
            Route::get('issue/{issue}', 'show')->name('issue.show')->whereNumber('issue')
                ->middleware('permission:house-keeping/issue,view');
            Route::delete('issue/{issue}', 'destroy')->name('issue.destroy')->whereNumber('issue')
                ->middleware('permission:house-keeping/issue,delete');
        });

    /*
     * Room Blocked — the third thing that can hold a room, alongside a booking
     * and a check-in. Every availability test in the app already reads
     * room_blocks, so blocking here takes the room off every other screen on
     * its own.
     */
    Route::controller(RoomBlockedController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('room-blocked', 'index')->name('room-blocked')
                ->middleware('permission:house-keeping/room-blocked,view');
            Route::post('room-blocked', 'store')->name('room-blocked.store')
                ->middleware('permission:house-keeping/room-blocked,add');
            Route::post('room-blocked/bulk', 'bulk')->name('room-blocked.bulk')
                ->middleware('permission:house-keeping/room-blocked,add');
            Route::post('room-blocked/release', 'release')->name('room-blocked.release')
                ->middleware('permission:house-keeping/room-blocked,delete');
        });

    /*
     * Work Order — the maintenance job card. A job can hold its room off sale
     * through the same room_blocks row the Room Blocked screen uses, so the two
     * screens can never disagree about whether 204 is sellable.
     */
    Route::controller(WorkOrderController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('work-order', 'index')->name('work-order')
                ->middleware('permission:house-keeping/work-order,view');
            Route::get('work-order/new', 'create')->name('work-order.create')
                ->middleware('permission:house-keeping/work-order,add');
            Route::post('work-order', 'store')->name('work-order.store')
                ->middleware('permission:house-keeping/work-order,add');

            // whereNumber keeps these from swallowing work-order/new.
            Route::get('work-order/{order}/edit', 'edit')->name('work-order.edit')->whereNumber('order')
                ->middleware('permission:house-keeping/work-order,edit');
            Route::put('work-order/{order}', 'update')->name('work-order.update')->whereNumber('order')
                ->middleware('permission:house-keeping/work-order,edit');
            Route::post('work-order/{order}/close', 'close')->name('work-order.close')->whereNumber('order')
                ->middleware('permission:house-keeping/work-order,edit');
            Route::delete('work-order/{order}', 'destroy')->name('work-order.destroy')->whereNumber('order')
                ->middleware('permission:house-keeping/work-order,delete');
        });

    Route::controller(HkReceivedController::class)->prefix('house-keeping')->name('house-keeping.')
        ->group(function () {
            Route::get('received', 'index')->name('received')
                ->middleware('permission:house-keeping/received,view');
            Route::get('received/new', 'create')->name('received.create')
                ->middleware('permission:house-keeping/received,add');
            Route::get('received/pending', 'pending')->name('received.pending')
                ->middleware('permission:house-keeping/received,view');
            Route::post('received', 'store')->name('received.store')
                ->middleware('permission:house-keeping/received,add');

            Route::get('received/{receipt}', 'show')->name('received.show')->whereNumber('receipt')
                ->middleware('permission:house-keeping/received,view');
            Route::delete('received/{receipt}', 'destroy')->name('received.destroy')->whereNumber('receipt')
                ->middleware('permission:house-keeping/received,delete');
        });

    // Room Calendar — the whole house as tiles, one day at a time
    Route::controller(RoomCalendarController::class)->prefix('front-office')->name('front-office.')
        ->group(function () {
            Route::get('room-calendar', 'index')->name('room-calendar')
                ->middleware('permission:front-office/room-calendar,view');
            Route::get('room-calendar/guest', 'guest')->name('room-calendar.guest')
                ->middleware('permission:front-office/room-calendar,view');
            Route::post('room-calendar/housekeeping', 'housekeeping')->name('room-calendar.housekeeping')
                ->middleware('permission:front-office/room-calendar,edit');
        });

    /*
    |----------------------------------------------------------------------
    | Store and Inventory
    |----------------------------------------------------------------------
    | The five kinds of store paperwork share a controller, so `{kind}` is in
    | the URL — but each has its own permission key, because ordering,
    | receiving and writing stock off are three different levels of trust.
    | The keys are listed out rather than built from `{kind}`, so a URL cannot
    | invent a permission that is not in the matrix.
    */
    Route::controller(StoreItemController::class)->prefix('store')->name('store.')
        ->whereNumber(['item', 'category'])
        ->group(function () {
            Route::get('items', 'index')->name('items')
                ->middleware('permission:store/items,view');
            Route::post('items', 'save')->name('items.store')
                ->middleware('permission:store/items,add');
            Route::put('items/{item}', 'save')->name('items.update')
                ->middleware('permission:store/items,edit');
            Route::delete('items/{item}', 'destroy')->name('items.destroy')
                ->middleware('permission:store/items,delete');

            Route::get('categories', 'categories')->name('categories')
                ->middleware('permission:store/categories,view');
            Route::post('categories', 'saveCategory')->name('categories.store')
                ->middleware('permission:store/categories,add');
            Route::put('categories/{category}', 'saveCategory')->name('categories.update')
                ->middleware('permission:store/categories,edit');
            Route::delete('categories/{category}', 'deleteCategory')->name('categories.destroy')
                ->middleware('permission:store/categories,delete');
        });

    Route::controller(StockController::class)->prefix('store')->name('store.')
        ->whereNumber('item')
        ->group(function () {
            Route::get('stock', 'index')->name('stock')
                ->middleware('permission:store/stock,view');
            Route::get('stock/export', 'export')->name('stock.export')
                ->middleware('permission:store/stock,view');
            Route::get('stock/{item}', 'ledger')->name('stock.ledger')
                ->middleware('permission:store/stock,view');
            // Replaying the ledger rewrites balances, so it is an edit.
            Route::post('stock/{item}/rebuild', 'rebuild')->name('stock.rebuild')
                ->middleware('permission:store/stock,edit');
        });

    Route::controller(RecipeController::class)->prefix('store')->name('store.')
        ->whereNumber('recipe')
        ->group(function () {
            Route::get('recipes', 'index')->name('recipes')
                ->middleware('permission:store/recipes,view');
            Route::get('recipes/new', 'edit')->name('recipes.create')
                ->middleware('permission:store/recipes,add');
            Route::post('recipes', 'save')->name('recipes.store')
                ->middleware('permission:store/recipes,add');
            Route::get('recipes/{recipe}', 'edit')->name('recipes.edit')
                ->middleware('permission:store/recipes,view');
            Route::put('recipes/{recipe}', 'save')->name('recipes.update')
                ->middleware('permission:store/recipes,edit');
            Route::delete('recipes/{recipe}', 'destroy')->name('recipes.destroy')
                ->middleware('permission:store/recipes,delete');
        });

    /*
     * One group, with the kind as a URL segment constrained to the ones that
     * exist — so an unknown one is a clean 404 rather than a stack trace.
     *
     * Eight entries, not five: the menu (database/seeders/MenuSeeder.php) and
     * the permission matrix know three of these documents by the same long,
     * readable slug as their permission key — "purchase-orders", "issues",
     * "adjustments" — while the database enum and this controller's own
     * internals have always used the short code — "po", "issue",
     * "adjustment". Both spellings are accepted here; DocController::shape()
     * normalises the long one to the short one before anything else runs, so
     * the link the menu has been pointing at all along starts working rather
     * than needing the menu, the permission matrix or the database enum
     * changed underneath it.
     *
     * The permission is checked inside the controller rather than by
     * middleware here, because it depends on BOTH the kind and the action:
     * receiving stock and writing it off are different levels of trust, and a
     * middleware string cannot say "whichever key this kind maps to". See
     * DocController::shape().
     */
    Route::controller(DocController::class)->prefix('store')->name('store.docs')
        ->whereIn('kind', [
            'po', 'purchase-orders',
            'grn',
            'issue', 'issues',
            'wastage',
            'adjustment', 'adjustments',
            'transfer_out', 'transfer_in', 'transfer',
        ])
        ->whereNumber('doc')
        ->group(function () {
            Route::get('{kind}', 'index');
            Route::get('{kind}/new', 'create')->name('.create');
            Route::post('{kind}', 'save')->name('.store');
            Route::get('{kind}/{doc}', 'show')->name('.show');
            Route::get('{kind}/{doc}/edit', 'edit')->name('.edit');
            Route::put('{kind}/{doc}', 'save')->name('.update');
            Route::post('{kind}/{doc}/post', 'post')->name('.post');
            Route::delete('{kind}/{doc}', 'cancel')->name('.cancel');
        });

    // Where a receiving outlet finds out a transfer is on its way — see
    // TransferController and the redirect in DocController::create().
    Route::get('store/transfer/inbox', [TransferController::class, 'inbox'])
        ->name('store.transfer.inbox');

    /*
    |----------------------------------------------------------------------
    | Shift closing — the drawer, and whether it is right
    |----------------------------------------------------------------------
    | Two screens with two different permissions on purpose. `shift/my-shift`
    | is every cashier's own drawer and is what they are given; `shift/reports`
    | is everybody's, and is a manager's screen. A cashier who can see their
    | own close does not thereby get to see the variances of the person on the
    | shift before them.
    |
    | Closing somebody else's drawer is `edit` on the manager's screen: the
    | shift already exists, and what changes is whose name is on the close.
    */
    Route::controller(ShiftController::class)->prefix('shift')
        ->whereNumber('shift')
        ->group(function () {
            Route::get('my-shift', 'mine')->name('shift.my-shift')
                ->middleware('permission:shift/my-shift,view');
            Route::post('my-shift/open', 'open')->name('shift.my-shift.open')
                ->middleware('permission:shift/my-shift,add');
            Route::post('my-shift/close', 'close')->name('shift.my-shift.close')
                ->middleware('permission:shift/my-shift,add');

            Route::get('reports', 'index')->name('shift.reports')
                ->middleware('permission:shift/reports,view');
            Route::get('reports/{shift}', 'show')->name('shift.reports.show')
                ->middleware('permission:shift/reports,view');
            Route::get('reports/{shift}/print', 'print')->name('shift.reports.print')
                ->middleware('permission:shift/reports,view');
            Route::post('reports/{shift}/close', 'closeOther')->name('shift.reports.close')
                ->middleware('permission:shift/reports,edit');
        });

    /*
    |----------------------------------------------------------------------
    | The audit trail — who changed what
    |----------------------------------------------------------------------
    | View only, for everybody including an administrator. There is no add,
    | edit or delete route here and there is not going to be one: a log a
    | manager can tidy up is not evidence of anything.
    */
    Route::controller(TrailController::class)->prefix('audit')->group(function () {
        Route::get('trail', 'index')->name('audit.trail')
            ->middleware('permission:audit/trail,view');
        Route::get('trail/export', 'export')->name('audit.trail.export')
            ->middleware('permission:audit/trail,view');
        Route::get('logins', 'logins')->name('audit.logins')
            ->middleware('permission:audit/logins,view');
    });

    /*
    |----------------------------------------------------------------------
    | Guest CRM — the person, rather than the booking
    |----------------------------------------------------------------------
    | `delete` on crm/guests is what blacklists a guest and removes a note,
    | which is right: both are things that should need more than the
    | permission to type into a box.
    */
    Route::controller(CrmGuestController::class)->prefix('crm')->name('crm.')
        ->whereNumber(['guest', 'note'])
        ->group(function () {
            Route::get('guests', 'index')->name('guests')
                ->middleware('permission:crm/guests,view');
            // Ahead of guests/{guest} on purpose — {guest} is numeric-only
            // (whereNumber above) so the order can't actually cause a
            // mismatch, but a literal path reads clearer coming first.
            Route::get('guests/search', 'search')->name('guests.search')
                ->middleware('permission:crm/guests,view');
            Route::get('guests/{guest}', 'show')->name('guests.show')
                ->middleware('permission:crm/guests,view');

            Route::post('guests/{guest}/note', 'addNote')->name('guests.note')
                ->middleware('permission:crm/guests,add');
            Route::post('guests/note/{note}/pin', 'pinNote')->name('guests.note.pin')
                ->middleware('permission:crm/guests,edit');
            Route::delete('guests/note/{note}', 'deleteNote')->name('guests.note.destroy')
                ->middleware('permission:crm/guests,delete');

            Route::post('guests/{guest}/preferences', 'savePreferences')->name('guests.preferences')
                ->middleware('permission:crm/guests,edit');
            Route::post('guests/{guest}/points', 'adjustPoints')->name('guests.points')
                ->middleware('permission:crm/guests,edit');
            Route::post('guests/{guest}/blacklist', 'blacklist')->name('guests.blacklist')
                ->middleware('permission:crm/guests,delete');

            Route::get('occasions', 'occasions')->name('occasions')
                ->middleware('permission:crm/occasions,view');
        });

    Route::controller(FeedbackController::class)->prefix('crm')->name('crm.')
        ->whereNumber('feedback')
        ->group(function () {
            Route::get('feedback', 'index')->name('feedback')
                ->middleware('permission:crm/feedback,view');
            Route::post('feedback/{feedback}/handled', 'handled')->name('feedback.handled')
                ->middleware('permission:crm/feedback,edit');
        });

    /*
    |----------------------------------------------------------------------
    | Compliance — the paperwork owed to somebody other than the guest
    |----------------------------------------------------------------------
    | Four screens with four permission keys. The one that matters is
    | `compliance/police-register`: its DELETE permission is what unlocks
    | whole ID numbers, on screen, on paper and in the CSV. There is no
    | "reveal" flag in the permission matrix, and Delete is the one nobody
    | is given by accident — so that is the line, and every screen that
    | shows an ID says which side of it the reader is on.
    */
    Route::controller(ComplianceController::class)->prefix('compliance')->name('compliance.')
        ->whereNumber(['checkIn', 'entry'])
        ->group(function () {
            Route::get('form-c', 'formC')->name('form-c')
                ->middleware('permission:compliance/form-c,view');
            Route::get('form-c/{checkIn}/write', 'formCCreate')->name('form-c.create')
                ->middleware('permission:compliance/form-c,add');
            Route::post('form-c/{checkIn}', 'formCStore')->name('form-c.store')
                ->middleware('permission:compliance/form-c,add');
            Route::post('form-c/{entry}/filed', 'formCFiled')->name('form-c.filed')
                ->middleware('permission:compliance/form-c,edit');
            Route::get('form-c/{entry}/print', 'formCPrint')->name('form-c.print')
                ->middleware('permission:compliance/form-c,view');

            Route::get('police-register', 'police')->name('police-register')
                ->middleware('permission:compliance/police-register,view');
            Route::get('police-register/print', 'policePrint')->name('police-register.print')
                ->middleware('permission:compliance/police-register,view');
            // Full ID numbers leave the building in this one, so the
            // controller checks `delete` again on the way through.
            Route::get('police-register/export', 'policeExport')->name('police-register.export')
                ->middleware('permission:compliance/police-register,view');

            Route::get('gst-returns', 'gst')->name('gst')
                ->middleware('permission:compliance/gst-returns,view');
            Route::get('gst-returns/export/{table}', 'gstExport')->name('gst.export')
                ->middleware('permission:compliance/gst-returns,view');

            Route::get('tally-export', 'tally')->name('tally')
                ->middleware('permission:compliance/tally-export,view');
            Route::get('tally-export/download', 'tallyDownload')->name('tally.download')
                ->middleware('permission:compliance/tally-export,view');
        });

    /*
    |----------------------------------------------------------------------
    | Rate Management — what a room costs tonight
    |----------------------------------------------------------------------
    | Four screens with four permission keys, because they are four different
    | jobs: a revenue manager loads rates, a receptionist only ever looks at
    | the calendar, and nobody but the owner should be inventing plans.
    |
    | The quote endpoint sits under the calendar's `view` permission. It
    | answers "what should this cost", which is exactly what the calendar
    | shows — anyone allowed to read one is allowed to read the other.
    */
    Route::controller(RateCalendarController::class)->prefix('rates')->name('rates.')
        ->group(function () {
            Route::get('calendar', 'index')->name('calendar')
                ->middleware('permission:rates/calendar,view');
        });

    Route::controller(RateController::class)->prefix('rates')->name('rates.')
        ->whereNumber(['plan', 'season', 'rule'])
        ->group(function () {
            Route::get('quote', 'quote')->name('quote')
                ->middleware('permission:rates/calendar,view');

            Route::get('plans', 'plans')->name('plans')
                ->middleware('permission:rates/plans,view');
            Route::post('plans', 'savePlan')->name('plans.store')
                ->middleware('permission:rates/plans,add');
            Route::put('plans/{plan}', 'savePlan')->name('plans.update')
                ->middleware('permission:rates/plans,edit');
            Route::delete('plans/{plan}', 'deletePlan')->name('plans.destroy')
                ->middleware('permission:rates/plans,delete');

            Route::get('seasons', 'seasons')->name('seasons')
                ->middleware('permission:rates/seasons,view');
            Route::post('seasons', 'saveSeason')->name('seasons.store')
                ->middleware('permission:rates/seasons,add');
            Route::put('seasons/{season}', 'saveSeason')->name('seasons.update')
                ->middleware('permission:rates/seasons,edit');
            Route::delete('seasons/{season}', 'deleteSeason')->name('seasons.destroy')
                ->middleware('permission:rates/seasons,delete');

            Route::get('rules', 'rules')->name('rules')
                ->middleware('permission:rates/rules,view');
            /*
             * There is no single-rate add. Rates are loaded across the house
             * at once — say when it applies, then a price per room type — so
             * the only way in is the grid, and a plan cannot end up with one
             * room type priced and the rest quietly on base rent.
             */
            Route::post('rules/add-many', 'storeRules')->name('rules.store-many')
                ->middleware('permission:rates/rules,add');
            Route::put('rules/{rule}', 'saveRule')->name('rules.update')
                ->middleware('permission:rates/rules,edit');
            Route::delete('rules/{rule}', 'deleteRule')->name('rules.destroy')
                ->middleware('permission:rates/rules,delete');
        });

    /*
    |----------------------------------------------------------------------
    | Night Audit — the hotel's day, closed
    |----------------------------------------------------------------------
    | Running the audit posts money to guest folios, so it sits behind `add`
    | rather than `edit`: a clerk allowed to correct a booking is not
    | automatically allowed to charge the whole house a night's rent.
    |
    | Reopening a closed night is `delete` — it is the destructive one, the
    | only action here that takes something away.
    */
    Route::controller(NightAuditController::class)->prefix('front-office')->name('front-office.night-audit')
        ->whereNumber('day')
        ->group(function () {
            Route::get('night-audit', 'index')
                ->middleware('permission:front-office/night-audit,view');
            Route::post('night-audit', 'run')->name('.run')
                ->middleware('permission:front-office/night-audit,add');
            Route::get('night-audit/{day}', 'show')->name('.show')
                ->middleware('permission:front-office/night-audit,view');
            Route::get('night-audit/{day}/print', 'print')->name('.print')
                ->middleware('permission:front-office/night-audit,view');
            Route::delete('night-audit/{day}', 'reopen')->name('.reopen')
                ->middleware('permission:front-office/night-audit,delete');
        });

    /*
    |----------------------------------------------------------------------
    | Check Out Guest
    |----------------------------------------------------------------------
    | Adding a charge, extending a stay and taking a payment are all edits to
    | a stay that is leaving, so they share the check-out permission rather
    | than inventing one of their own.
    */
    Route::controller(CheckOutController::class)->prefix('front-office')->name('front-office.check-out-guest')
        ->whereNumber(['checkIn', 'charge', 'settlement', 'bill'])
        ->group(function () {
            Route::get('check-out-guest', 'index')
                ->middleware('permission:front-office/check-out-guest,view');

            Route::post('check-out-guest/{checkIn}/charge', 'addCharge')->name('.add-charge')
                ->middleware('permission:front-office/check-out-guest,add');
            Route::delete('check-out-guest/charge/{charge}', 'removeCharge')->name('.remove-charge')
                ->middleware('permission:front-office/check-out-guest,delete');

            Route::post('check-out-guest/{checkIn}/extend', 'extend')->name('.extend')
                ->middleware('permission:front-office/check-out-guest,edit');
            Route::post('check-out-guest/{checkIn}/pax-checkout', 'paxCheckout')->name('.pax-checkout')
                ->middleware('permission:front-office/check-out-guest,edit');

            Route::post('check-out-guest/{checkIn}/pay', 'pay')->name('.pay')
                ->middleware('permission:front-office/check-out-guest,add');
            Route::delete('check-out-guest/payment/{settlement}', 'removePayment')->name('.remove-payment')
                ->middleware('permission:front-office/check-out-guest,delete');

            Route::post('check-out-guest/{checkIn}/checkout', 'checkout')->name('.checkout')
                ->middleware('permission:front-office/check-out-guest,add');

            Route::get('check-out-guest/{checkIn}/proforma', 'proforma')->name('.proforma')
                ->middleware('permission:front-office/check-out-guest,view');
            Route::get('check-out-guest/bill/{bill}', 'invoice')->name('.invoice')
                ->middleware('permission:front-office/check-out-guest,view');
        });

    /*
     * TEMP DIAGNOSTIC — read-only. Why the whole app has gotten slow since
     * yesterday's work: PHP-level settings that affect every single request
     * (opcache, xdebug), how big the tables everything queries have grown,
     * how many files are sitting in storage/, and real timings on a couple of
     * queries that run on nearly every page. Remove once this is understood.
     */
    Route::get('diag/perf', function () {
        $t0 = microtime(true);

        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;

        $countFiles = function (string $dir): int|string {
            if (! is_dir($dir)) {
                return 'no such folder';
            }

            try {
                $n = 0;
                foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $f) {
                    $n++;
                }

                return $n;
            } catch (\Throwable $e) {
                return 'error: ' . $e->getMessage();
            }
        };

        $tables = [
            'check_ins', 'folio_charges', 'reservations', 'reservation_rooms', 'bills', 'settlements',
            'pos_orders', 'pos_order_items', 'pos_order_item_modifiers', 'pos_invoices',
            'app_notifications', 'notification_deliveries', 'activity_logs',
            'stock_ledger_entries', 'store_docs', 'store_doc_items', 'rooms', 'guests', 'users',
        ];

        $counts = [];

        foreach ($tables as $table) {
            try {
                $counts[$table] = \Illuminate\Support\Facades\DB::table($table)->count();
            } catch (\Throwable $e) {
                $counts[$table] = 'error: ' . $e->getMessage();
            }
        }

        $timings = [];

        // Each one stands alone — a bad query in one must not blank out the
        // timings either side of it, which is the whole point of this route.
        $time = function (string $label, \Closure $work) use (&$timings) {
            $s = microtime(true);

            try {
                $work();
                $timings[$label] = round((microtime(true) - $s) * 1000, 1) . ' ms';
            } catch (\Throwable $e) {
                $timings[$label] = 'error: ' . $e->getMessage();
            }
        };

        $time('trivial_db_query', fn () => \Illuminate\Support\Facades\DB::table('module')->count());
        $time('sidebar_menu', fn () => \App\Helpers\Helper::sideMenus());
        $time('inhouse_checkins_query', fn () => \App\Models\FrontOffice\CheckIn::query()
            ->where('branch_id', 1)->inHouse()->with('room')->orderBy('folio_no')->get());

        $logPath = storage_path('logs/laravel.log');

        return response()->json([
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
                'ini_loaded_file' => php_ini_loaded_file(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_loaded' => extension_loaded('Zend OPcache'),
                'opcache_enabled' => $opcache['opcache_enabled'] ?? null,
                'opcache_hit_rate' => isset($opcache['opcache_statistics']['opcache_hit_rate'])
                    ? round($opcache['opcache_statistics']['opcache_hit_rate'], 1) : null,
                'xdebug_loaded' => extension_loaded('xdebug'),
                'xdebug_mode' => extension_loaded('xdebug') ? (ini_get('xdebug.mode') ?: '(default)') : null,
            ],
            'laravel' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'session_driver' => config('session.driver'),
                'queue_connection' => config('queue.default'),
                'cache_driver' => config('cache.default'),
            ],
            'table_row_counts' => $counts,
            'storage_file_counts' => [
                'sessions' => $countFiles(storage_path('framework/sessions')),
                'compiled_views' => $countFiles(storage_path('framework/views')),
                'file_cache' => $countFiles(storage_path('framework/cache/data')),
            ],
            'log_file' => [
                'exists' => file_exists($logPath),
                'bytes' => file_exists($logPath) ? filesize($logPath) : 0,
                'megabytes' => file_exists($logPath) ? round(filesize($logPath) / 1048576, 2) : 0,
            ],
            'timings_ms' => $timings,
            'this_diagnostic_took_ms' => round((microtime(true) - $t0) * 1000, 1),
        ], 200, [], JSON_PRETTY_PRINT);
    });

    /*
     * TEMP DIAGNOSTIC — read-only. Guest WhatsApp text is arriving but the
     * PDF link is not. Prints the resolved PMS_PUBLIC_URL and the last 10
     * guest WhatsApp delivery rows so a gateway refusal, if there is one, is
     * visible without opening the database.
     *
     * This route used to also have this server place a live HTTPS call to
     * its own public tunnel URL. That test was wrong and has been removed:
     * `php artisan serve` on Windows handles one request at a time (there is
     * no pcntl fork here), so a call FROM this route TO this same server's
     * own public address can never come back — the one worker is stuck
     * waiting on the outbound call, so the inbound leg of that same call has
     * nothing to answer it. It timed out every time regardless of whether
     * the tunnel was actually healthy, which is not a real answer. Testing
     * reachability from outside this process — a browser on another
     * connection, or the real WhatsApp send — is the only way that is not
     * self-defeating on this server.
     */
    Route::get('diag/pdf-link', function () {
        $base = \App\Support\GuestDocument::base();
        $reachableByConfig = \App\Support\GuestDocument::reachable();
        $why = \App\Support\GuestDocument::unreachableBecause();

        $deliveries = \Illuminate\Support\Facades\DB::table('notification_deliveries')
            ->where('channel', 'whatsapp')
            ->where('audience', 'guest')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'event', 'target', 'status', 'error', 'created_at']);

        return response()->json([
            'config' => [
                'PMS_PUBLIC_URL_resolved_to' => $base,
                'app_thinks_reachable_by_url_shape' => $reachableByConfig,
                'reason_if_not' => $why,
            ],
            'note' => 'Open ' . $base . '/ directly in a browser to test reachability — '
                . 'this route cannot test itself (see comment above the route in routes/web.php).',
            'last_10_guest_whatsapp_deliveries' => $deliveries,
        ], 200, [], JSON_PRETTY_PRINT);
    });

    /*
     * TEMP DIAGNOSTIC — has a real side effect: visiting it with ?go=yes
     * sends one real WhatsApp message. Everything else on this page
     * (including diag/pdf-link above) only reads.
     *
     * The investigation this replaces (two earlier, now-deleted routes) found
     * that Chatway needs its own send-file endpoint for an attachment, not
     * send-msg with an extra parameter — see the long comment on
     * WhatsApp::chatwaySendFile(). This route calls WhatsApp::send() itself,
     * the exact method every real guest message goes through, so a good
     * result here means the real thing is fixed, not just a test harness.
     * Delete this route once that is confirmed.
     */
    Route::get('diag/chatway-attach-test', function () {
        if (request()->query('go') !== 'yes') {
            return response()->json([
                'note' => 'Add ?go=yes to actually send. This fires one REAL WhatsApp message, '
                    . 'through the exact same WhatsApp::send() every real guest message uses, to '
                    . 'the branch\'s own number, with a PDF attached.',
                'will_send_to' => '7062313341',
            ], 200, [], JSON_PRETTY_PRINT);
        }

        set_time_limit(60);

        $to = '7062313341';
        $token = 'ej8PngnBhqecrBagV8G4QtB9cbz9LMY0QLVubs27';
        $pdfUrl = \App\Support\GuestDocument::base() . '/guest-doc/' . $token . '.pdf';

        try {
            $reply = \App\Support\WhatsApp::send(
                $to,
                'TEST — real WhatsApp::send() path — is a PDF attached to THIS message?',
                $pdfUrl,
                'Hotel-Bill.pdf'
            );
            $result = ['ok' => true, 'reply' => $reply];
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }

        return response()->json([
            'sent_to' => $to,
            'pdf_url_used' => $pdfUrl,
            'result' => $result,
            'next_step' => 'Open WhatsApp for ' . $to . ' and check the newest message — '
                . 'does it show a PDF this time?',
        ], 200, [], JSON_PRETTY_PRINT);
    });

    /*
     * TEMP DIAGNOSTIC — has a real side effect: signals the queue worker to
     * restart once it finishes whatever it is doing right now. This is the
     * same signal `php artisan queue:restart` sends by hand; queue-worker.bat's
     * own loop is what actually relaunches it, exactly as it already does
     * every ~30 minutes on its own (see --max-time in queue-worker.bat).
     *
     * Added so a fresh PMS_PUBLIC_URL — written the moment
     * `php artisan pms:tunnel-watch` sees the Cloudflare tunnel reconnect
     * with a new address — reaches the running worker immediately, instead
     * of guest WhatsApp sends silently using a dead link until the worker's
     * next restart. Delete alongside the other diag/* routes once the
     * tunnel is confirmed to keep itself healthy without checking on it.
     */
    Route::get('diag/queue-restart', function () {
        \Illuminate\Support\Facades\Artisan::call('queue:restart');

        return response()->json([
            'note' => 'Restart signal sent. The PMS - Queue Worker window will finish its '
                . 'current job (if any), print "Queue worker stopped", and relaunch itself '
                . 'within a few seconds.',
        ], 200, [], JSON_PRETTY_PRINT);
    });

    /*
     * One family, several rooms, one bill.
     *
     * Same permission key as the single-room screen: a cashier who may settle
     * one room may settle the family, and one who may not, may not. The folio
     * number is the family — three rooms checked in together already share one.
     */
    Route::controller(GroupBillController::class)->prefix('front-office')->name('front-office.check-out-guest')
        ->group(function () {
            Route::get('check-out-guest/folio/{folio}', 'show')->name('.folio')
                ->middleware('permission:front-office/check-out-guest,view');
            Route::post('check-out-guest/folio/{folio}/checkout', 'checkout')->name('.group-checkout')
                ->middleware('permission:front-office/check-out-guest,add');
            Route::get('check-out-guest/group/{group}', 'invoice')->name('.group-invoice')
                ->middleware('permission:front-office/check-out-guest,view');
        });

    // Pre Reg Card — the registration card the guest signs
    Route::controller(PreRegCardController::class)->prefix('front-office')->name('front-office.')
        // whereNumber keeps `{reservation}` from swallowing `blank`.
        ->whereNumber('reservation')
        ->middleware('permission:front-office/pre-reg-card,view')
        ->group(function () {
            Route::get('pre-reg-card', 'index')->name('pre-reg-card');
            Route::get('pre-reg-card/blank', 'blank')->name('pre-reg-card.blank');
            Route::get('pre-reg-card/{reservation}', 'show')->name('pre-reg-card.show');
        });

    Route::controller(CheckInController::class)->prefix('front-office')->name('front-office.')
        ->group(function () {
            Route::get('check-in-guest', 'create')->name('check-in-guest')
                ->middleware('permission:front-office/check-in-guest,add');
            Route::get('check-in-guest/rooms', 'rooms')->name('check-in-guest.rooms')
                ->middleware('permission:front-office/check-in-guest,add');
            Route::post('check-in-guest', 'store')->name('check-in-guest.store')
                ->middleware('permission:front-office/check-in-guest,add');

            Route::get('check-in-guest-details', 'index')->name('check-in-details')
                ->middleware('permission:front-office/check-in-guest-details,view');
            Route::post('check-in-guest-details/{checkIn}/undo', 'undo')->name('check-in-details.undo')
                ->whereNumber('checkIn')
                ->middleware('permission:front-office/check-in-guest-details,delete');
        });

    /*
    |----------------------------------------------------------------------
    | Reports
    |----------------------------------------------------------------------
    | Eighteen reports, one permission key. A hotel that trusts somebody with
    | the occupancy report trusts them with the arrivals list, and eighteen
    | rows on the permission matrix is a matrix nobody would tick correctly.
    |
    | `whereIn` on {report} keeps an unknown slug a clean 404 rather than a
    | stack trace, and means the registry in config/reports.php is the only
    | list of what exists.
    */
    Route::controller(ReportsController::class)->prefix('reports')->name('reports.')
        ->whereIn('report', array_keys(config('reports.reports')))
        ->middleware('permission:reports,view')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{report}', 'show')->name('show');
            Route::get('{report}/export', 'export')->name('export');
        });

    /*
    |----------------------------------------------------------------------
    | Notifications
    |----------------------------------------------------------------------
    | The bell is open to everybody who can sign in — a notification is not a
    | screen, and a night porter who can only see the Room Calendar still has
    | to be told the fire panel went off. The settings screen behind it is
    | administration, and is gated.
    */
    Route::controller(NotificationController::class)->group(function () {
        Route::get('notifications', 'index')->name('notifications.index');
        Route::get('notifications/feed', 'feed')->name('notifications.feed');
        Route::post('notifications/read', 'read')->name('notifications.read');

        Route::get('notification-settings', 'settings')->name('notification-settings')
            ->middleware('permission:notification-settings,view');
        Route::post('notification-settings', 'saveSettings')->name('notification-settings.save')
            ->middleware('permission:notification-settings,edit');
        Route::post('notification-settings/test', 'test')->name('notification-settings.test')
            ->middleware('permission:notification-settings,edit');
        // Asks the gateway whether it knows this account. Sends nobody a
        // message, so it is safe to press as often as somebody likes.
        Route::post('notification-settings/probe', 'probe')->name('notification-settings.probe')
            ->middleware('permission:notification-settings,edit');
        // The same, for the mail server: it logs in and hangs up without
        // sending anything, so nobody is emailed.
        Route::post('notification-settings/probe-mail', 'probeMail')->name('notification-settings.probe-mail')
            ->middleware('permission:notification-settings,edit');
    });

    /*
    |----------------------------------------------------------------------
    | Accounts, and Pool / Hall / Car
    |----------------------------------------------------------------------
    | Two whole modules, kept in their own files so this one stays readable.
    | They are required here rather than registered in a service provider so
    | that they inherit this group's `auth` middleware — and so that anybody
    | reading this file can see exactly where they come in.
    */
    require __DIR__ . '/accounting.php';
    require __DIR__ . '/facilities.php';

    /*
    |----------------------------------------------------------------------
    | Administration
    |----------------------------------------------------------------------
    | Every route below is gated by the same `submodule.url` you tick on the
    | Users screen, so what the sidebar shows and what a route allows can
    | never drift apart. Administrators (role 1) bypass all of it.
    */

    /** Map the seven resource actions onto view / add / edit / delete. */
    $guard = fn (string $url) => [
        ['index'],            "permission:{$url},view",
        ['create', 'store'],  "permission:{$url},add",
        ['edit', 'update'],   "permission:{$url},edit",
        ['destroy'],          "permission:{$url},delete",
    ];

    $resource = function (string $uri, string $controller) use ($guard) {
        $route = Route::resource($uri, $controller)->except('show');

        foreach (array_chunk($guard($uri), 2) as [$methods, $middleware]) {
            $route->middlewareFor($methods, $middleware);
        }

        return $route;
    };

    $resource('role', RoleController::class);

    $resource('users', UserController::class);
    Route::post('user-status', [UserController::class, 'changeStatus'])
        ->name('user-status')->middleware('permission:users,edit');

    $resource('viewBranch', BranchController::class);
    Route::post('branch/status/{branch}', [BranchController::class, 'updateStatus'])
        ->name('branch-status')->middleware('permission:viewBranch,edit');

    // Modules & sub-modules — the source the sidebar and the matrix both read
    Route::controller(ModuleController::class)->group(function () {
        Route::get('modules', 'index')->name('modules.index')->middleware('permission:modules,view');
        Route::post('modules', 'storeModule')->name('modules.store')->middleware('permission:modules,add');
        Route::put('modules/{module}', 'updateModule')->name('modules.update')->middleware('permission:modules,edit');
        Route::delete('modules/{module}', 'destroyModule')->name('modules.destroy')->middleware('permission:modules,delete');

        Route::post('submodules', 'storeSubmodule')->name('submodules.store')->middleware('permission:modules,add');
        Route::put('submodules/{submodule}', 'updateSubmodule')->name('submodules.update')->middleware('permission:modules,edit');
        Route::delete('submodules/{submodule}', 'destroySubmodule')->name('submodules.destroy')->middleware('permission:modules,delete');
    });
});

// TEMPORARY — one-shot repair for the Reservation Calendar Monthly report
// (fixes a side effect of the earlier DevSeedController demo-data run).
// Self-guarded (key + non-production + localhost) in the controller itself,
// so it sits outside the auth group on purpose. Remove this route once run.
Route::get('dev/cleanup-seed-overlap', [DevSeedCleanupController::class, 'run'])
    ->name('dev.cleanup-seed-overlap');

// TEMPORARY — one-shot restore of the demo data DevSeedController and
// DevSeedPosController originally wrote (since genuinely deleted from the
// database — see DevSeedRestoreController's own docblock for the full
// story). Self-guarded (key + non-production + localhost) in the controller
// itself, so — like the cleanup route above — it sits outside the auth group
// on purpose. Remove this route (and the controller) once it has been run.
Route::get('dev/restore-seed', [DevSeedRestoreController::class, 'run'])
    ->name('dev.restore-seed');
