<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One family, five rooms, one bill — without giving up five bills.
 *
 * A family checking out of five rooms wants a single total and a single sheet
 * of paper. Accounting wants five numbered bills, one per room, because that is
 * what a room's revenue is reported against and what a GST auditor asks for.
 *
 * `group_no` satisfies both: the five bills stay five bills, and they carry the
 * same group number so the desk can print them under one cover with one grand
 * total, or hand out any single one on its own.
 *
 * **The guard starts by asking whether `bills` exists**, so this is a no-op on
 * a database that has never run the front office migration and inside the MySQL
 * dump generator, which answers "no" to every schema question.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bills')) {
            return;
        }

        if (! Schema::hasColumn('bills', 'group_no')) {
            Schema::table('bills', function (Blueprint $table) {
                // Nullable on purpose: a single-room checkout has no group, and
                // reading "this bill is not part of a group" off a NULL is
                // clearer than off an empty string.
                $table->string('group_no', 40)->nullable()->index();
            });
        }
    }

    /**
     * Deliberately empty.
     *
     * Dropping the column would break the link between bills that were printed
     * to a guest as one document.
     */
    public function down(): void
    {
        //
    }
};
