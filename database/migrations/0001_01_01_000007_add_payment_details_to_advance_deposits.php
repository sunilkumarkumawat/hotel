<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_deposits', function (Blueprint $table) {
            $table->string('pay_type', 40)->nullable()->after('pay_mode_id');
            $table->string('card_type', 40)->nullable()->after('pay_type');
            $table->string('card_name')->nullable()->after('card_type');
            $table->string('card_last4', 4)->nullable()->after('card_name');
            $table->string('pan_no', 10)->nullable()->after('card_last4');
        });
    }

    public function down(): void
    {
        Schema::table('advance_deposits', function (Blueprint $table) {
            $table->dropColumn(['pay_type', 'card_type', 'card_name', 'card_last4', 'pan_no']);
        });
    }
};
