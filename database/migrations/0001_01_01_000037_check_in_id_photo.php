<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture of the card, next to the ID it already carries.
 *
 * `check_ins` has had `id_type` and `id_number` since the compliance
 * migration — read by the police register and Form C — but nothing on the
 * check-in screen ever wrote them, so both columns have sat empty since the
 * day they were added. This adds the one thing that was still missing:
 * somewhere to keep the photo, named `photo` to match the column
 * `check_in_pax` has carried for the same purpose from the start.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('check_ins') && ! Schema::hasColumn('check_ins', 'photo')) {
            Schema::table('check_ins', function (Blueprint $table) {
                $table->string('photo')->nullable()->after('id_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('check_ins') && Schema::hasColumn('check_ins', 'photo')) {
            Schema::table('check_ins', fn (Blueprint $t) => $t->dropColumn('photo'));
        }
    }
};
