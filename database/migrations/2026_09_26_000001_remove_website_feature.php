<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the public hotel website feature entirely: the four tables it
 * added, and the "Website Content" sidebar entry that
 * 2026_09_25_000005_add_website_content_menu_entry.php created.
 *
 * The routes, controllers, views and models for the feature are removed
 * from the codebase in the same update that adds this migration — this
 * file only cleans up what the feature left behind in the database, so a
 * hotel that ran it for a few days does not keep empty tables and a dead
 * sidebar link forever.
 *
 * Same defensive shape as the migration that added the menu entry: every
 * step is guarded by a table/column existence check, nothing here is
 * assumed, and a failure is caught and logged rather than allowed to break
 * the rest of the batch — a database that never had these tables, or never
 * had the menu entry, must still migrate cleanly.
 */
return new class extends Migration
{
    private const SUBMODULE_URL = 'administration/website';

    public function up(): void
    {
        $this->removeMenuEntry();

        Schema::dropIfExists('website_payments');
        Schema::dropIfExists('room_type_images');
        Schema::dropIfExists('room_type_website');
        Schema::dropIfExists('branch_website');
    }

    public function down(): void
    {
        // One-way. The tables' own create migrations are what used to know
        // how to rebuild them; nothing here brings the website feature back.
    }

    private function removeMenuEntry(): void
    {
        if (! Schema::hasTable('submodule')) {
            return;
        }

        try {
            DB::table('submodule')->where('url', self::SUBMODULE_URL)->delete();

            // Only the fallback "Website" module this feature may have
            // created for itself — an existing "…admin…" module the old
            // migration found and reused instead is never touched here.
            if (Schema::hasTable('module')) {
                DB::table('module')
                    ->where('name', 'Website')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('submodule')
                            ->whereColumn('submodule.module_id', 'module.id');
                    })
                    ->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
};
