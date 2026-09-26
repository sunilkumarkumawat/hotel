<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the bill add up to the booking.
 *
 * Two things a guest was quoted at booking never reached the folio:
 *
 * 1. The **plan charge**. A booking on AP is priced at room rent + the plan's
 *    charge per night, but only the bare rent was stored on the row, so the
 *    checkout screen re-priced the night without the meals. Storing the plan's
 *    charge on the row also stops the booking from silently re-pricing itself
 *    if somebody edits the plan master months later.
 *
 * 2. The **services** taken with the booking — an airport transfer, an extra
 *    bed. They sat on the reservation and were never charged at checkout.
 *    `reservation_service_id` is what makes posting them idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_rooms', function (Blueprint $table) {
            $table->decimal('plan_charge', 12, 2)->default(0)->after('discount');
        });

        Schema::table('check_ins', function (Blueprint $table) {
            $table->decimal('plan_charge', 12, 2)->default(0)->after('discount');
        });

        Schema::table('folio_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_service_id')->nullable()->after('service_id');
        });

        // "Has this already been charged" has to survive the charge being
        // taken off again — a guest who cancels their airport transfer at the
        // desk must not have it posted back the next time a screen opens.
        Schema::table('reservation_services', function (Blueprint $table) {
            $table->timestamp('posted_at')->nullable()->after('remark');
        });

        $this->backfillPlanCharges();
    }

    public function down(): void
    {
        Schema::table('reservation_services', function (Blueprint $table) {
            $table->dropColumn('posted_at');
        });

        Schema::table('folio_charges', function (Blueprint $table) {
            $table->dropColumn('reservation_service_id');
        });

        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropColumn('plan_charge');
        });

        Schema::table('reservation_rooms', function (Blueprint $table) {
            $table->dropColumn('plan_charge');
        });
    }

    /**
     * Give bookings that already exist the charge their plan carries today.
     *
     * Done one plan at a time rather than as a join, because the same
     * statement has to work on SQLite and MySQL. On a fresh install there are
     * no plans yet and this does nothing.
     */
    private function backfillPlanCharges(): void
    {
        if (! Schema::hasTable('plan_type')) {
            return;
        }

        foreach (DB::table('plan_type')->pluck('charge', 'id') as $id => $charge) {
            if ((float) $charge <= 0) {
                continue;
            }

            DB::table('reservation_rooms')->where('plan_type_id', $id)->update(['plan_charge' => $charge]);
            DB::table('check_ins')->where('plan_type_id', $id)->update(['plan_charge' => $charge]);
        }
    }
};
