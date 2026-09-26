<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per online booking attempt made from the public website.
 *
 * Deliberately NOT the same thing as a `reservations` row. The payment is
 * started (a Razorpay order is created) the moment a guest clicks "Pay now",
 * before any money has moved and before this system has promised them a
 * room — `booking_snapshot` is the guest's whole intended booking, held here
 * until the payment actually clears. The `reservations` row — the real,
 * counted-against-inventory booking — is only created once Razorpay
 * confirms the payment succeeded. That ordering is what stops a browser tab
 * closed mid-payment, or a payment that never completes, from ever leaving a
 * half-made reservation behind.
 *
 * `confirmation_token` is the guest's own link back to "what happened to my
 * booking" (`/booking/{token}`), on the same unguessable-random-string terms
 * as the existing guest-doc and feedback links — no account, nothing to log
 * in to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();

            // Set once the reservation this payment paid for actually exists.
            $table->unsignedBigInteger('reservation_id')->nullable()->index();

            $table->string('confirmation_token', 40)->unique();

            $table->string('gateway', 20)->default('razorpay');
            $table->string('gateway_order_id')->index();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');

            /*
             * created            order made, guest is on the payment page
             * paid               signature verified, reservation created
             * failed             guest's payment did not go through
             * needs_review       paid, but the rooms sold out in the few
             *                    seconds between order and payment — see
             *                    App\Support\Website\BookingService. Staff
             *                    handle these by hand; it should be rare.
             */
            $table->enum('status', ['created', 'paid', 'failed', 'needs_review'])->default('created');

            // The guest's whole intended booking — dates, room type, count,
            // name, mobile, email, remark — exactly as they submitted it.
            $table->json('booking_snapshot');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_payments');
    }
};
