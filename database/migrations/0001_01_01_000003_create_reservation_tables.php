<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservation — one header row, many room rows, many service rows.
 *
 * The header carries the guest and billing details from the "Personal Details"
 * tab; `reservation_rooms` is the "Rooms Allotment Details" grid and
 * `reservation_services` is the service grid under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('reservation_no', 40)->index();
            $table->date('reservation_date');

            // Guest — kept on the reservation as well as on `guests`, because a
            // booking is a record of what was said at the time.
            $table->unsignedBigInteger('guest_id')->nullable()->index();
            $table->string('title', 10)->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('email2')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('mobile2', 20)->nullable();
            $table->string('address')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('zip_code', 12)->nullable();

            // Trip
            $table->enum('reservation_type', ['confirm', 'tentative', 'waiting', 'group'])->default('confirm');
            $table->unsignedBigInteger('pick_drop_id')->nullable();
            $table->unsignedBigInteger('visit_purpose_id')->nullable();
            $table->string('arrival_from')->nullable();
            $table->string('departure_to')->nullable();
            $table->string('transport_mode')->nullable();
            $table->string('confirm_voucher_no', 60)->nullable();

            // Trade
            $table->unsignedBigInteger('booked_by_id')->nullable();
            $table->unsignedBigInteger('business_market_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('company_gst_no', 20)->nullable();
            $table->unsignedBigInteger('emp_id')->nullable();          // users.user_id

            // Billing
            $table->unsignedBigInteger('billing_instruction_id')->nullable();
            $table->unsignedBigInteger('pay_mode_id')->nullable();
            $table->text('remark')->nullable();
            $table->text('special_remark')->nullable();

            $table->decimal('room_total', 12, 2)->default(0);
            $table->decimal('service_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('advance_paid', 12, 2)->default(0);

            $table->enum('status', [
                'confirmed', 'tentative', 'cancelled', 'checked_in', 'checked_out', 'no_show',
            ])->default('confirmed')->index();

            $table->date('cancelled_on')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'reservation_no']);
        });

        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id')->index();

            $table->date('arrival_date');
            $table->time('arrival_time')->nullable();
            $table->date('checkout_date');
            $table->time('checkout_time')->nullable();

            $table->enum('guest_type', ['adv_booking', 'walk_in', 'complimentary', 'house_use', 'group'])
                ->default('adv_booking');

            $table->unsignedBigInteger('room_category_id')->nullable();
            $table->unsignedBigInteger('room_type_id')->nullable();
            $table->unsignedBigInteger('plan_type_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable()->index();  // set when a room is allotted
            $table->string('room_no', 30)->nullable();

            $table->unsignedSmallInteger('no_of_days')->default(1);
            $table->unsignedSmallInteger('no_of_rooms')->default(1);

            $table->enum('tax_type', ['exclusive', 'inclusive'])->default('exclusive');
            $table->decimal('room_rent', 12, 2)->default(0);           // per room, per day
            $table->decimal('discount', 12, 2)->default(0);            // per room, per day

            $table->unsignedTinyInteger('male')->default(1);
            $table->unsignedTinyInteger('female')->default(0);
            $table->unsignedTinyInteger('child')->default(0);

            $table->decimal('amount', 12, 2)->default(0);              // taxable value
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('reservation_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id')->index();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('service_name');
            $table->enum('tax_type', ['exclusive', 'inclusive'])->default('exclusive');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('advance_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->date('deposit_date');
            $table->unsignedBigInteger('pay_mode_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference_no', 60)->nullable();
            $table->enum('type', ['deposit', 'refund'])->default('deposit');
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_deposits');
        Schema::dropIfExists('reservation_services');
        Schema::dropIfExists('reservation_rooms');
        Schema::dropIfExists('reservations');
    }
};
