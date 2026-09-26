<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House keeping — room condition, linen stock, blocks, work orders and
 * anything a guest left behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hk_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');                                  // towel, bedsheet, soap
            $table->string('unit', 20)->default('pcs');
            $table->decimal('opening_qty', 12, 2)->default(0);
            $table->decimal('current_qty', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            // The laundry contract rate per piece. Standard is the everyday
            // wash; express is the one the guest is waiting for.
            $table->decimal('std_rate', 12, 2)->default(0);
            $table->decimal('exp_rate', 12, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        // Who the linen goes out to. Kept general rather than "laundry"
        // because the same list serves Accounting → Vendor Payment.
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

        Schema::create('hk_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('room_id')->index();
            $table->date('log_date');
            $table->enum('status', ['clean', 'dirty', 'inspected', 'out_of_order'])->default('dirty');
            $table->unsignedBigInteger('attended_by')->nullable();   // users.user_id
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /*
         * An issue is a document, not a line: one note goes out to the laundry
         * with fifteen kinds of linen on it, and the hotel needs the note back
         * with the same number on it. Header here, lines in hk_issue_items.
         */
        Schema::create('hk_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            // Unique per branch: two clerks writing a note at the same
            // moment must not both take ISS-1-0007.
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

        Schema::create('hk_issue_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hk_issue_id')->index();
            $table->unsignedBigInteger('hk_item_id')->index();
            // What this vendor still owed on this item when the note was
            // written. Stored, not recomputed: it is what the two sides agreed
            // on that day, and a later receipt must not rewrite history.
            $table->decimal('prev_qty', 12, 2)->default(0);
            $table->decimal('std_qty', 12, 2)->default(0);
            $table->decimal('exp_qty', 12, 2)->default(0);
            $table->decimal('rewash_qty', 12, 2)->default(0);
            $table->decimal('std_rate', 12, 2)->default(0);
            $table->decimal('exp_rate', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

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

        Schema::create('hk_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hk_receipt_id')->index();
            $table->unsignedBigInteger('hk_item_id')->index();
            $table->decimal('pending_qty', 12, 2)->default(0);   // owed when the note was written
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->decimal('damaged_qty', 12, 2)->default(0);   // came back torn — off the books
            $table->decimal('missing_qty', 12, 2)->default(0);   // never came back — off the books
            $table->string('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('room_id')->index();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason')->nullable();
            $table->enum('status', ['blocked', 'released'])->default('blocked')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /*
         * A maintenance job: what is broken, where, who is fixing it and by
         * when. `room_id` is nullable because a lift or a lobby is a job with
         * no room number.
         */
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('order_no', 40);
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->string('category', 40)->default('other');        // config('pms.work_order_categories')
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->unsignedBigInteger('assigned_to')->nullable();   // users.user_id
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_on')->nullable();
            // The block this job took the room off sale with, if any, so
            // closing the job can offer to put the room back.
            $table->unsignedBigInteger('room_block_id')->nullable();
            $table->enum('status', ['open', 'in_progress', 'done', 'cancelled'])->default('open')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'order_no']);
        });

        Schema::create('lost_and_founds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('item_name');
            $table->date('found_date');
            $table->string('found_by')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_mobile', 20)->nullable();
            $table->enum('status', ['stored', 'returned', 'disposed'])->default('stored')->index();
            $table->date('returned_on')->nullable();
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'lost_and_founds', 'work_orders', 'room_blocks',
            'hk_receipt_items', 'hk_receipts', 'hk_issue_items', 'hk_issues',
            'hk_status_logs', 'hk_items', 'vendors',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
