<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring a database that already has the POS dashboard up to the Setup screens.
 *
 * 0001_01_01_000015 now creates all of this in one go, so on a fresh install
 * every guard below is already satisfied and this file does nothing. It exists
 * for the database that ran 000015 in its earlier, thinner shape — an outlet
 * was a name and a kind, and there were no tables, rate plans, departments,
 * stewards, NC types or reservation slots at all.
 *
 * **Every guard starts by asking whether `outlets` exists.** That keeps this a
 * no-op both on a database that has never seen POS and inside the MySQL dump
 * generator, which answers "no" to every schema question — otherwise the dump
 * would carry each of these tables twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outlets')) {
            return;
        }

        $this->widenOutlets();
        $this->growMenuCategories();
        $this->addSetupTables();
    }

    /**
     * The outlet grew from a label into a till: who it is, how it sells, how it
     * prints. Added column by column so a half-upgraded database finishes the
     * job rather than falling over on the first one that already exists.
     */
    private function widenOutlets(): void
    {
        $columns = [
            // Who it is
            'address1' => fn (Blueprint $t) => $t->string('address1')->nullable(),
            'address2' => fn (Blueprint $t) => $t->string('address2')->nullable(),
            'address3' => fn (Blueprint $t) => $t->string('address3')->nullable(),
            'phone1' => fn (Blueprint $t) => $t->string('phone1', 30)->nullable(),
            'phone2' => fn (Blueprint $t) => $t->string('phone2', 30)->nullable(),
            'website' => fn (Blueprint $t) => $t->string('website')->nullable(),
            'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
            'gst_no' => fn (Blueprint $t) => $t->string('gst_no', 20)->nullable(),
            'cin_no' => fn (Blueprint $t) => $t->string('cin_no', 30)->nullable(),
            'pan_no' => fn (Blueprint $t) => $t->string('pan_no', 20)->nullable(),
            'sac_code' => fn (Blueprint $t) => $t->string('sac_code', 20)->nullable(),

            // When it is open
            'start_time' => fn (Blueprint $t) => $t->time('start_time')->nullable(),
            'end_time' => fn (Blueprint $t) => $t->time('end_time')->nullable(),

            // How it sells
            'bill_series' => fn (Blueprint $t) => $t->string('bill_series', 20)->nullable(),
            'bill_start_no' => fn (Blueprint $t) => $t->unsignedInteger('bill_start_no')->default(1),
            'is_retail' => fn (Blueprint $t) => $t->tinyInteger('is_retail')->default(0),
            'tax_inclusive' => fn (Blueprint $t) => $t->tinyInteger('tax_inclusive')->default(0),
            'discount_after_tax' => fn (Blueprint $t) => $t->tinyInteger('discount_after_tax')->default(0),
            'allow_open_item' => fn (Blueprint $t) => $t->tinyInteger('allow_open_item')->default(0),
            'pos_dine_in' => fn (Blueprint $t) => $t->tinyInteger('pos_dine_in')->default(1),
            'pos_room_service' => fn (Blueprint $t) => $t->tinyInteger('pos_room_service')->default(0),
            'pos_delivery' => fn (Blueprint $t) => $t->tinyInteger('pos_delivery')->default(0),
            'pos_take_away' => fn (Blueprint $t) => $t->tinyInteger('pos_take_away')->default(0),
            'split_liquor_bill' => fn (Blueprint $t) => $t->tinyInteger('split_liquor_bill')->default(0),
            'diff_liquor_series' => fn (Blueprint $t) => $t->tinyInteger('diff_liquor_series')->default(0),
            'liquor_bill_series' => fn (Blueprint $t) => $t->string('liquor_bill_series', 20)->nullable(),
            'auto_settle' => fn (Blueprint $t) => $t->tinyInteger('auto_settle')->default(0),
            'show_order_notification' => fn (Blueprint $t) => $t->tinyInteger('show_order_notification')->default(0),
            'show_last_orders' => fn (Blueprint $t) => $t->tinyInteger('show_last_orders')->default(0),

            // How it prints
            'page_width' => fn (Blueprint $t) => $t->unsignedSmallInteger('page_width')->default(80),
            'print_margin' => fn (Blueprint $t) => $t->unsignedSmallInteger('print_margin')->default(6),
            'print_header' => fn (Blueprint $t) => $t->string('print_header')->nullable(),
            'tax_invoice_name' => fn (Blueprint $t) => $t->string('tax_invoice_name')->nullable(),
            'header_font' => fn (Blueprint $t) => $t->string('header_font', 40)->nullable(),
            'header_font_size' => fn (Blueprint $t) => $t->unsignedSmallInteger('header_font_size')->default(0),
            'header_font_bold' => fn (Blueprint $t) => $t->tinyInteger('header_font_bold')->default(1),
            'print_footer' => fn (Blueprint $t) => $t->string('print_footer')->nullable(),
            'guest_signature_print' => fn (Blueprint $t) => $t->tinyInteger('guest_signature_print')->default(1),
            'logo_path' => fn (Blueprint $t) => $t->string('logo_path')->nullable(),
        ];

        $missing = array_filter(
            $columns,
            fn (string $column) => ! Schema::hasColumn('outlets', $column),
            ARRAY_FILTER_USE_KEY
        );

        if ($missing) {
            Schema::table('outlets', function (Blueprint $table) use ($missing) {
                foreach ($missing as $define) {
                    $define($table);
                }
            });
        }

        if (! Schema::hasColumn('outlets', 'deleted_at')) {
            Schema::table('outlets', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    /** Item Category gained a heading/sub-heading split and a parent. */
    private function growMenuCategories(): void
    {
        if (! Schema::hasTable('pos_menu_categories')) {
            return;
        }

        if (! Schema::hasColumn('pos_menu_categories', 'type')) {
            Schema::table('pos_menu_categories', function (Blueprint $table) {
                $table->enum('type', ['category', 'sub_category'])->default('category');
                $table->unsignedBigInteger('parent_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('pos_menu_categories', 'deleted_at')) {
            Schema::table('pos_menu_categories', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    /** The seven lists the Setup screens read and write. */
    private function addSetupTables(): void
    {
        if (! Schema::hasTable('outlet_user')) {
            Schema::create('outlet_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('outlet_id')->index();
                $table->unsignedBigInteger('user_id')->index();

                $table->unique(['outlet_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('pos_table_groups')) {
            Schema::create('pos_table_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->index();
                $table->string('name');
                $table->enum('kind', ['apartment', 'room', 'table', 'villa'])->default('table');
                $table->unsignedSmallInteger('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_tables')) {
            Schema::create('pos_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->index();
                $table->unsignedBigInteger('pos_table_group_id')->index();
                $table->string('name', 60);
                $table->unsignedSmallInteger('capacity')->default(0);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_rate_plans')) {
            Schema::create('pos_rate_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_rate_plan_outlet')) {
            Schema::create('pos_rate_plan_outlet', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_rate_plan_id')->index();
                $table->unsignedBigInteger('outlet_id')->index();

                $table->unique(['pos_rate_plan_id', 'outlet_id']);
            });
        }

        if (! Schema::hasTable('pos_departments')) {
            Schema::create('pos_departments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_stewards')) {
            Schema::create('pos_stewards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->string('phone', 30)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_nc_types')) {
            Schema::create('pos_nc_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->tinyInteger('requires_department')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_reservation_slots')) {
            Schema::create('pos_reservation_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->index();
                $table->time('slot_time');
                $table->unsignedSmallInteger('max_booking')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['branch_id', 'outlet_id', 'slot_time']);
            });
        }
    }

    /**
     * Deliberately empty.
     *
     * Rolling back would drop tables that 000015 also creates, and dropping the
     * outlet columns would throw away a hotel's bill series and print setup.
     * The way back is `migrate:rollback` far enough to take 000015 with it.
     */
    public function down(): void
    {
        //
    }
};
