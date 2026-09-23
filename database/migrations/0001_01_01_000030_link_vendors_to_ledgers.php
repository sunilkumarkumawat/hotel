<?php

use App\Models\Master\Vendor;
use App\Support\VendorLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column that makes a vendor and its ledger the same row.
 *
 * Masters → Vendor is what Purchase Orders, Goods Receipts and the laundry
 * issue note point at; Accounting → Vendor Payment pays against a Ledger
 * under Sundry Creditors. Nothing tied the two together, so the two lists
 * could only be kept in sync by hand — and Vendor Payment's dropdown ended
 * up pointing at neither of them (see the account-group/ledger id mix-up
 * fixed alongside this).
 *
 * This adds the link, then backfills it for every vendor already on file —
 * the same rule a new vendor gets from here on, run once against the
 * backlog. See App\Support\VendorLedger, which both this migration and
 * Vendor::booted() call so the two never drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        if (! Schema::hasColumn('vendors', 'ledger_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unsignedBigInteger('ledger_id')->nullable()->after('id')->index();
            });
        }

        Vendor::query()->whereNull('ledger_id')->each(function (Vendor $vendor) {
            VendorLedger::sync($vendor);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'ledger_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('ledger_id');
            });
        }
    }
};
