<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock moving between outlets, through the same store_docs/stock_ledger the
 * other five kinds already use — see 0001_01_01_000028_store_and_inventory.php
 * for why one table instead of six.
 *
 * Two kinds, not one, because a transfer is two branches agreeing on the same
 * fact at two different moments: `transfer_out` is the source outlet saying
 * "this left", posted at the source branch_id and moving stock out there;
 * `transfer_in` is the destination outlet saying "this arrived", posted at
 * its own branch_id (the destination) and moving stock in there, linked back
 * via the existing `against_id` exactly the way a GRN points at its PO. What
 * was sent and what was actually received are allowed to differ — same as a
 * short delivery against a purchase order — because a box can go missing
 * between two kitchens exactly as easily as between a hotel and a vendor.
 *
 * `to_branch_id` is the one new column: which outlet a transfer_out is bound
 * for. A transfer_in needs no equivalent `from_branch_id` — its source is
 * whichever branch owns the transfer_out it is against.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_docs')) {
            return;
        }

        // No ->index() here: `kind` was already indexed when store_docs was
        // first created, and change() only needs to touch — the enum's list
        // of allowed values, never its index. Re-declaring the index is what
        // produced "Duplicate key name 'store_docs_kind_index'".
        Schema::table('store_docs', function (Blueprint $table) {
            $table->enum('kind', ['po', 'grn', 'issue', 'adjustment', 'wastage', 'transfer_out', 'transfer_in'])
                ->change();
        });

        if (! Schema::hasColumn('store_docs', 'to_branch_id')) {
            Schema::table('store_docs', function (Blueprint $table) {
                // Only meaningful on a transfer_out: the outlet it is bound for.
                $table->unsignedBigInteger('to_branch_id')->nullable()->index()->after('branch_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_docs')) {
            return;
        }

        if (Schema::hasColumn('store_docs', 'to_branch_id')) {
            Schema::table('store_docs', function (Blueprint $table) {
                $table->dropColumn('to_branch_id');
            });
        }

        Schema::table('store_docs', function (Blueprint $table) {
            $table->enum('kind', ['po', 'grn', 'issue', 'adjustment', 'wastage'])->change();
        });
    }
};
