<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax stops happening by itself.
 *
 * Before this, a room row worked out its own GST from the nightly rent and a
 * folio line took whatever percent the service master carried. Neither asked.
 * A hotel that does not charge tax — or charges it on some things and not
 * others — had no way to say so.
 *
 * Every table that holds money now holds a `tax_choice` beside it:
 *
 *   `none`   no tax. The default for every new row.
 *   `slab`   the GST slab worked out from the rent per room per night.
 *   `fixed`  whatever percent is already on the row — see below.
 *   `<id>`   a row from Masters → Tax.
 *
 * **Rows that already exist are backfilled to `fixed`, not to `none`.** A
 * booking that was priced with 12% on it keeps that 12% when it is re-priced,
 * extended or split; only new rows start at nothing. Going the other way would
 * have quietly rewritten every bill in the house the first time somebody
 * changed a checkout date.
 *
 * **Every guard starts by asking whether the table exists**, so this is a
 * no-op on a database that has not run the earlier migrations and inside the
 * MySQL dump generator, which answers "no" to every schema question.
 */
return new class extends Migration
{
    /** table => the column the new one sits after */
    private const TABLES = [
        'reservation_rooms' => 'tax_percent',
        'reservation_services' => 'tax_percent',
        'check_ins' => 'tax_percent',
        'folio_charges' => 'tax_percent',
        // A POS line has no choice of its own: the order decides, and
        // "Each item's own tax" is one of the things the order can decide.
        'pos_orders' => 'tax_total',
    ];

    public function up(): void
    {
        /*
         * A stay in the house has no tax percent of its own — its rate lives on
         * the folio's room nights. Giving it one is what lets an existing guest
         * keep the tax they were checked in with: without it, the first time
         * the checkout screen re-priced their nights they would silently drop
         * to zero mid-stay.
         */
        if (Schema::hasTable('check_ins') && ! Schema::hasColumn('check_ins', 'tax_percent')) {
            Schema::table('check_ins', function (Blueprint $blueprint) {
                $blueprint->decimal('tax_percent', 6, 2)->default(0)->after('tax_type');
            });

            if (Schema::hasTable('folio_charges')) {
                DB::statement(
                    'UPDATE check_ins SET tax_percent = COALESCE((
                        SELECT MAX(fc.tax_percent) FROM folio_charges fc
                        WHERE fc.check_in_id = check_ins.id AND fc.charge_type = ?
                    ), 0)',
                    ['room']
                );
            }
        }

        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'tax_choice')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($after) {
                $blueprint->string('tax_choice', 20)->default('none')->after($after);
            });

            $this->backfill($table);
        }
    }

    /**
     * Anything already carrying tax is marked "as billed".
     *
     * `pos_orders` is the exception twice over: its tax is the sum of its
     * lines rather than a percent of its own, so it is read through
     * `tax_total`; and what it becomes is `item` rather than `fixed`, because
     * "each line takes the tax its item carries in Setup → Items" is exactly
     * what the till used to do and exactly what `item` means now.
     */
    private function backfill(string $table): void
    {
        $isOrder = $table === 'pos_orders';
        $column = $isOrder ? 'tax_total' : 'tax_percent';

        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, '>', 0)
            ->update(['tax_choice' => $isOrder ? 'item' : 'fixed']);
    }

    /**
     * Deliberately empty.
     *
     * Dropping the column would put the system back to charging tax nobody
     * asked for, which is the behaviour this migration exists to remove.
     */
    public function down(): void
    {
        //
    }
};
