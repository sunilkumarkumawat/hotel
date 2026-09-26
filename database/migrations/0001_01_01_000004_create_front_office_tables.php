<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Front office — the guest is in the building.
 *
 * A check-in points back at the reservation it came from (walk-ins have none),
 * and everything charged to the guest lands in `folio_charges` until it is
 * settled on a bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->unsignedBigInteger('reservation_room_id')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable()->index();

            $table->string('folio_no', 40)->index();
            $table->string('guest_name');
            $table->string('mobile', 20)->nullable();

            $table->date('checkin_date');
            $table->time('checkin_time')->nullable();
            $table->date('expected_checkout_date');
            $table->time('expected_checkout_time')->nullable();
            $table->date('actual_checkout_date')->nullable();
            $table->time('actual_checkout_time')->nullable();

            $table->unsignedBigInteger('plan_type_id')->nullable();
            $table->decimal('room_rent', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->enum('tax_type', ['exclusive', 'inclusive'])->default('exclusive');

            $table->unsignedTinyInteger('male')->default(1);
            $table->unsignedTinyInteger('female')->default(0);
            $table->unsignedTinyInteger('child')->default(0);

            $table->enum('status', ['in_house', 'checked_out', 'cancelled'])->default('in_house')->index();
            $table->tinyInteger('is_direct')->default(0);          // walk-in, no reservation
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('check_in_pax', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('check_in_id')->index();
            $table->string('name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('relation', 40)->nullable();
            $table->string('id_type', 40)->nullable();
            $table->string('id_number', 60)->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('folio_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('check_in_id')->index();
            $table->date('charge_date');
            $table->enum('charge_type', ['room', 'service', 'misc', 'discount', 'tax'])->default('service');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('particulars');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->tinyInteger('is_settled')->default(0);
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('room_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('check_in_id')->index();
            $table->unsignedBigInteger('from_room_id')->nullable();
            $table->unsignedBigInteger('to_room_id')->nullable();
            $table->date('transfer_date');
            $table->decimal('new_room_rent', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('check_in_id')->nullable()->index();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->string('bill_no', 40)->index();
            /*
             * Five rooms billed to one family share a group number. Each room
             * keeps its own bill_no — that is what room revenue and a GST
             * return are reported against — while the group is what lets the
             * desk print them as one document with one grand total.
             */
            $table->string('group_no', 40)->nullable()->index();
            $table->date('bill_date');
            $table->decimal('room_total', 12, 2)->default(0);
            $table->decimal('service_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->enum('status', ['open', 'settled', 'partial', 'cancelled'])->default('open')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'bill_no']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('bill_id')->nullable()->index();
            $table->unsignedBigInteger('check_in_id')->nullable()->index();
            $table->date('settle_date');
            $table->unsignedBigInteger('pay_mode_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference_no', 60)->nullable();
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settlements', 'bills', 'room_transfers', 'folio_charges', 'check_in_pax', 'check_ins'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
