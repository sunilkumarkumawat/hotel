<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservation Calendar Monthly counts management blocks and maintenance
 * blocks on separate lines, so the block has to say which kind it is.
 *
 * Guessing from the free-text `reason` would work until somebody typed
 * "held for owner" and it landed in the maintenance row; a column the Block
 * Room form actually asks for cannot drift like that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->enum('block_type', ['management', 'maintenance'])
                ->default('management')
                ->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropColumn('block_type');
        });
    }
};
