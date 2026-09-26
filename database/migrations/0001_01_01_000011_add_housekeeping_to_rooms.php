<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the House Keeping Status screen needs on a room.
 *
 * `touch_up` joins the housekeeping states because it is a real one and the
 * old system's legend carries it: an occupied room being tidied mid-stay is
 * not the same job as a checkout being made up from scratch, and the
 * supervisor allots the two differently.
 *
 * The enum is rebuilt rather than altered because SQLite has no ALTER for a
 * CHECK constraint — the column is recreated with the new list, which is what
 * `change()` does on both drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('housekeeping_status', ['clean', 'dirty', 'inspected', 'touch_up', 'out_of_order'])
                ->default('clean')
                ->change();

            // Who is looking after this room right now. The screen shows it in
            // the Name column and assigns it in bulk.
            $table->unsignedBigInteger('housekeeper_id')->nullable()->after('housekeeping_status');
            $table->string('housekeeping_remark')->nullable()->after('housekeeper_id');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['housekeeper_id', 'housekeeping_remark']);

            $table->enum('housekeeping_status', ['clean', 'dirty', 'inspected', 'out_of_order'])
                ->default('clean')
                ->change();
        });
    }
};
