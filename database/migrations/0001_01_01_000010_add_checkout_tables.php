<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Check Out Guest needs on top of the tables already there.
 *
 * A settlement records how the guest actually paid, the same way an advance
 * deposit does — and with the same rule: only the last four digits of a card
 * are stored, in a column four characters wide so a full number cannot fit.
 *
 * `pax_checkouts` is its own table rather than a column on the check-in
 * because two of four guests leaving on Tuesday and the rest on Thursday is
 * two events, and the desk has to be able to see both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->string('pay_type', 40)->nullable()->after('pay_mode_id');
            $table->string('card_type', 40)->nullable()->after('pay_type');
            $table->string('card_name')->nullable()->after('card_type');
            $table->string('card_last4', 4)->nullable()->after('card_name');
            $table->string('pan_no', 10)->nullable()->after('card_last4');
        });

        Schema::table('bills', function (Blueprint $table) {
            // What the guest had already given before the bill was made, so a
            // reprint of an old bill still shows the figures it was made with.
            $table->decimal('advance_amount', 12, 2)->default(0)->after('discount_total');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('paid_amount');
            $table->unsignedBigInteger('billing_instruction_id')->nullable()->after('status');
            $table->string('remark')->nullable()->after('billing_instruction_id');
        });

        Schema::create('pax_checkouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('check_in_id')->index();
            $table->date('checkout_date');
            $table->unsignedTinyInteger('pax')->default(1);
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pax_checkouts');

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['advance_amount', 'refund_amount', 'billing_instruction_id', 'remark']);
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['pay_type', 'card_type', 'card_name', 'card_last4', 'pan_no']);
        });
    }
};
