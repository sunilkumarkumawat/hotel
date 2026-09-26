<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master lookup lists become shared across every branch.
 *
 * MasterController now saves Tax, Pay Mode, Room Category/Type, Plan Type,
 * Services, Companies, Vendors, Booked By, Business Market, Visit Purpose,
 * Pick/Drop, Billing Instruction and Expense/Receive Head with branch_id
 * NULL — which BaseMaster::scopeForBranch() already reads as "every branch
 * may use this row". This backfills everything that was added before that
 * change, so nothing already set up has to be typed in again for a new
 * branch.
 *
 * Rooms are deliberately left alone: a physical room stands in exactly one
 * building, so it stays tied to whichever branch it was added under.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $sharedTables = [
        'room_category', 'room_type', 'plan_type', 'tax_master', 'services',
        'companies', 'vendors', 'booked_by', 'business_market', 'visit_purpose',
        'pick_drop', 'billing_instruction', 'pay_mode', 'expense_head', 'receive_head',
    ];

    public function up(): void
    {
        foreach ($this->sharedTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->update(['branch_id' => null]);
            }
        }
    }

    public function down(): void
    {
        // Which branch each row originally belonged to was overwritten by
        // up() and cannot be recovered, so down() intentionally leaves the
        // data shared rather than guessing an owner.
    }
};
