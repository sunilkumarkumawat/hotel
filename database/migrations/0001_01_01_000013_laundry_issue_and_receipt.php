<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring an older database up to the laundry issue/receipt shape.
 *
 * `hk_issues` and `hk_receipts` used to be one row per item. A laundry note is
 * a document — one note, one vendor, fifteen kinds of linen on it — so
 * 0001_01_01_000005 now creates them with a header and lines. This migration
 * is only for a database created before that change; nothing had ever written
 * to those two tables, so rebuilding them loses nothing.
 *
 * **Every guard here starts by asking whether a table exists**, so on a fresh
 * install (where 000005 already made the new shape) this whole file is a
 * no-op, and the MySQL dump generator — which answers "no" to every schema
 * question — records nothing from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // hk_items is the marker for "this database predates the change".
        if (! Schema::hasTable('hk_items')) {
            return;
        }

        if (! Schema::hasColumn('hk_items', 'std_rate')) {
            Schema::table('hk_items', function (Blueprint $table) {
                $table->decimal('std_rate', 12, 2)->default(0)->after('reorder_level');
                $table->decimal('exp_rate', 12, 2)->default(0)->after('std_rate');
            });
        }

        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->string('mobile', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('gst_no', 20)->nullable();
                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        // The old per-item shape is recognisable by its hk_item_id column.
        if (Schema::hasColumn('hk_issues', 'hk_item_id')) {
            Schema::dropIfExists('hk_issues');

            Schema::create('hk_issues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('issue_no', 40);
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->date('issue_date');
                $table->decimal('total_qty', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'issue_no']);
            });
        }

        if (! Schema::hasTable('hk_issue_items')) {
            Schema::create('hk_issue_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hk_issue_id')->index();
                $table->unsignedBigInteger('hk_item_id')->index();
                $table->decimal('prev_qty', 12, 2)->default(0);
                $table->decimal('std_qty', 12, 2)->default(0);
                $table->decimal('exp_qty', 12, 2)->default(0);
                $table->decimal('rewash_qty', 12, 2)->default(0);
                $table->decimal('std_rate', 12, 2)->default(0);
                $table->decimal('exp_rate', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('hk_receipts', 'hk_item_id')) {
            Schema::dropIfExists('hk_receipts');

            Schema::create('hk_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('receipt_no', 40);
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->date('receive_date');
                $table->decimal('total_qty', 12, 2)->default(0);
                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'receipt_no']);
            });
        }

        if (! Schema::hasTable('hk_receipt_items')) {
            Schema::create('hk_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hk_receipt_id')->index();
                $table->unsignedBigInteger('hk_item_id')->index();
                $table->decimal('pending_qty', 12, 2)->default(0);
                $table->decimal('received_qty', 12, 2)->default(0);
                $table->decimal('damaged_qty', 12, 2)->default(0);
                $table->decimal('missing_qty', 12, 2)->default(0);
                $table->string('remark')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Nothing to undo.
     *
     * Rolling back would mean putting a shape nobody can use back — and
     * 000005's own `down()` already drops all of these tables.
     */
    public function down(): void {}
};
