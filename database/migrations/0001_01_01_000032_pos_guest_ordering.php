<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The QR on a table card used to be a staff-only shortcut — scan it while
 * signed in and it jumped straight to that table's order screen. A guest who
 * scanned it with no account just hit the login page, even though the card
 * itself says "Scan to see the menu and order from your phone".
 *
 * This is what makes that promise true: a guest picks items on their own
 * phone, with no account, and sends them in as a request. Nothing reaches the
 * kitchen on its own — a member of staff still has to look at the request and
 * accept it, same as if the guest had told a waiter. `pos_guest_requests` is
 * that one request, from the moment it is sent to the moment somebody at the
 * desk accepts or declines it.
 *
 * `photo` on `pos_menu_items` is the other half: a guest browsing on their
 * phone reads a picture faster than a name. Veg/Non-Veg is not added here —
 * `is_veg` has been on this table since the POS billing migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_tables')) {
            return;
        }

        if (! Schema::hasTable('pos_guest_requests')) {
            Schema::create('pos_guest_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('pos_table_id')->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();

                // What stands in for a login on the guest's own status page —
                // forty random characters, exactly like the feedback link, so
                // it is nothing to guess and nothing to count up through.
                $table->string('token', 40)->unique();

                /*
                 * What the guest asked for, as they asked for it: item id,
                 * the name and price AT THE TIME (so the request still reads
                 * sensibly even if the menu changes before anyone looks at
                 * it), quantity and any note. What actually gets billed is
                 * decided fresh at approval time, from the live menu — this
                 * is a record of the ask, not a price quote.
                 */
                $table->json('items');
                $table->string('guest_note', 255)->nullable();

                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
                $table->unsignedBigInteger('pos_order_id')->nullable()->index();
                $table->unsignedInteger('kot_no')->nullable();
                $table->string('decline_reason', 255)->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->timestamps();
            });
        }

        if (Schema::hasTable('pos_menu_items') && ! Schema::hasColumn('pos_menu_items', 'photo')) {
            Schema::table('pos_menu_items', function (Blueprint $table) {
                // A storage-disk path, same convention as outlets.logo_path —
                // null means "no picture yet", not "broken".
                $table->string('photo')->nullable()->after('is_veg');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_guest_requests');

        if (Schema::hasColumn('pos_menu_items', 'photo')) {
            Schema::table('pos_menu_items', fn (Blueprint $table) => $table->dropColumn('photo'));
        }
    }
};
