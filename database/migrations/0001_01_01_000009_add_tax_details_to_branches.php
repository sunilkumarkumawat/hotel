<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Guest Registration Card, and every bill after it, is printed on the
 * hotel's letterhead — which by law carries the GSTIN.
 *
 * `legal_name` is separate from `branch_name` because the name on the GST
 * certificate is often not the name over the door: "Shree Krishna Cottage"
 * trades, "Krishna Hospitality Pvt Ltd" is registered. Printing the wrong one
 * on a tax document is the hotel's problem, not the guest's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('branch_name');
            $table->string('gst_no', 20)->nullable()->after('email');
            $table->string('sac_code', 12)->nullable()->after('gst_no');
            $table->string('logo')->nullable()->after('sac_code');
            $table->text('reg_card_terms')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'gst_no', 'sac_code', 'logo', 'reg_card_terms']);
        });
    }
};
