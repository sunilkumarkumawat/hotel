<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A way for a guest who scans a table's own QR to be told, on their own
 * phone, that their order was heard — added after the fact, once staff asked
 * for it. Both columns are nullable and the guest is never made to fill
 * either one in: a request with neither still works exactly as it did before
 * this migration, it just has nobody to message.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_guest_requests')) {
            return;
        }

        Schema::table('pos_guest_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_guest_requests', 'guest_mobile')) {
                $table->string('guest_mobile', 20)->nullable()->after('guest_note');
            }

            if (! Schema::hasColumn('pos_guest_requests', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_mobile');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_guest_requests')) {
            return;
        }

        Schema::table('pos_guest_requests', function (Blueprint $table) {
            foreach (['guest_mobile', 'guest_email'] as $column) {
                if (Schema::hasColumn('pos_guest_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
