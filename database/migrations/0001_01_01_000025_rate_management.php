<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a room costs tonight.
 *
 * Until now a room type had one `base_rent` and that was the answer all year.
 * Real hotels do not work that way: a Deluxe is ₹3,500 in July, ₹9,000 between
 * Christmas and the 2nd, ₹4,200 on a Saturday, and ₹2,800 for the company that
 * books forty room nights a month.
 *
 * Three tables, and each answers one question:
 *
 *   `rate_seasons` — WHEN. A named stretch of the calendar: "Peak", "Monsoon
 *   Offer", "Diwali". Named because "extend the peak season by three days"
 *   should be one edit, not one per room type.
 *
 *   `rate_plans`   — WHO FOR. A price list with a name: Rack, Corporate, OTA,
 *   Weekend Special. A plan can be tied to a market or to one company, which
 *   is what makes a negotiated rate a negotiated rate.
 *
 *   `rate_rules`   — HOW MUCH. One row: this plan, this room type, these
 *   nights, this many rupees. Min-stay and stop-sell live here too, because
 *   "not below three nights" and "not at any price" are things you say about
 *   the same stretch of dates as the price.
 *
 * Nothing here ever deletes or rewrites what a booking was sold at. The rate
 * is copied onto the booking row when it is taken; these tables only answer
 * "what should this cost", and only when somebody asks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rate_seasons')) {
            Schema::create('rate_seasons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('name');                       // Peak, Monsoon Offer
                $table->string('code', 20)->nullable();
                $table->date('from_date');
                $table->date('to_date');

                /*
                 * Two seasons can cover the same night — a "Diwali" inside a
                 * "Peak". The higher priority wins, so a hotel can lay a short
                 * special over a long season without editing the long one.
                 */
                $table->unsignedTinyInteger('priority')->default(0);

                // Shown as the band on the rate calendar.
                $table->string('colour', 20)->nullable();

                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->index(['branch_id', 'from_date', 'to_date']);
            });
        }

        if (! Schema::hasTable('rate_plans')) {
            Schema::create('rate_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('name');                       // Rack, Corporate, OTA
                $table->string('code', 20)->nullable();

                /*
                 * Who the plan is for. Both null is a plan anybody can be sold
                 * — the rack rate. A company id makes it that company's
                 * negotiated rate and nobody else's.
                 */
                $table->unsignedBigInteger('business_market_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();

                // The one used when the booking names no plan at all.
                $table->boolean('is_default')->default(false);

                $table->unsignedTinyInteger('priority')->default(0);

                // A plan can itself be seasonal — an OTA contract that expires.
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();

                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rate_rules')) {
            Schema::create('rate_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->unsignedBigInteger('rate_plan_id')->index();
                $table->unsignedBigInteger('room_type_id')->index();

                /*
                 * A rule is dated either by naming a season or by giving its
                 * own two dates. Both empty means "all year", which is the row
                 * a hotel starts with and the one that catches everything the
                 * seasons do not.
                 */
                $table->unsignedBigInteger('rate_season_id')->nullable()->index();
                $table->date('from_date')->nullable();
                $table->date('to_date')->nullable();

                /*
                 * Which nights of the week this rule speaks for, as
                 * 'fri,sat'. Empty means every night. Stored as text rather
                 * than seven booleans because that is how it is read back —
                 * "weekends" is one thing, not seven.
                 */
                $table->string('weekdays', 40)->nullable();

                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('extra_adult', 12, 2)->default(0);
                $table->decimal('extra_child', 12, 2)->default(0);

                // "Not below three nights", and "not at any price".
                $table->unsignedSmallInteger('min_stay')->default(0);
                $table->unsignedSmallInteger('max_stay')->default(0);
                $table->boolean('stop_sell')->default(false);
                $table->boolean('closed_to_arrival')->default(false);
                $table->boolean('closed_to_departure')->default(false);

                $table->unsignedTinyInteger('priority')->default(0);

                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                // The lookup the engine makes, once per night of a stay.
                $table->index(['branch_id', 'rate_plan_id', 'room_type_id'], 'rate_rules_lookup_index');
            });
        }

        /*
         * Which plan a booking was sold on. Kept beside the rate it was sold
         * at rather than instead of it: the amount on the booking row is the
         * contract, and this only says where the number came from.
         */
        if (Schema::hasTable('reservations') && ! Schema::hasColumn('reservations', 'rate_plan_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('rate_plan_id')->nullable()->after('business_market_id');
            });
        }

        if (Schema::hasTable('reservation_rooms') && ! Schema::hasColumn('reservation_rooms', 'rate_plan_id')) {
            Schema::table('reservation_rooms', function (Blueprint $table) {
                $table->unsignedBigInteger('rate_plan_id')->nullable()->after('plan_type_id');
            });
        }

        if (Schema::hasTable('check_ins') && ! Schema::hasColumn('check_ins', 'rate_plan_id')) {
            Schema::table('check_ins', function (Blueprint $table) {
                $table->unsignedBigInteger('rate_plan_id')->nullable()->after('plan_type_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['reservations', 'reservation_rooms', 'check_ins'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'rate_plan_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('rate_plan_id'));
            }
        }

        Schema::dropIfExists('rate_rules');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('rate_seasons');
    }
};
