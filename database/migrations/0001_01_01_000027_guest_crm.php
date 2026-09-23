<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembering a guest between visits.
 *
 * A hotel already holds everything it needs to do this — who came, what they
 * paid, what they asked for — and throws it away every time they leave,
 * because it is scattered across bookings and nobody ever puts it back
 * together. These four tables are the putting back together.
 *
 *   `guest_notes`      — what the desk learned. "Asks for a high floor."
 *                        "Complained about the lift, comped a breakfast."
 *   `guest_feedback`   — what the guest said after they left, from a link they
 *                        can open without an account.
 *   `loyalty_entries`  — points, as a ledger rather than a number, so a
 *                        balance can always be explained.
 *   plus a handful of columns on `guests` — the figures a profile screen would
 *                        otherwise recompute on every page load.
 *
 * ── Why the totals are stored ─────────────────────────────────────────────
 *
 * `stays`, `nights`, `total_spend`, `last_stay_at` are cached on the guest.
 * That is denormalisation with its eyes open: the alternative is four
 * aggregate queries across every folio the guest has ever had, run once per
 * row of a list screen. They are rebuilt by App\Support\GuestCrm from the
 * bookings whenever a stay closes, and the profile screen shows when they were
 * last worked out — so a stale figure is visible rather than silently wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guests') && ! Schema::hasColumn('guests', 'stays')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->unsignedInteger('stays')->default(0)->after('is_blacklisted');
                $table->unsignedInteger('nights')->default(0)->after('stays');
                $table->decimal('total_spend', 14, 2)->default(0)->after('nights');
                $table->date('first_stay_at')->nullable()->after('total_spend');
                $table->date('last_stay_at')->nullable()->after('first_stay_at');
                $table->timestamp('totals_at')->nullable()->after('last_stay_at');

                /*
                 * A tier is worked out from the figures above, and stored so a
                 * list can sort and filter on it. It is never the source of
                 * anything — see App\Support\GuestCrm::tierFor.
                 */
                $table->string('tier', 20)->nullable()->after('totals_at')->index();
                $table->integer('loyalty_points')->default(0)->after('tier');

                // Preferences, as a list the desk types rather than a form of
                // forty checkboxes nobody fills in.
                $table->text('preferences')->nullable()->after('loyalty_points');
                $table->date('anniversary')->nullable()->after('preferences');

                /*
                 * Blacklisting is a decision somebody made on a date for a
                 * reason. The flag alone has been on this table since the
                 * start and has never been able to answer "why?".
                 */
                $table->string('blacklist_reason')->nullable()->after('anniversary');
                $table->date('blacklisted_on')->nullable()->after('blacklist_reason');
                $table->unsignedBigInteger('blacklisted_by')->nullable()->after('blacklisted_on');
            });
        }

        /*
         * What the desk learned, one line at a time.
         *
         * `pinned` is what puts a note in front of whoever checks them in next
         * time. Everything else is history, and history that shouts is history
         * nobody reads.
         */
        if (! Schema::hasTable('guest_notes')) {
            Schema::create('guest_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('guest_id')->index();
                $table->unsignedBigInteger('check_in_id')->nullable()->index();

                $table->enum('kind', ['preference', 'complaint', 'compliment', 'note'])
                    ->default('note')->index();
                $table->text('body');
                $table->boolean('pinned')->default(false);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        /*
         * What the guest said after they left.
         *
         * One row per stay, reached by a token in a link — no account, nothing
         * to guess. Scores are 1 to 5 and every one of them is nullable: a
         * guest who rates the room and skips the food has answered, and a form
         * that insists on all five gets answered by nobody.
         */
        if (! Schema::hasTable('guest_feedback')) {
            Schema::create('guest_feedback', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('guest_id')->nullable()->index();
                $table->unsignedBigInteger('check_in_id')->nullable()->index();

                $table->string('token', 40)->unique();

                $table->unsignedTinyInteger('overall')->nullable();
                $table->unsignedTinyInteger('room')->nullable();
                $table->unsignedTinyInteger('cleanliness')->nullable();
                $table->unsignedTinyInteger('staff')->nullable();
                $table->unsignedTinyInteger('food')->nullable();
                $table->unsignedTinyInteger('value')->nullable();

                $table->text('liked')->nullable();
                $table->text('improve')->nullable();
                $table->boolean('would_return')->nullable();

                // When the link was sent, and when they actually answered.
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('answered_at')->nullable()->index();

                /*
                 * A bad score is a job, not a statistic. `handled_at` is what
                 * makes the inbox empty: somebody read it, rang the guest, and
                 * wrote down what they did.
                 */
                $table->timestamp('handled_at')->nullable();
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->text('handled_note')->nullable();

                $table->timestamps();
            });
        }

        /*
         * Points as a ledger, never as a number.
         *
         * A balance somebody can argue with has to be explainable line by line
         * — "you earned 480 on that stay and spent 500 on the upgrade" — and a
         * single column on the guest can never do that. The column that IS on
         * the guest is a cache of the sum, rebuilt from here.
         */
        if (! Schema::hasTable('loyalty_entries')) {
            Schema::create('loyalty_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('guest_id')->index();
                $table->unsignedBigInteger('check_in_id')->nullable();

                $table->date('entry_date');
                $table->enum('kind', ['earned', 'redeemed', 'adjusted', 'expired'])->default('earned');
                // Negative for anything that takes points away, so a balance
                // is a SUM and never a subtraction somebody has to remember.
                $table->integer('points');
                $table->string('reason');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['guest_id', 'entry_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
        Schema::dropIfExists('guest_feedback');
        Schema::dropIfExists('guest_notes');

        if (Schema::hasTable('guests') && Schema::hasColumn('guests', 'stays')) {
            Schema::table('guests', fn (Blueprint $t) => $t->dropColumn([
                'stays', 'nights', 'total_spend', 'first_stay_at', 'last_stay_at', 'totals_at',
                'tier', 'loyalty_points', 'preferences', 'anniversary',
                'blacklist_reason', 'blacklisted_on', 'blacklisted_by',
            ]));
        }
    }
};
