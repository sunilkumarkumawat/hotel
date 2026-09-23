<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring an older database up to the full work order shape.
 *
 * `work_orders` started as a title, a priority and a due date. A real job card
 * also carries its own number, what trade it is, when the work starts and ends,
 * and — when the room had to come off sale for it — the block that did that.
 * 0001_01_01_000005 now creates all of it; this file is for a database made
 * before that change.
 *
 * **Every guard here starts by asking whether a table exists**, so on a fresh
 * install this whole file is a no-op, and the MySQL dump generator — which
 * answers "no" to every schema question — records nothing from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        if (! Schema::hasColumn('work_orders', 'order_no')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->string('order_no', 40)->default('')->after('branch_id');
                $table->string('category', 40)->default('other')->after('room_id');
                $table->date('start_date')->nullable()->after('assigned_to');
                $table->time('start_time')->nullable()->after('start_date');
                $table->date('end_date')->nullable()->after('start_time');
                $table->unsignedBigInteger('room_block_id')->nullable()->after('completed_on');
            });

            $this->numberExistingOrders();

            Schema::table('work_orders', function (Blueprint $table) {
                $table->unique(['branch_id', 'order_no']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders') || ! Schema::hasColumn('work_orders', 'order_no')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'order_no']);
            $table->dropColumn([
                'order_no', 'category', 'start_date', 'start_time', 'end_date', 'room_block_id',
            ]);
        });
    }

    /**
     * Give jobs that already exist a number.
     *
     * They were written before numbering existed, so they all have the empty
     * default — and the unique index that follows would refuse to be created
     * over two blanks. Numbered per branch, oldest first, in the same
     * WO-<branch>-0001 shape the screen uses.
     */
    private function numberExistingOrders(): void
    {
        $rows = DB::table('work_orders')->orderBy('id')->get(['id', 'branch_id']);

        $seen = [];

        foreach ($rows as $row) {
            $branch = (int) $row->branch_id;
            $seen[$branch] = ($seen[$branch] ?? 0) + 1;

            DB::table('work_orders')->where('id', $row->id)->update([
                'order_no' => 'WO-' . $branch . '-' . str_pad((string) $seen[$branch], 4, '0', STR_PAD_LEFT),
            ]);
        }
    }
};
