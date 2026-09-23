<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring a database that already has the POS Setup screens up to the till.
 *
 * 0001_01_01_000015 now creates all of this in one go, so on a fresh install
 * every guard below is already satisfied and this file does nothing. It exists
 * for the database that ran 000015 in its earlier shape — orders had no table,
 * no steward and no KOT rounds, items had no department, and an invoice could
 * not be signed to a room.
 *
 * **Every guard starts by asking whether `pos_orders` exists.** That keeps this
 * a no-op both on a database that has never seen POS and inside the MySQL dump
 * generator, which answers "no" to every schema question — otherwise the dump
 * would carry `pos_menu_item_prices` twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        $this->growOrders();
        $this->growOrderItems();
        $this->growInvoices();
        $this->growMenuItems();
        $this->addItemPrices();
    }

    /**
     * An order grew from "what was sold" into "who sold it, to which table, on
     * which price list, and how many times the kitchen has been told".
     */
    private function growOrders(): void
    {
        $columns = [
            'pos_table_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pos_table_id')->nullable()->index(),
            'pos_steward_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pos_steward_id')->nullable()->index(),
            'pos_rate_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pos_rate_plan_id')->nullable()->index(),
            'nc_type_id' => fn (Blueprint $t) => $t->unsignedBigInteger('nc_type_id')->nullable(),
            'nc_department_id' => fn (Blueprint $t) => $t->unsignedBigInteger('nc_department_id')->nullable(),
            'kot_count' => fn (Blueprint $t) => $t->unsignedSmallInteger('kot_count')->default(0),
            'discount_percent' => fn (Blueprint $t) => $t->decimal('discount_percent', 5, 2)->default(0),
            'service_charge' => fn (Blueprint $t) => $t->decimal('service_charge', 12, 2)->default(0),
            'round_off' => fn (Blueprint $t) => $t->decimal('round_off', 8, 2)->default(0),
        ];

        $this->addTo('pos_orders', $columns);
    }

    private function growOrderItems(): void
    {
        $this->addTo('pos_order_items', [
            'pos_department_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pos_department_id')->nullable()->index(),
            'kot_no' => fn (Blueprint $t) => $t->unsignedSmallInteger('kot_no')->default(0),
            'fired_at' => fn (Blueprint $t) => $t->dateTime('fired_at')->nullable(),
            'kitchen_status' => fn (Blueprint $t) => $t
                ->enum('kitchen_status', ['pending', 'preparing', 'ready', 'served'])
                ->default('pending')->index(),
            'is_nc' => fn (Blueprint $t) => $t->tinyInteger('is_nc')->default(0),
            'sort' => fn (Blueprint $t) => $t->unsignedSmallInteger('sort')->default(0),
        ]);
    }

    private function growInvoices(): void
    {
        $this->addTo('pos_invoices', [
            'guest_name' => fn (Blueprint $t) => $t->string('guest_name')->nullable(),
            'check_in_id' => fn (Blueprint $t) => $t->unsignedBigInteger('check_in_id')->nullable()->index(),
            'folio_charge_id' => fn (Blueprint $t) => $t->unsignedBigInteger('folio_charge_id')->nullable(),
            'folio_amount' => fn (Blueprint $t) => $t->decimal('folio_amount', 12, 2)->default(0),
            'settled_at' => fn (Blueprint $t) => $t->dateTime('settled_at')->nullable(),
        ]);
    }

    private function growMenuItems(): void
    {
        $this->addTo('pos_menu_items', [
            'pos_department_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pos_department_id')->nullable()->index(),
            'sort' => fn (Blueprint $t) => $t->unsignedSmallInteger('sort')->default(0),
            'deleted_at' => fn (Blueprint $t) => $t->softDeletes(),
        ]);
    }

    private function addItemPrices(): void
    {
        if (Schema::hasTable('pos_menu_item_prices')) {
            return;
        }

        Schema::create('pos_menu_item_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_menu_item_id')->index();
            $table->unsignedBigInteger('pos_rate_plan_id')->index();
            $table->decimal('price', 12, 2)->default(0);

            $table->unique(['pos_menu_item_id', 'pos_rate_plan_id']);
        });
    }

    /**
     * Add whichever of these columns is missing, one at a time.
     *
     * One statement per column rather than one for the lot: a database that was
     * half-upgraded by an interrupted run finishes the job instead of falling
     * over on the first column it already has.
     *
     * @param  array<string, callable(Blueprint): mixed>  $columns
     */
    private function addTo(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $name => $add) {
            if (Schema::hasColumn($table, $name)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $add($t));
        }
    }

    /**
     * Deliberately empty.
     *
     * Rolling back would drop columns that now hold real bills. 000015's own
     * down() removes the POS tables outright, which is the honest way to undo
     * this.
     */
    public function down(): void
    {
        //
    }
};
