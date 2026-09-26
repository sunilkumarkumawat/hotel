<?php

/*
|--------------------------------------------------------------------------
| Accounts
|--------------------------------------------------------------------------
| Required from inside routes/web.php's auth group.
|
| The six voucher screens share one controller and are told apart by a route
| default rather than a URI segment — see VoucherController::kinds(). Route
| names mirror the url with dots, so `accounting/vendor-payment` is
| `accounting.vendor-payment`.
*/

use App\Http\Controllers\Accounting\BookController;
use App\Http\Controllers\Accounting\GroupController;
use App\Http\Controllers\Accounting\LedgerController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\VoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Masters
|--------------------------------------------------------------------------
*/

Route::controller(GroupController::class)->prefix('accounting')->whereNumber('group')->group(function () {
    Route::get('group', 'index')->name('accounting.group')
        ->middleware('permission:accounting/group,view');
    Route::post('group', 'save')->name('accounting.group.save')
        ->middleware('permission:accounting/group,add');
    Route::post('group/{group}/toggle', 'toggle')->name('accounting.group.toggle')
        ->middleware('permission:accounting/group,delete');
});

Route::controller(LedgerController::class)->prefix('accounting')->whereNumber('ledger')->group(function () {
    Route::get('ledger', 'index')->name('accounting.ledger')
        ->middleware('permission:accounting/ledger,view');
    Route::post('ledger', 'save')->name('accounting.ledger.save')
        ->middleware('permission:accounting/ledger,add');
    Route::post('ledger/{ledger}/toggle', 'toggle')->name('accounting.ledger.toggle')
        ->middleware('permission:accounting/ledger,delete');
    Route::delete('ledger/{ledger}', 'destroy')->name('accounting.ledger.destroy')
        ->middleware('permission:accounting/ledger,delete');
});

/*
|--------------------------------------------------------------------------
| The six screens that write vouchers
|--------------------------------------------------------------------------
*/

foreach (array_keys(VoucherController::kinds()) as $voucherKind) {
    $voucherUrl = 'accounting/' . $voucherKind;

    Route::get($voucherUrl, [VoucherController::class, 'index'])
        ->defaults('kind', $voucherKind)
        ->name('accounting.' . $voucherKind)
        ->middleware("permission:{$voucherUrl},view");

    Route::post($voucherUrl, [VoucherController::class, 'store'])
        ->defaults('kind', $voucherKind)
        ->name('accounting.' . $voucherKind . '.store')
        ->middleware("permission:{$voucherUrl},add");

    // Cancelling is the only way to undo a voucher, so it answers to delete.
    Route::post($voucherUrl . '/{voucher}/cancel', [VoucherController::class, 'cancel'])
        ->defaults('kind', $voucherKind)
        ->whereNumber('voucher')
        ->name('accounting.' . $voucherKind . '.cancel')
        ->middleware("permission:{$voucherUrl},delete");
}

/*
|--------------------------------------------------------------------------
| The books you read
|--------------------------------------------------------------------------
*/

Route::controller(BookController::class)->prefix('accounting')->group(function () {
    Route::get('day-book', 'dayBook')->name('accounting.day-book')
        ->middleware('permission:accounting/day-book,view');

    Route::get('cash-book', 'cashBook')->name('accounting.cash-book')
        ->middleware('permission:accounting/cash-book,view');

    Route::get('bank-book', 'bankBook')->name('accounting.bank-book')
        ->middleware('permission:accounting/bank-book,view');

    Route::get('ledger-statement', 'statement')->name('accounting.ledger-statement')
        ->middleware('permission:accounting/ledger-statement,view');
    Route::get('ledger-statement/export', 'exportStatement')->name('accounting.ledger-statement.export')
        ->middleware('permission:accounting/ledger-statement,view');

    Route::get('all-receipt', 'allReceipts')->name('accounting.all-receipt')
        ->middleware('permission:accounting/all-receipt,view');
});

Route::controller(ReportController::class)->prefix('accounting')->group(function () {
    Route::get('trial-balance', 'trialBalance')->name('accounting.trial-balance')
        ->middleware('permission:accounting/trial-balance,view');
    Route::get('trial-balance/export', 'exportTrialBalance')->name('accounting.trial-balance.export')
        ->middleware('permission:accounting/trial-balance,view');

    Route::get('profit-loss', 'profitAndLoss')->name('accounting.profit-loss')
        ->middleware('permission:accounting/profit-loss,view');
});
