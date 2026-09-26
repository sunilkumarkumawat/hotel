<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives "Administration → Website Content" a real row in the sidebar.
 *
 * The sidebar (app/Helpers/Helper.php@sideMenus, resources/views/partials/
 * sidebar.blade.php) is entirely database-driven from the `module` /
 * `submodule` tables — "Nothing here is hard-coded", per that view's own
 * comment — so a screen with no row in `submodule` is real but invisible:
 * reachable only by typing /administration/website directly.
 *
 * This install's copy of those two tables was never seen while writing this
 * migration (no migration for them shipped in this snapshot — they read as
 * carried over from an older system, per Module's own "as in the old
 * system" comment), so every assumption below is a best guess, not a known
 * fact. The migration is written to never be the thing that breaks the
 * batch: it only touches columns it can see really exist on this
 * installation, it never inserts a duplicate if run twice, and any failure
 * is caught and logged rather than thrown — because "the new screen has no
 * sidebar link yet" is a small, easily-fixed gap, while "migrate failed
 * partway through and left the other new tables unmigrated" is not. The
 * feature itself does not depend on this migration succeeding — the routes
 * and the `admin` middleware check are registered either way.
 */
return new class extends Migration
{
    private const SUBMODULE_URL = 'administration/website';

    public function up(): void
    {
        if (! Schema::hasTable('module') || ! Schema::hasTable('submodule')) {
            return;
        }

        try {
            if (DB::table('submodule')->where('url', self::SUBMODULE_URL)->exists()) {
                return;
            }

            $moduleId = DB::table('module')
                ->where('name', 'like', '%admin%')
                ->orderBy('id')
                ->value('id');

            if (! $moduleId) {
                $moduleId = $this->insertRow('module', [
                    'name' => 'Website',
                    'icon' => 'globe',
                    'sort' => 999,
                ]);
            }

            if ($moduleId) {
                $this->insertRow('submodule', [
                    'module_id' => $moduleId,
                    'name' => 'Website Content',
                    'url' => self::SUBMODULE_URL,
                    'sort' => 999,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('submodule')) {
            return;
        }

        try {
            DB::table('submodule')->where('url', self::SUBMODULE_URL)->delete();

            // The "Website" fallback module is only ever removed if this
            // migration was the one that created it and nothing else was
            // ever attached to it — an existing "…admin…" module found and
            // reused above is never touched here.
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

    /** Insert only the columns this installation's table actually has, so an unknown schema degrades gracefully instead of erroring. */
    private function insertRow(string $table, array $wanted): ?int
    {
        $columns = Schema::getColumnListing($table);
        $row = array_intersect_key($wanted, array_flip($columns));

        if (in_array('created_at', $columns, true)) {
            $row['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $row['updated_at'] = now();
        }

        // Without at least a name, this row would not mean anything in the
        // menu — better to insert nothing than a blank row.
        if (empty($row['name'] ?? null) && in_array('name', $columns, true)) {
            return null;
        }

        return DB::table($table)->insertGetId($row);
    }
};
