<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The store: what the hotel bought, what it has, and where it went.
 *
 * ── Why one document table instead of six ─────────────────────────────────
 *
 * A purchase order, a goods receipt, an issue to the kitchen and a wastage
 * note are the same shape — a header with a date and a party, and a list of
 * items with quantities and rates. Six near-identical pairs of tables would
 * mean six near-identical controllers, and the sixth would be the one with the
 * bug in it. So there is one `store_docs` with a `kind`, one `store_doc_items`,
 * and the differences live in the handful of columns only some kinds use.
 *
 * ── The ledger is the truth ───────────────────────────────────────────────
 *
 * `stock_ledger` has one row per movement, ever. `store_items.current_qty` and
 * `avg_rate` are a CACHE of running that ledger, kept because a stock list
 * that recomputed every item from first principles would be unusable — and
 * rebuildable at any time from App\Support\Store::rebuild(). If the two ever
 * disagree, the ledger is right.
 *
 * Valuation is a moving weighted average, which is what a hotel's auditor
 * expects and what survives a kitchen buying the same onions at four prices in
 * a month. It is recalculated on every receipt and never on an issue: issuing
 * stock cannot change what the remaining stock cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * What kind of thing it is. Separate from the POS menu categories on
         * purpose: "Vegetables" is a store category and "Starters" is a menu
         * one, and the day somebody merges them is the day a tomato appears
         * on a menu.
         */
        if (! Schema::hasTable('store_categories')) {
            Schema::create('store_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name');
                $table->string('code', 20)->nullable();
                // Which department usually draws from it — a default, not a rule.
                $table->string('department', 40)->nullable();
                $table->unsignedTinyInteger('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('store_items')) {
            Schema::create('store_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('store_category_id')->nullable()->index();

                $table->string('name');
                $table->string('code', 40)->nullable()->index();
                // kg, litre, piece, packet. Free text because a hotel's units
                // are its own and a fixed list is a list somebody fights.
                $table->string('unit', 20)->default('kg');

                /*
                 * The cache. Both are rebuilt from `stock_ledger`; neither is
                 * ever written by a screen.
                 */
                $table->decimal('current_qty', 14, 3)->default(0);
                $table->decimal('avg_rate', 12, 2)->default(0);
                $table->decimal('last_rate', 12, 2)->default(0);

                $table->decimal('reorder_level', 14, 3)->default(0);
                $table->decimal('opening_qty', 14, 3)->default(0);
                $table->decimal('opening_rate', 12, 2)->default(0);

                $table->string('hsn_code', 12)->nullable();
                $table->decimal('tax_percent', 6, 2)->default(0);

                // Can it go in a recipe? Cooking oil yes, floor cleaner no.
                $table->boolean('is_ingredient')->default(true);

                $table->string('remark')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->index(['branch_id', 'status']);
            });
        }

        /*
         * One header for every kind of store paperwork.
         *
         * `kind` decides which columns matter:
         *   po         — vendor, expected_on, status ordered/partial/closed
         *   grn        — vendor, invoice_no, invoice_date, against_id (its PO)
         *   issue      — department, issued_to
         *   adjustment — a stock count correcting the books
         *   wastage    — thrown away, with a reason
         */
        if (! Schema::hasTable('store_docs')) {
            Schema::create('store_docs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();

                $table->enum('kind', ['po', 'grn', 'issue', 'adjustment', 'wastage'])->index();
                $table->string('doc_no', 40)->index();
                $table->date('doc_date');

                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->string('department', 40)->nullable()->index();
                $table->string('issued_to')->nullable();

                $table->string('invoice_no', 60)->nullable();
                $table->date('invoice_date')->nullable();
                $table->date('expected_on')->nullable();

                // A GRN's own purchase order, where it has one.
                $table->unsignedBigInteger('against_id')->nullable()->index();

                $table->decimal('sub_total', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('other_charges', 14, 2)->default(0);
                $table->decimal('net_amount', 14, 2)->default(0);

                /*
                 * `draft` is the only state in which a document can be edited.
                 * `posted` means it has hit the ledger and the stock has moved;
                 * from there it can only be cancelled, which posts the reverse.
                 */
                $table->enum('status', ['draft', 'posted', 'partial', 'closed', 'cancelled'])
                    ->default('draft')->index();

                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();

                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'kind', 'doc_no']);
            });
        }

        if (! Schema::hasTable('store_doc_items')) {
            Schema::create('store_doc_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_doc_id')->index();
                $table->unsignedBigInteger('store_item_id')->index();

                $table->decimal('qty', 14, 3)->default(0);
                // How much of an ordered quantity has actually turned up. Only
                // a PO uses it, and it is what makes a PO "partial".
                $table->decimal('received_qty', 14, 3)->default(0);

                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('tax_percent', 6, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);

                $table->string('remark')->nullable();
                $table->timestamps();
            });
        }

        /*
         * Every movement, ever. Nothing is updated here and nothing is
         * deleted: a correction is another row, and a cancelled document is a
         * reversing row. That is what makes "why does the book say 40kg?" a
         * question with an answer.
         */
        if (! Schema::hasTable('stock_ledger')) {
            Schema::create('stock_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('store_item_id')->index();

                $table->date('entry_date')->index();
                $table->enum('direction', ['in', 'out']);
                $table->string('kind', 20);                 // grn, issue, wastage, opening, adjustment, reversal

                $table->unsignedBigInteger('store_doc_id')->nullable()->index();
                $table->string('reference')->nullable();

                $table->decimal('qty', 14, 3);
                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('value', 14, 2)->default(0);

                /*
                 * The running balance AFTER this row, kept so a ledger screen
                 * can show it without adding up every row above. Rebuilt with
                 * the item caches.
                 */
                $table->decimal('balance_qty', 14, 3)->default(0);
                $table->decimal('balance_rate', 12, 2)->default(0);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'store_item_id', 'entry_date'], 'stock_ledger_item_date_index');
            });
        }

        /*
         * What a dish is made of, and therefore what it costs.
         *
         * Tied to a POS menu item, so the costing screen can put the recipe
         * cost next to the selling price and show the margin. `yield_qty` is
         * how many portions the recipe makes: a biryani recipe written for
         * four is priced per one.
         */
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('pos_item_id')->nullable()->index();

                $table->string('name');
                $table->decimal('yield_qty', 10, 3)->default(1);
                $table->string('yield_unit', 20)->default('portion');

                $table->text('method')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipe_id')->index();
                $table->unsignedBigInteger('store_item_id')->index();

                $table->decimal('qty', 14, 4)->default(0);
                $table->string('remark')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('store_doc_items');
        Schema::dropIfExists('store_docs');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('store_categories');
    }
};
