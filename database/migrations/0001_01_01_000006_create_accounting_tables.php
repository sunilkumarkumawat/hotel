<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Petty cash and accounting.
 *
 * Accounting is a plain double-entry set: a `voucher` header with two or more
 * `voucher_entries` that must balance. Day book, cash book, bank book and the
 * ledger statement are all views over those two tables, so nothing has to be
 * kept in sync by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | Petty cash
        |----------------------------------------------------------------------
        */

        Schema::create('petty_cash_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('voucher_no', 40)->index();
            $table->date('voucher_date');
            $table->unsignedBigInteger('expense_head_id')->nullable();
            $table->unsignedBigInteger('pay_mode_id')->nullable();
            $table->string('paid_to')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('bill_no', 60)->nullable();
            $table->string('remark')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('voucher_no', 40)->index();
            $table->date('voucher_date');
            $table->unsignedBigInteger('receive_head_id')->nullable();
            $table->unsignedBigInteger('pay_mode_id')->nullable();
            $table->string('received_from')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        /*
        |----------------------------------------------------------------------
        | Accounting
        |----------------------------------------------------------------------
        */

        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');                                  // Sundry Debtors
            $table->enum('nature', ['asset', 'liability', 'income', 'expense'])->default('asset');
            $table->tinyInteger('is_system')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('account_group_id')->nullable()->index();
            $table->string('name');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->enum('balance_type', ['dr', 'cr'])->default('dr');
            $table->string('gst_no', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->tinyInteger('is_system')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->enum('voucher_type', ['payment', 'receipt', 'contra', 'journal', 'sales', 'purchase'])
                ->default('journal')->index();
            $table->string('voucher_no', 40)->index();
            $table->date('voucher_date')->index();
            $table->string('reference_no', 60)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('narration')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'voucher_type', 'voucher_no']);
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->index();
            $table->unsignedBigInteger('ledger_id')->index();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('narration')->nullable();
            $table->timestamps();
        });

        Schema::create('e_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('bill_id')->nullable()->index();
            $table->string('bill_no', 40)->nullable();
            $table->string('irn', 100)->nullable();
            $table->string('ack_no', 60)->nullable();
            $table->date('ack_date')->nullable();
            $table->text('qr_code')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'failed', 'cancelled'])->default('pending')->index();
            $table->text('response')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'e_invoices', 'voucher_entries', 'vouchers', 'ledgers', 'account_groups',
            'petty_cash_receipts', 'petty_cash_payments',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
