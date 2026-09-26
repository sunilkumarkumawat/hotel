<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest-facing copy for a room type — description and a short highlights
 * list ("Free WiFi", "City View", "King Bed").
 *
 * Kept off `room_type` itself on purpose: that table is the operational
 * master the reservation form, the tape chart and pricing all read, and
 * every screen that edits it already has its own tested form. A new nullable
 * column would be harmless, but a new table is safer still — there is no way
 * for a row here to be missing, malformed or edited from a stray place that
 * breaks the room type it belongs to. One row per room type, created the
 * first time the hotel edits that room type's website copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_website', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_type_id')->unique()->index();
            $table->text('description')->nullable();

            // ["Free WiFi", "City View", "King Bed", ...] — short chips under
            // the price on the room card, not a paragraph.
            $table->json('highlights')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_website');
    }
};
