<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masters — the lists every other screen picks from.
 *
 * Everything is scoped by `branch_id` so two properties never see each
 * other's rooms, rates or companies.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** Small name-only lists, all built the same way. */
        $simple = [
            'business_market'     => 'Business Market on the reservation',
            'visit_purpose'       => 'Why the guest is visiting',
            'pick_drop'           => 'Pick and drop facility',
            'billing_instruction' => 'Billing instruction on the folio',
            'expense_head'        => 'Petty cash — what money went out for',
            'receive_head'        => 'Petty cash — what money came in for',
        ];

        foreach ($simple as $table => $comment) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->decimal('charge', 12, 2)->default(0);
                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        Schema::create('pay_mode', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->enum('type', ['cash', 'bank', 'card', 'upi', 'cheque', 'other'])->default('cash');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('tax_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');                                  // GST 12%
            $table->decimal('percent', 6, 2)->default(0);
            $table->tinyInteger('is_default')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('room_category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');                                  // Deluxe, Suite
            $table->string('code', 20)->nullable();
            $table->tinyInteger('sort')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('room_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('room_category_id')->nullable()->index();
            $table->string('name');                                  // Single, Double, Triple
            $table->string('code', 20)->nullable();
            $table->decimal('base_rent', 12, 2)->default(0);
            $table->unsignedTinyInteger('max_adult')->default(2);
            $table->unsignedTinyInteger('max_child')->default(1);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('plan_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');                                  // European Plan
            $table->string('code', 20)->nullable();                  // EP, CP, MAP, AP
            $table->decimal('charge', 12, 2)->default(0);            // added per day, per room
            $table->string('remark')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('room_category_id')->nullable()->index();
            $table->unsignedBigInteger('room_type_id')->nullable()->index();
            $table->string('room_no', 30);
            $table->string('floor', 30)->nullable();
            $table->decimal('base_rent', 12, 2)->default(0);
            $table->unsignedTinyInteger('max_pax')->default(2);
            $table->enum('housekeeping_status', ['clean', 'dirty', 'inspected', 'out_of_order'])->default('clean');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'room_no']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('tax_master_id')->nullable()->index();
            $table->string('name');                                  // Laundry, Airport pickup
            $table->string('code', 30)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('gst_no', 20)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('zip_code', 12)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('booked_by', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');                                  // agent / OTA / walk-in
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->decimal('commission_percent', 6, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('title', 10)->nullable();                 // Mr. / Mrs. / Ms.
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('email2')->nullable();
            $table->string('mobile', 20)->nullable()->index();
            $table->string('mobile2', 20)->nullable();
            $table->string('address')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('zip_code', 12)->nullable();
            $table->string('id_type', 40)->nullable();               // Aadhaar / Passport
            $table->string('id_number', 60)->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->tinyInteger('is_blacklisted')->default(0);
            $table->string('remark')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'guests', 'booked_by', 'companies', 'services', 'rooms', 'plan_type',
            'room_type', 'room_category', 'tax_master', 'pay_mode',
            'receive_head', 'expense_head', 'billing_instruction',
            'pick_drop', 'visit_purpose', 'business_market',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
