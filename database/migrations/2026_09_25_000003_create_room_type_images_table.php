<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A room type's photo gallery.
 *
 * Nothing in this project has ever stored a room photo before — no column,
 * no folder, no upload screen (confirmed by searching every migration and
 * every view). Files land in public/images/rooms/, the same plain
 * public-path convention `branches.logo` already uses, rather than Laravel's
 * storage disk — one less moving part (no storage:link dependency) on a
 * install that already has enough batch files to remember to run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_type_id')->index();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_images');
    }
};
