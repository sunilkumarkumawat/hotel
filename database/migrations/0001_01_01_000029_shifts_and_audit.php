<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two questions a hotel owner asks that nothing so far can answer:
 *
 *   "Is the cash in the drawer the cash the shift took?"
 *   "Who changed that, and when?"
 *
 * `cashier_shifts` is the first. Note what it does NOT hold: a copy of the
 * payments. A shift's figures are a question asked of `settlements`,
 * `advance_deposits`, `pos_payments` and the petty cash tables — the rows
 * that already exist — which means a shift cannot disagree with the books,
 * and yesterday's shifts can be worked out for a hotel that only installed
 * this screen today. What it does hold is what the cashier COUNTED, which
 * exists nowhere else, and a frozen copy of the expected figures taken at
 * the moment of closing, so that a later correction to a bill cannot quietly
 * rewrite a variance somebody already signed.
 *
 * `activity_logs` is the second. One row per thing done, never updated and
 * never deleted, with the user's name and the row's label written into it
 * rather than joined: a log that goes blank when a user is deleted is a log
 * that fails exactly when somebody needs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('shift_no', 40);
            $table->string('name', 40)->nullable();          // Morning / Evening / Night
            $table->dateTime('opened_at')->index();
            $table->dateTime('closed_at')->nullable();

            // What was in the drawer before the shift took a rupee.
            $table->decimal('opening_float', 12, 2)->default(0);

            // What the cashier counted, per pay mode: {"pay_mode_id": amount}.
            $table->json('declared')->nullable();

            // The whole figure set as it stood at closing time — frozen.
            $table->json('expected')->nullable();

            $table->decimal('cash_expected', 12, 2)->default(0);
            $table->decimal('cash_counted', 12, 2)->default(0);
            // Counted minus expected: negative is short, positive is over.
            $table->decimal('variance', 12, 2)->default(0);

            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->text('remark')->nullable();
            $table->text('close_note')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'shift_no']);
            $table->index(['branch_id', 'user_id', 'status']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Frozen: the log must still name them after the user row is gone.
            $table->string('user_name', 120)->nullable();

            $table->string('action', 40)->index();           // created / updated / deleted / login / …
            $table->string('area', 40)->nullable()->index(); // money / stay / rates / access / system
            $table->string('subject_type', 80)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            // Also frozen: "Bill INV-1-0042", not a number to go looking for.
            $table->string('subject_label')->nullable();
            $table->string('summary')->nullable();

            // Only the columns that actually changed: {"col": {"from": …, "to": …}}
            $table->json('changes')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->dateTime('happened_at')->index();
            $table->timestamps();

            $table->index(['branch_id', 'happened_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('cashier_shifts');
    }
};
