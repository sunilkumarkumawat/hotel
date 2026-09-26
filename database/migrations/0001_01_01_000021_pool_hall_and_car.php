<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The four things a hotel sells that are not rooms and not food.
 *
 * Pool, banquet hall, car park, and the car itself. They are different enough
 * to deserve their own tables and similar enough to share one shape, and that
 * shape is worth stating once:
 *
 *   • a master (which pool, which hall, which bay, which car)
 *   • a booking or a record against it, with the times it holds
 *   • money that is **optional at every level** — `is_chargeable` starts off
 *     for parking and for a car trip, and `tax_choice` starts at `none`
 *     everywhere, so recording that a guest parked costs them nothing until
 *     somebody says otherwise
 *   • `check_in_id` + `post_to_room`, so a charge can land on the guest's folio
 *     and be settled with the room
 *
 * Every clash test in the controllers is the same half-open rule the room
 * calendar uses — `from < :to AND to > :from` — so a hall that frees up at 6pm
 * can be booked from 6pm.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pools();
        $this->halls();
        $this->parking();
        $this->cars();
    }

    /*
    |--------------------------------------------------------------------------
    | Pool
    |--------------------------------------------------------------------------
    */

    private function pools(): void
    {
        if (! Schema::hasTable('pools')) {
            Schema::create('pools', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');                                  // Main Pool, Kids Pool
                $table->string('code', 20)->nullable();
                $table->unsignedSmallInteger('capacity')->default(0);    // 0 = no limit
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->decimal('adult_rate', 10, 2)->default(0);
                $table->decimal('child_rate', 10, 2)->default(0);
                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pool_bookings')) {
            Schema::create('pool_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('booking_no', 40)->index();
                $table->unsignedBigInteger('pool_id')->index();

                // A house guest, or somebody who walked in off the street.
                $table->unsignedBigInteger('check_in_id')->nullable()->index();
                $table->string('guest_name')->nullable();
                $table->string('mobile', 20)->nullable();
                $table->string('room_no', 20)->nullable();

                $table->date('booking_date')->index();
                $table->time('from_time');
                $table->time('to_time');

                $table->unsignedSmallInteger('adults')->default(1);
                $table->unsignedSmallInteger('children')->default(0);

                $table->decimal('adult_rate', 10, 2)->default(0);
                $table->decimal('child_rate', 10, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);

                // Nothing is taxed unless somebody picks a tax.
                $table->string('tax_choice', 20)->default('none');
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);

                $table->enum('status', ['booked', 'in_use', 'completed', 'cancelled'])
                    ->default('booked')->index();

                $table->tinyInteger('post_to_room')->default(0);
                $table->unsignedBigInteger('folio_charge_id')->nullable();

                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'pool_id', 'booking_date']);
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Hall
    |--------------------------------------------------------------------------
    */

    private function halls(): void
    {
        if (! Schema::hasTable('halls')) {
            Schema::create('halls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');                                  // Grand Ballroom
                $table->string('code', 20)->nullable();
                $table->string('floor', 20)->nullable();
                $table->unsignedSmallInteger('capacity')->default(0);
                $table->decimal('hour_rate', 10, 2)->default(0);
                $table->decimal('day_rate', 10, 2)->default(0);
                $table->text('amenities')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hall_bookings')) {
            Schema::create('hall_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('booking_no', 40)->index();
                $table->unsignedBigInteger('hall_id')->index();

                $table->unsignedBigInteger('check_in_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('guest_name');
                $table->string('mobile', 20)->nullable();
                $table->string('room_no', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('event_type', 60)->nullable();            // Wedding, Conference

                /*
                 * Stored as date + time rather than one datetime so the screens
                 * can show, filter and clash-test on the day without parsing —
                 * and so a booking that runs past midnight still belongs to the
                 * day it started on, which is how a banquet manager thinks.
                 */
                $table->date('from_date')->index();
                $table->time('from_time');
                $table->date('to_date')->index();
                $table->time('to_time');

                $table->unsignedSmallInteger('pax')->default(0);

                $table->enum('rate_type', ['hour', 'day', 'event'])->default('event');
                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('qty', 8, 2)->default(1);                // hours or days
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);

                $table->string('tax_choice', 20)->default('none');
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('advance', 12, 2)->default(0);

                $table->enum('status', ['tentative', 'confirmed', 'in_use', 'completed', 'cancelled'])
                    ->default('confirmed')->index();

                $table->tinyInteger('post_to_room')->default(0);
                $table->unsignedBigInteger('folio_charge_id')->nullable();

                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'hall_id', 'from_date']);
            });
        }

        if (! Schema::hasTable('hall_booking_items')) {
            Schema::create('hall_booking_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hall_booking_id')->index();
                $table->string('particulars');                            // Decor, DJ, Buffet
                $table->decimal('qty', 8, 2)->default(1);
                $table->decimal('price', 12, 2)->default(0);
                $table->string('tax_choice', 20)->default('none');
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Car park
    |--------------------------------------------------------------------------
    */

    private function parking(): void
    {
        if (! Schema::hasTable('parking_slots')) {
            Schema::create('parking_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('code', 20);                              // P-01
                $table->string('zone', 40)->nullable();                  // Basement, Front
                $table->enum('vehicle_type', ['car', 'bike', 'bus', 'other'])->default('car');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('parking_records')) {
            Schema::create('parking_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('ticket_no', 40)->index();
                $table->unsignedBigInteger('parking_slot_id')->nullable()->index();

                $table->unsignedBigInteger('check_in_id')->nullable()->index();
                $table->string('guest_name')->nullable();
                $table->string('mobile', 20)->nullable();
                $table->string('room_no', 20)->nullable();

                $table->string('vehicle_no', 20);
                $table->enum('vehicle_type', ['car', 'bike', 'bus', 'other'])->default('car');
                $table->string('make_model', 60)->nullable();
                $table->string('colour', 30)->nullable();
                $table->string('driver_name', 80)->nullable();
                $table->string('driver_mobile', 20)->nullable();

                $table->dateTime('in_at')->index();
                $table->dateTime('out_at')->nullable();

                /*
                 * **Parking is free.** This tick starts off and a record with it
                 * off charges nothing, whatever is in the rate. Turning it on is
                 * a deliberate act on the screen, which is exactly how the hotel
                 * asked for it.
                 */
                $table->tinyInteger('is_chargeable')->default(0);
                $table->decimal('rate', 10, 2)->default(0);
                $table->decimal('hours', 8, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);

                $table->string('tax_choice', 20)->default('none');
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);

                $table->enum('status', ['parked', 'out', 'cancelled'])->default('parked')->index();
                $table->tinyInteger('post_to_room')->default(0);
                $table->unsignedBigInteger('folio_charge_id')->nullable();

                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'status', 'in_at']);
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The car itself — picking guests up and dropping them back
    |--------------------------------------------------------------------------
    */

    private function cars(): void
    {
        if (! Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');                                  // Innova 1
                $table->string('vehicle_no', 20)->nullable();
                $table->enum('type', ['car', 'suv', 'tempo', 'bus', 'other'])->default('car');
                $table->unsignedSmallInteger('seats')->default(4);
                $table->string('driver_name', 80)->nullable();
                $table->string('driver_mobile', 20)->nullable();
                $table->decimal('km_rate', 10, 2)->default(0);
                $table->decimal('trip_rate', 10, 2)->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('guest_trips')) {
            Schema::create('guest_trips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('trip_no', 40)->index();
                $table->enum('trip_type', ['pickup', 'drop'])->default('pickup')->index();

                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->unsignedBigInteger('check_in_id')->nullable()->index();
                $table->string('guest_name');
                $table->string('mobile', 20)->nullable();
                $table->string('room_no', 20)->nullable();
                $table->unsignedSmallInteger('pax')->default(1);
                $table->unsignedSmallInteger('luggage')->default(0);

                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->string('driver_name', 80)->nullable();
                $table->string('driver_mobile', 20)->nullable();

                $table->string('from_place');                            // Airport T3
                $table->string('to_place');                              // The hotel
                $table->string('flight_no', 30)->nullable();
                $table->date('trip_date')->index();
                $table->time('trip_time');

                $table->decimal('km', 8, 2)->default(0);

                // Same rule as parking: recorded first, charged only if asked.
                $table->tinyInteger('is_chargeable')->default(0);
                $table->enum('rate_type', ['km', 'trip'])->default('trip');
                $table->decimal('rate', 10, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);

                $table->string('tax_choice', 20)->default('none');
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);

                $table->enum('status', ['scheduled', 'started', 'completed', 'cancelled'])
                    ->default('scheduled')->index();

                $table->tinyInteger('post_to_room')->default(0);
                $table->unsignedBigInteger('folio_charge_id')->nullable();

                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'trip_date', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'guest_trips', 'vehicles',
            'parking_records', 'parking_slots',
            'hall_booking_items', 'hall_bookings', 'halls',
            'pool_bookings', 'pools',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
