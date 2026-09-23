<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hotel's own calendar.
 *
 * A hotel's day does not end at midnight. The desk is still checking people in
 * at 1am, the restaurant is still closing its last table, and all of it belongs
 * to yesterday's business. So the hotel keeps its own date — the *business
 * date* — and a night auditor moves it forward once a day, after the day's
 * figures have been taken.
 *
 * One row per branch per day. While it is `open` the day is still being traded;
 * once it is `closed` its figures are frozen and the business date is the day
 * after it. That freezing is the point: an occupancy figure that changes every
 * time somebody opens the report is a figure nobody can put in a meeting.
 *
 * `figures` is the whole manager's report as it stood at the close, stored as
 * JSON rather than as forty columns. It is a photograph, never a source: the
 * folio charges and settlements it was taken from are still there to be
 * queried, and a report that wants live numbers asks them instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_days')) {
            return;
        }

        Schema::create('business_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->date('business_date');

            $table->enum('status', ['open', 'closed'])->default('open')->index();

            /*
             * What the audit did, kept apart from what it found. A night that
             * posted eleven room charges and marked two no-shows is a night
             * somebody may want to ask about later.
             */
            $table->unsignedInteger('nights_posted')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->unsignedInteger('rooms_sold')->default(0);

            // The manager's report, frozen at the moment of the close.
            $table->json('figures')->nullable();

            $table->text('note')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            /*
             * One row per day per branch, enforced by the database rather than
             * by the code that writes it. Two clerks pressing Run at the same
             * moment is exactly how a day gets closed twice and every figure
             * doubles.
             */
            $table->unique(['branch_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_days');
    }
};
