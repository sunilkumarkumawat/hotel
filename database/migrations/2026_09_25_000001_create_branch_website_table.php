<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public guest website — one row of content per branch.
 *
 * Nothing here touches `branches` itself. The name, address, phone and email
 * a guest sees are read straight off the existing `branches` row (the same
 * one the registration card and every bill already use); this table only
 * holds the extra, guest-facing copy that row never needed before: a
 * tagline, an about paragraph, an amenities list, policies, and the slug the
 * hotel's page lives at.
 *
 * Purely additive — a fresh table, nothing altered, nothing dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_website', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->unique()->index();

            // What the hotel picker and the URL both use — /hotels/{slug}.
            $table->string('slug', 80)->unique();

            $table->string('tagline')->nullable();
            $table->text('about')->nullable();

            // A simple list, editable from the admin screen: ["Free WiFi", "Pool", ...]
            // or a richer ["icon" => "wifi", "label" => "Free WiFi"] shape — the
            // admin form and the site view agree on this together, so either
            // shape can be read back safely.
            $table->json('amenities')->nullable();

            // Short cards, not a whole offers engine: ["title" => ..., "body" => ...].
            $table->json('offers')->nullable();

            $table->text('policies')->nullable();
            $table->string('checkin_time', 20)->nullable();
            $table->string('checkout_time', 20)->nullable();

            // public/images/hotels/<file> — same plain-path convention branches.logo
            // already uses, not Laravel's storage disk.
            $table->string('hero_image')->nullable();

            // A branch the hotel is not ready to sell online yet — set up but
            // hidden from the picker and returning 404 on its own pages.
            $table->boolean('is_published')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_website');
    }
};
