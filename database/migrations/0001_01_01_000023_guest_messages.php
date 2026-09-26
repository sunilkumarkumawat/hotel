<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent to the guest, not to the staff.
 *
 * `notification_deliveries` was built for the staff bell: every row belonged to
 * an `app_notifications` row, because every message started life as something
 * the hotel was told. A WhatsApp sent straight to a guest — "your booking is
 * confirmed" — has no such parent and should not have one: putting every guest
 * message in the staff bell would bury the things the desk actually needs to
 * see.
 *
 * So the link becomes optional and the row carries the event on its own. The
 * delivery log then shows both kinds side by side, which is what somebody
 * asking "did the guest get it?" is looking at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_deliveries')) {
            return;
        }

        if (! Schema::hasColumn('notification_deliveries', 'event')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                $table->string('event', 60)->nullable()->after('branch_id');
                // Who it went to — 'staff' for the bell's own mail and
                // WhatsApp, 'guest' for a message sent to the guest's phone.
                $table->string('audience', 10)->default('staff')->after('event');
            });
        }

        /*
         * Making an existing NOT NULL column nullable needs the column to be
         * redeclared, which is what change() does on both MySQL and SQLite.
         */
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('app_notification_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_deliveries')) {
            return;
        }

        if (Schema::hasColumn('notification_deliveries', 'event')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                $table->dropColumn(['event', 'audience']);
            });
        }
    }
};
