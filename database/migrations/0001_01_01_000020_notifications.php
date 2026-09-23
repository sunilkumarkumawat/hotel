<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telling people things.
 *
 * Three tables, because "send a notification" is really three separate
 * questions and mixing them is what makes a notification system impossible to
 * debug six months later:
 *
 *   `app_notifications`      what happened. One row per event, and it is the
 *                            row the bell in the top bar reads.
 *   `notification_settings`  who should hear about it and how — per event, per
 *                            branch. This is the screen a manager edits.
 *   `notification_deliveries` what actually left the building. One row per
 *                            channel per notification, with the error on it
 *                            when it failed. A WhatsApp message that never
 *                            arrived shows up here instead of vanishing.
 *
 * The third table is the one people skip. Without it, "the guest never got the
 * mail" has no answer — with it, the answer is on the screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();

                /*
                 * NULL means everybody in the branch. A notification aimed at
                 * one person — "your work order was closed" — names them.
                 * Storing "everybody" as a null rather than as a row per user
                 * keeps a 40-user hotel from writing 40 rows every time a
                 * booking is taken.
                 */
                $table->unsignedBigInteger('user_id')->nullable()->index();

                $table->string('event', 60)->index();       // reservation.created
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('url')->nullable();          // where clicking it goes
                $table->string('icon', 40)->nullable();
                $table->enum('level', ['info', 'success', 'warning', 'danger'])->default('info');
                $table->text('data')->nullable();           // JSON, for whatever the event carries

                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                // The bell's query: this branch, mine or everyone's, unread first.
                $table->index(['branch_id', 'user_id', 'read_at'], 'app_notifications_bell_index');
                $table->index(['branch_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('notification_settings')) {
            Schema::create('notification_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('event', 60);

                /*
                 * Comma-separated rather than a join table on purpose: there
                 * are three channels, they will not grow to thirty, and a
                 * settings screen that reads and writes one row per event is
                 * one the hotel can actually understand.
                 */
                $table->string('channels', 60)->default('app');   // app,mail,whatsapp

                $table->text('mail_to')->nullable();              // extra addresses, comma separated
                $table->text('whatsapp_to')->nullable();          // extra numbers, comma separated
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->unique(['branch_id', 'event']);
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('app_notification_id')->index();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('channel', 20);                    // mail | whatsapp
                $table->string('target');                         // the address or the number
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->index();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        /*
         * Which users want the browser pop-up, and whether the bell should
         * follow them across branches. Kept on the user rather than in the
         * settings table because it is a preference, not a policy.
         */
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'notify_web')) {
            Schema::table('users', function (Blueprint $table) {
                /*
                 * `users` had no email column at all — the system signs people
                 * in by username. It needs one now, because "email this to the
                 * duty manager" has to know where.
                 */
                $table->string('email')->nullable()->after('mobile');
                $table->tinyInteger('notify_web')->default(1);
                $table->tinyInteger('notify_mail')->default(0);
                $table->string('whatsapp_no', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['notification_deliveries', 'notification_settings', 'app_notifications'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'notify_web')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['email', 'notify_web', 'notify_mail', 'whatsapp_no']);
            });
        }
    }
};
