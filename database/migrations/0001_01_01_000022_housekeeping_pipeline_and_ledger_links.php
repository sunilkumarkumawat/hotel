<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two small additions that the Housekeeping board and the Accounts module
 * cannot work without.
 *
 * **Cleaning is a state, not a gap.** The old list went straight from Dirty to
 * Cleaned, so a room being made up right now looked exactly like one nobody had
 * started. The board's middle column needs somewhere to stand, and the minute a
 * room has a state of its own the supervisor can see who is where — which is
 * the whole reason to look at a board rather than a list.
 *
 * **A voucher needs to know what it came from.** Posting a bill to the ledgers
 * twice is the classic accounting bug: somebody re-opens the checkout screen,
 * the entry goes in again, and the trial balance is out by exactly one bill.
 * `source_type` + `source_id` make the posting idempotent — the second attempt
 * finds the first and stops.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->housekeeping();
        $this->ledgers();
    }

    private function housekeeping(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('housekeeping_status', [
                'clean', 'dirty', 'cleaning', 'inspected', 'touch_up', 'out_of_order',
            ])->default('clean')->change();
        });

        if (! Schema::hasColumn('rooms', 'cleaning_started_at')) {
            Schema::table('rooms', function (Blueprint $table) {
                // What the board's timer counts from, and what tells a
                // supervisor that 312 has been "being cleaned" since 9am.
                $table->timestamp('cleaning_started_at')->nullable()->after('housekeeping_remark');
            });
        }

        if (! Schema::hasTable('housekeeping_logs')) {
            Schema::create('housekeeping_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('room_id')->index();
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                $table->unsignedBigInteger('housekeeper_id')->nullable();
                $table->string('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                // "What happened in this house today" — the report's query.
                $table->index(['branch_id', 'created_at']);
            });
        }
    }

    private function ledgers(): void
    {
        if (Schema::hasTable('vouchers') && ! Schema::hasColumn('vouchers', 'source_type')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->string('source_type', 40)->nullable()->after('reference_no');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->tinyInteger('is_auto')->default(0)->after('source_id');
                $table->tinyInteger('is_cancelled')->default(0)->after('is_auto');

                // The idempotence check: one voucher per source document.
                $table->index(['branch_id', 'source_type', 'source_id'], 'vouchers_source_index');
            });
        }

        if (Schema::hasTable('ledgers') && ! Schema::hasColumn('ledgers', 'code')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->string('code', 30)->nullable()->after('name');

                /*
                 * A ledger that IS the cash box, or IS a bank account, is the
                 * one a Contra voucher moves money between and the one the Cash
                 * Book reads. Naming a ledger "Cash" is not good enough — two
                 * branches, two cash boxes, one name.
                 */
                $table->enum('cash_type', ['none', 'cash', 'bank'])->default('none')->after('code');
                $table->string('bank_account_no', 40)->nullable()->after('gst_no');
                $table->string('bank_ifsc', 20)->nullable()->after('bank_account_no');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_logs');

        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'cleaning_started_at')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('cleaning_started_at');
            });
        }

        if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'source_type')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropIndex('vouchers_source_index');
                $table->dropColumn(['source_type', 'source_id', 'is_auto', 'is_cancelled']);
            });
        }

        if (Schema::hasTable('ledgers') && Schema::hasColumn('ledgers', 'code')) {
            Schema::table('ledgers', function (Blueprint $table) {
                $table->dropColumn(['code', 'cash_type', 'bank_account_no', 'bank_ifsc']);
            });
        }
    }
};
