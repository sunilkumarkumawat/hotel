<?php

/*
|--------------------------------------------------------------------------
| Pool, Banquet Hall, and the Car
|--------------------------------------------------------------------------
| Required from inside routes/web.php's auth group, so everything here is
| already behind a login. Each route carries the permission key of the
| sub-module it belongs to — the same string the sidebar and the Users screen
| use, so a link a user cannot see is a route they cannot reach either.
|
| `whereNumber` on every {id} is not decoration: without it `pool/bookings/new`
| would be matched by `pool/bookings/{booking}/edit`'s sibling patterns and the
| Add screen would 404 the day somebody adds a route beside it.
*/

use App\Http\Controllers\Facility\FacilitySetupController;
use App\Http\Controllers\Facility\HallController;
use App\Http\Controllers\Facility\ParkingController;
use App\Http\Controllers\Facility\PoolController;
use App\Http\Controllers\Facility\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pool
|--------------------------------------------------------------------------
*/

Route::controller(PoolController::class)->prefix('pool')->whereNumber('booking')->group(function () {
    Route::get('bookings', 'index')->name('pool.bookings')
        ->middleware('permission:pool/bookings,view');

    Route::get('bookings/new', 'form')->name('pool.bookings.create')
        ->middleware('permission:pool/bookings,add');
    Route::post('bookings', 'save')->name('pool.bookings.store')
        ->middleware('permission:pool/bookings,add');

    Route::get('bookings/{booking}/edit', 'form')->name('pool.bookings.edit')
        ->middleware('permission:pool/bookings,edit');
    Route::put('bookings/{booking}', 'save')->name('pool.bookings.update')
        ->middleware('permission:pool/bookings,edit');
    Route::post('bookings/{booking}/status', 'status')->name('pool.bookings.status')
        ->middleware('permission:pool/bookings,edit');

    Route::post('bookings/{booking}/cancel', 'cancel')->name('pool.bookings.cancel')
        ->middleware('permission:pool/bookings,delete');

    Route::get('calendar', 'calendar')->name('pool.calendar')
        ->middleware('permission:pool/calendar,view');
});

/*
|--------------------------------------------------------------------------
| Banquet Hall
|--------------------------------------------------------------------------
*/

Route::controller(HallController::class)->prefix('hall')->whereNumber('booking')->group(function () {
    Route::get('bookings', 'index')->name('hall.bookings')
        ->middleware('permission:hall/bookings,view');

    Route::get('bookings/new', 'form')->name('hall.bookings.create')
        ->middleware('permission:hall/bookings,add');
    Route::post('bookings', 'save')->name('hall.bookings.store')
        ->middleware('permission:hall/bookings,add');

    Route::get('bookings/{booking}/edit', 'form')->name('hall.bookings.edit')
        ->middleware('permission:hall/bookings,edit');
    Route::put('bookings/{booking}', 'save')->name('hall.bookings.update')
        ->middleware('permission:hall/bookings,edit');
    Route::post('bookings/{booking}/status', 'status')->name('hall.bookings.status')
        ->middleware('permission:hall/bookings,edit');

    Route::post('bookings/{booking}/cancel', 'cancel')->name('hall.bookings.cancel')
        ->middleware('permission:hall/bookings,delete');

    Route::get('calendar', 'calendar')->name('hall.calendar')
        ->middleware('permission:hall/calendar,view');
});

/*
|--------------------------------------------------------------------------
| The car park
|--------------------------------------------------------------------------
| Taking a car out is an `edit`, not an `add`: it changes a ticket that already
| exists, and it is the moment the only money on this screen is decided.
*/

Route::controller(ParkingController::class)->prefix('car')->whereNumber('record')->group(function () {
    Route::get('parking', 'index')->name('car.parking')
        ->middleware('permission:car/parking,view');
    Route::post('parking', 'checkIn')->name('car.parking.in')
        ->middleware('permission:car/parking,add');
    Route::post('parking/{record}/out', 'checkOut')->name('car.parking.out')
        ->middleware('permission:car/parking,edit');
    Route::post('parking/{record}/cancel', 'cancel')->name('car.parking.cancel')
        ->middleware('permission:car/parking,delete');
});

/*
|--------------------------------------------------------------------------
| Pickup & drop
|--------------------------------------------------------------------------
*/

Route::controller(TripController::class)->prefix('car')->whereNumber('trip')->group(function () {
    Route::get('trips', 'index')->name('car.trips')
        ->middleware('permission:car/trips,view');

    Route::get('trips/new', 'form')->name('car.trips.create')
        ->middleware('permission:car/trips,add');
    Route::post('trips', 'save')->name('car.trips.store')
        ->middleware('permission:car/trips,add');

    Route::get('trips/{trip}/edit', 'form')->name('car.trips.edit')
        ->middleware('permission:car/trips,edit');
    Route::put('trips/{trip}', 'save')->name('car.trips.update')
        ->middleware('permission:car/trips,edit');
    Route::post('trips/{trip}/status', 'status')->name('car.trips.status')
        ->middleware('permission:car/trips,edit');
});

/*
|--------------------------------------------------------------------------
| The four setup lists
|--------------------------------------------------------------------------
| Pools, halls, bays and cars are one screen four times, so they are four
| routes built from the same description the controller works off. The slug
| carries a slash, which a route parameter cannot, so it is passed as a route
| default instead — `defaults()` reaches the controller exactly like a
| parameter would, and the URL stays the clean `pool/setup`.
*/

foreach (array_keys(FacilitySetupController::screens()) as $facilityScreen) {
    $names = FacilitySetupController::routeNames($facilityScreen);

    Route::get($facilityScreen, [FacilitySetupController::class, 'index'])
        ->defaults('screen', $facilityScreen)
        ->name($names['index'])
        ->middleware("permission:{$facilityScreen},view");

    Route::post($facilityScreen, [FacilitySetupController::class, 'save'])
        ->defaults('screen', $facilityScreen)
        ->name($names['save'])
        ->middleware("permission:{$facilityScreen},add");

    Route::post($facilityScreen . '/{id}/toggle', [FacilitySetupController::class, 'toggle'])
        ->defaults('screen', $facilityScreen)
        ->whereNumber('id')
        ->name($names['toggle'])
        ->middleware("permission:{$facilityScreen},delete");
}
