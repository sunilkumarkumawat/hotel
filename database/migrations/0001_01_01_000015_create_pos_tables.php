<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point of Sale — the restaurant, room service, the bar.
 *
 * The shape the POS Dashboard reads. An **order** is what the kitchen works
 * from; an **invoice** is what the guest pays. They are separate because an
 * order can be cancelled before it is ever billed, and one table's two orders
 * can be settled on one invoice.
 *
 * `pos_audit_logs` is the Revenue Control strip: deleting an invoice, taking an
 * item off a running order, changing a price, re-printing a bill. None of those
 * are wrong on their own — a guest does change their mind — but they are the
 * four things a till is skimmed through, so the hotel counts them.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Where the sale happened. "Outlet" rather than "restaurant" because
         * room service and the bar sell through the same till.
         *
         * An outlet carries three separate sets of settings and they are kept
         * apart on purpose:
         *   • who it is — name, address, the tax numbers that print on a bill;
         *   • how it sells — which order types it takes, whether the price
         *     already includes tax, whether discount comes off before or after
         *     tax, its own bill series (and a second one for liquor, which many
         *     states require to be billed separately);
         *   • how it prints — the thermal roll is 58 mm or 80 mm and the header
         *     has to be sized for it.
         *
         * Soft-deleted rather than deleted: an outlet that sold ten thousand
         * bills cannot vanish, or every one of those bills loses the name of
         * the place that rang it up.
         */
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->enum('kind', ['restaurant', 'room_service', 'bar', 'banquet', 'other'])
                ->default('restaurant');

            // Who it is
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('phone1', 30)->nullable();
            $table->string('phone2', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('gst_no', 20)->nullable();
            $table->string('cin_no', 30)->nullable();
            $table->string('pan_no', 20)->nullable();
            $table->string('sac_code', 20)->nullable();

            // When it is open. Both null means round the clock.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // How it sells
            $table->string('bill_series', 20)->nullable();
            $table->unsignedInteger('bill_start_no')->default(1);
            $table->tinyInteger('is_retail')->default(0);
            $table->tinyInteger('tax_inclusive')->default(0);
            $table->tinyInteger('discount_after_tax')->default(0);
            $table->tinyInteger('allow_open_item')->default(0);
            $table->tinyInteger('pos_dine_in')->default(1);
            $table->tinyInteger('pos_room_service')->default(0);
            $table->tinyInteger('pos_delivery')->default(0);
            $table->tinyInteger('pos_take_away')->default(0);
            $table->tinyInteger('split_liquor_bill')->default(0);
            $table->tinyInteger('diff_liquor_series')->default(0);
            $table->string('liquor_bill_series', 20)->nullable();
            $table->tinyInteger('auto_settle')->default(0);
            $table->tinyInteger('show_order_notification')->default(0);
            $table->tinyInteger('show_last_orders')->default(0);

            // How it prints
            $table->unsignedSmallInteger('page_width')->default(80);   // mm
            $table->unsignedSmallInteger('print_margin')->default(6);  // mm
            $table->string('print_header')->nullable();
            $table->string('tax_invoice_name')->nullable();
            $table->string('header_font', 40)->nullable();
            $table->unsignedSmallInteger('header_font_size')->default(0);
            $table->tinyInteger('header_font_bold')->default(1);
            $table->string('print_footer')->nullable();
            $table->tinyInteger('guest_signature_print')->default(1);
            $table->string('logo_path')->nullable();

            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /*
         * Which users may ring a sale through which till.
         *
         * Empty means everybody: a hotel with one restaurant should not have to
         * tick twelve names before anybody can bill.
         */
        Schema::create('outlet_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->unique(['outlet_id', 'user_id']);
        });

        /*
         * Seating. A group is a section of the floor — Non AC, Terrace, Pool —
         * and `kind` is what the things inside it are called, because the same
         * screen seats a restaurant's tables, a resort's villas and a service
         * apartment's flats.
         */
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

        /*
         * The menu tree. `type` says whether a row is a heading or sits under
         * one; a sub-category names its parent so the POS screen can show
         * Beverages → Hot Beverages rather than one flat wall of buttons.
         */
        Schema::create('pos_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->string('name');
            $table->enum('type', ['category', 'sub_category'])->default('category');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /*
         * One thing on the menu. `price` is the everyday price; a rate plan
         * that charges something else says so in `pos_menu_item_prices`, and
         * the till falls back to this column when no plan is running.
         *
         * `pos_department_id` is what routes the KOT: this line goes to the
         * bar, that one to the kitchen. An item with no department still sells
         * — it just lands on the default ticket.
         */
        Schema::create('pos_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('pos_menu_category_id')->nullable()->index();
            $table->unsignedBigInteger('pos_department_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedBigInteger('tax_master_id')->nullable();
            $table->tinyInteger('is_veg')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /*
         * A rate plan is one price list. The same dosa is ₹180 on the à la
         * carte plan and ₹120 during Happy Hours, and a plan is switched on per
         * outlet — the bar runs Happy Hours, the coffee shop does not.
         */
        Schema::create('pos_rate_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('pos_rate_plan_outlet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_rate_plan_id')->index();
            $table->unsignedBigInteger('outlet_id')->index();

            $table->unique(['pos_rate_plan_id', 'outlet_id']);
        });

        /*
         * What an item costs on a particular plan. Only the exceptions are
         * stored — an item the Happy Hours plan does not discount simply has no
         * row here and sells at its own price.
         */
        Schema::create('pos_menu_item_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_menu_item_id')->index();
            $table->unsignedBigInteger('pos_rate_plan_id')->index();
            $table->decimal('price', 12, 2)->default(0);

            $table->unique(['pos_menu_item_id', 'pos_rate_plan_id']);
        });

        /*
         * Who cooks it. A KOT for a drink goes to the bar printer and a KOT for
         * a curry goes to the kitchen, so every menu item is eventually pinned
         * to a department.
         */
        Schema::create('pos_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /** Waiting staff. A bill carries the steward's name so tips and service can be traced. */
        Schema::create('pos_stewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /*
         * No Charge reasons. Food that leaves the kitchen without being paid
         * for is not theft — it is a comp, a staff meal, a tasting — but it has
         * to be named, and `requires_department` forces the "which department
         * eats this cost" question for the ones that need answering.
         */
        Schema::create('pos_nc_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->tinyInteger('requires_department')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        /*
         * Table-reservation slots: the sittings an outlet takes bookings for,
         * and how many tables it will hold in each. One row per outlet per
         * time — the unique index is what stops two 7:30 PM rows appearing and
         * the covers being counted twice.
         */
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

        /*
         * One order = one KOT run. `order_type` is how it was sold, which is a
         * different question from which outlet sold it: the restaurant takes
         * dine-in and take-away, room service is always to a room.
         */
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->string('order_no', 40);
            $table->enum('order_type', ['dine_in', 'room_service', 'delivery', 'take_away'])
                ->default('dine_in')->index();
            $table->string('table_no', 30)->nullable();
            $table->unsignedBigInteger('room_id')->nullable();          // room service
            $table->unsignedBigInteger('check_in_id')->nullable();      // post to the folio
            $table->string('guest_name')->nullable();
            $table->unsignedTinyInteger('pax')->default(1);

            // Who and what it was sold against.
            $table->unsignedBigInteger('pos_table_id')->nullable()->index();
            $table->unsignedBigInteger('pos_steward_id')->nullable()->index();
            $table->unsignedBigInteger('pos_rate_plan_id')->nullable()->index();

            // A no-charge order names its reason and whose budget carries it.
            $table->unsignedBigInteger('nc_type_id')->nullable();
            $table->unsignedBigInteger('nc_department_id')->nullable();

            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();                  // turn-around time
            $table->tinyInteger('is_complimentary')->default(0);

            // How many KOT rounds have gone to the kitchen. Round 2 prints only
            // what round 1 did not, which is the whole point of counting them.
            $table->unsignedSmallInteger('kot_count')->default(0);

            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            $table->enum('status', ['open', 'billed', 'settled', 'cancelled'])->default('open')->index();
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'order_no']);
        });

        /*
         * One line on an order.
         *
         * `kot_no` is the round it was sent to the kitchen in — 0 means it is
         * still sitting on the screen and has not been ordered yet, which is
         * the only kind of line the till lets you freely edit or remove.
         * `kitchen_status` is the Kitchen Display System's own column.
         */
        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_order_id')->index();
            $table->unsignedBigInteger('pos_menu_item_id')->nullable()->index();
            $table->unsignedBigInteger('pos_department_id')->nullable()->index();
            $table->unsignedSmallInteger('kot_no')->default(0);
            $table->dateTime('fired_at')->nullable();
            $table->enum('kitchen_status', ['pending', 'preparing', 'ready', 'served'])
                ->default('pending')->index();
            $table->tinyInteger('is_nc')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('item_name');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('outlet_id')->nullable()->index();
            $table->unsignedBigInteger('pos_order_id')->nullable()->index();
            $table->string('invoice_no', 40);
            $table->dateTime('invoice_at');
            $table->string('guest_name')->nullable();

            // A bill signed to a room is settled by the folio, not by cash:
            // `folio_charge_id` is the line it became on the guest's bill.
            $table->unsignedBigInteger('check_in_id')->nullable()->index();
            $table->unsignedBigInteger('folio_charge_id')->nullable();
            // How much was signed, which is not always the whole bill: a guest
            // can pay part in cash and sign the rest to the room.
            $table->decimal('folio_amount', 12, 2)->default(0);
            $table->dateTime('settled_at')->nullable();
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->enum('status', ['open', 'settled', 'cancelled'])->default('open')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'invoice_no']);
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('pos_invoice_id')->index();
            $table->unsignedBigInteger('pay_mode_id')->nullable()->index();  // masters → Pay Mode
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference_no', 60)->nullable();
            $table->dateTime('paid_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /*
         * The four things Revenue Control counts. Written by the POS screens as
         * they happen; the dashboard only reads them.
         */
        Schema::create('pos_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->enum('action', [
                'invoice_deleted', 'item_removed', 'item_modified', 'invoice_reprinted',
            ])->index();
            $table->unsignedBigInteger('pos_order_id')->nullable();
            $table->unsignedBigInteger('pos_invoice_id')->nullable();
            $table->string('particulars')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->dateTime('happened_at')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'pos_audit_logs', 'pos_payments', 'pos_invoices', 'pos_order_items',
            'pos_orders', 'pos_reservation_slots', 'pos_nc_types', 'pos_stewards',
            'pos_departments', 'pos_menu_item_prices', 'pos_rate_plan_outlet', 'pos_rate_plans',
            'pos_menu_items', 'pos_menu_categories', 'pos_tables',
            'pos_table_groups', 'outlet_user', 'outlets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
