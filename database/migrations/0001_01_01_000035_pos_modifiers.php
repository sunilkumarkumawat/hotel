<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modifiers and add-ons — "extra cheese +₹20" priced against a menu item.
 *
 * A **modifier group** is the question — Size, Toppings, Spice Level — and
 * `selection_type` says whether it takes one answer or several; `is_required`
 * says whether it may be left unanswered. A **modifier** is one answer inside
 * a group, with its own surcharge. `pos_menu_item_modifier_group` is which
 * groups a dish actually asks — a pivot exactly like pos_rate_plan_outlet,
 * assigned from the Items screen the same way a rate plan is assigned outlets.
 *
 * What a guest actually picked is never re-derived from the menu after the
 * fact — `pos_order_item_modifiers` snapshots the name and price at the
 * moment it was added, the same way `pos_order_items.item_name` snapshots the
 * dish itself, so a modifier renamed or re-priced next month cannot rewrite
 * what last night's bill said. The surcharge itself is folded straight into
 * `pos_order_items.price` rather than kept as a separate column — the whole
 * point being that PosTill::recalculate() and every tax and discount rule it
 * already runs need not know modifiers exist at all. `has_modifiers` is only
 * a fast flag so PosTill::addItem() never merges a plain add into a line that
 * is carrying a surcharge, or the other way round.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_menu_items')) {
            return;
        }

        if (! Schema::hasTable('pos_modifier_groups')) {
            Schema::create('pos_modifier_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->enum('selection_type', ['single', 'multiple'])->default('multiple');
                $table->tinyInteger('is_required')->default(0);
                $table->unsignedSmallInteger('max_select')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_modifiers')) {
            Schema::create('pos_modifiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('pos_modifier_group_id')->index();
                $table->string('name');
                $table->decimal('price', 12, 2)->default(0);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_menu_item_modifier_group')) {
            Schema::create('pos_menu_item_modifier_group', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_menu_item_id')->index();
                $table->unsignedBigInteger('pos_modifier_group_id')->index();

                // Named explicitly — the auto-generated name (table + both
                // columns + "_unique") runs past MySQL's 64-character limit.
                $table->unique(['pos_menu_item_id', 'pos_modifier_group_id'], 'item_group_unique');
            });
        }

        if (! Schema::hasTable('pos_order_item_modifiers')) {
            Schema::create('pos_order_item_modifiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_order_item_id')->index();
                // Nullable: the modifier this snapshot came from may itself be
                // deleted later. The row still prints correctly because it
                // carries its own name and price, not a lookup.
                $table->unsignedBigInteger('pos_modifier_id')->nullable()->index();
                $table->string('name');
                $table->decimal('price', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('pos_order_items', 'has_modifiers')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->tinyInteger('has_modifiers')->default(0)->after('is_nc');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_order_items', 'has_modifiers')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->dropColumn('has_modifiers');
            });
        }

        Schema::dropIfExists('pos_order_item_modifiers');
        Schema::dropIfExists('pos_menu_item_modifier_group');
        Schema::dropIfExists('pos_modifiers');
        Schema::dropIfExists('pos_modifier_groups');
    }
};
