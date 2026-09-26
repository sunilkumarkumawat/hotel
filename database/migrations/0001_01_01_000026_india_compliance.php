<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paperwork an Indian hotel owes to somebody other than the guest.
 *
 * Three obligations, and they are genuinely different jobs:
 *
 *   **Form C** — every foreign national staying at a hotel has to be reported
 *   to the FRRO. It wants passport and visa details the front desk does not
 *   otherwise collect, so they get a table of their own rather than fifteen
 *   nullable columns on `check_ins` that are empty for every Indian guest.
 *
 *   **The police register** — the local station wants a daily list of who is
 *   in the building, with an ID against each name. That needs no new table:
 *   it is `check_ins` and `check_in_pax`, which already carry ID type and
 *   number, read as a report. All this migration adds is the ID on the main
 *   guest's own row, which was the one place it was missing.
 *
 *   **GST** — the invoice already splits tax into CGST and SGST. What was
 *   missing is the paperwork around it: the buyer's GSTIN and the place of
 *   supply, without which a bill cannot appear in GSTR-1 as a B2B invoice and
 *   the company cannot claim the credit.
 *
 * A note on place of supply, because it is the thing most often got wrong:
 * for hotel accommodation it is the location of the hotel, whoever the guest
 * is and wherever they are from. So room revenue is CGST + SGST and never
 * IGST. It is stored per bill anyway — a bill can carry more than
 * accommodation, and a rule that is true today should still be visible in the
 * data when it changes. Confirm the treatment of anything beyond room rent
 * with your accountant; this schema records the facts, it does not give advice.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * The main guest's own ID. `check_in_pax` has carried one per
         * accompanying person since the start; the person whose name is on the
         * booking had none, which is exactly the one the police register asks
         * for first.
         */
        if (Schema::hasTable('check_ins') && ! Schema::hasColumn('check_ins', 'id_type')) {
            Schema::table('check_ins', function (Blueprint $table) {
                $table->string('id_type', 40)->nullable()->after('mobile');
                $table->string('id_number', 60)->nullable()->after('id_type');
                $table->unsignedBigInteger('nationality_id')->nullable()->after('id_number');
                // Set at check-in so the Form C list does not have to join to
                // countries and guess. A guest is foreign or they are not.
                $table->boolean('is_foreign')->default(false)->after('nationality_id')->index();
            });
        }

        /*
         * Form C, one per foreign guest.
         *
         * Per GUEST, not per room: a couple in one room is two forms, and a
         * family of four sharing a suite is four. `check_in_pax_id` is null for
         * the person the booking is in the name of and set for everybody else,
         * which is how the screen knows whose form it is looking at.
         */
        if (! Schema::hasTable('form_c_entries')) {
            Schema::create('form_c_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('check_in_id')->index();
                $table->unsignedBigInteger('check_in_pax_id')->nullable()->index();

                $table->string('name');
                $table->unsignedBigInteger('nationality_id')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->enum('sex', ['male', 'female', 'other'])->nullable();

                $table->string('passport_no', 40)->nullable();
                $table->string('passport_place_of_issue')->nullable();
                $table->date('passport_issue_date')->nullable();
                $table->date('passport_expiry_date')->nullable();

                $table->string('visa_no', 40)->nullable();
                $table->string('visa_type', 60)->nullable();
                $table->string('visa_place_of_issue')->nullable();
                $table->date('visa_issue_date')->nullable();
                $table->date('visa_expiry_date')->nullable();

                $table->date('arrived_in_india_on')->nullable();
                $table->string('arrived_from')->nullable();          // the city and country
                $table->string('purpose_of_visit', 120)->nullable();

                $table->text('permanent_address')->nullable();
                $table->text('address_in_india')->nullable();

                $table->string('next_destination')->nullable();
                $table->date('next_destination_on')->nullable();

                $table->boolean('employed_in_india')->default(false);
                $table->string('employer')->nullable();

                /*
                 * Filing is a fact, not a status. `filed_at` is stamped when
                 * somebody actually sends the form and `reference_no` is what
                 * the portal gave back, so "have we reported this guest?" has
                 * an answer that cannot be set by accident.
                 */
                $table->timestamp('filed_at')->nullable();
                $table->string('reference_no', 60)->nullable();

                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                // One form per person per stay.
                $table->unique(['check_in_id', 'check_in_pax_id'], 'form_c_person_unique');
            });
        }

        /*
         * What a bill needs to become a GST invoice somebody can claim on.
         *
         * The buyer's GSTIN is copied onto the bill rather than read from the
         * company: a company's registration can change, and last year's
         * invoice has to keep saying what it said when it was issued.
         */
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'buyer_gstin')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('buyer_gstin', 20)->nullable()->after('guest_id');
                $table->string('buyer_name')->nullable()->after('buyer_gstin');
                $table->string('buyer_address')->nullable()->after('buyer_name');

                // The state code, as GST numbers them: '08' is Rajasthan.
                $table->string('place_of_supply', 4)->nullable()->after('buyer_address');
                $table->string('place_of_supply_name', 60)->nullable()->after('place_of_supply');

                /*
                 * Whether this invoice went out as IGST rather than CGST+SGST.
                 * Stored rather than worked out: it is what was printed, and a
                 * rule change must not silently re-cast old invoices.
                 */
                $table->boolean('is_igst')->default(false)->after('place_of_supply_name');
            });
        }

        /*
         * The hotel's own GST state, so an invoice knows where it stands.
         * `gst_no` has been on branches since the tax columns went in; the
         * state code was being inferred from it, which breaks the moment a
         * branch has no GSTIN yet.
         */
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'gst_state_code')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('gst_state_code', 4)->nullable()->after('gst_no');
                $table->string('gst_state_name', 60)->nullable()->after('gst_state_code');
                // The FRRO knows a hotel by a number of its own.
                $table->string('frro_hotel_code', 40)->nullable()->after('gst_state_name');
                $table->string('police_station')->nullable()->after('frro_hotel_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_c_entries');

        if (Schema::hasTable('check_ins') && Schema::hasColumn('check_ins', 'id_type')) {
            Schema::table('check_ins', fn (Blueprint $t) => $t->dropColumn(
                ['id_type', 'id_number', 'nationality_id', 'is_foreign']
            ));
        }

        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'buyer_gstin')) {
            Schema::table('bills', fn (Blueprint $t) => $t->dropColumn(
                ['buyer_gstin', 'buyer_name', 'buyer_address', 'place_of_supply', 'place_of_supply_name', 'is_igst']
            ));
        }

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'gst_state_code')) {
            Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(
                ['gst_state_code', 'gst_state_name', 'frro_hotel_code', 'police_station']
            ));
        }
    }
};
