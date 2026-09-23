<?php

namespace Database\Seeders;

use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Ledger;
use App\Models\Branch\Branch;
use Illuminate\Database\Seeder;

/**
 * A standard Indian chart of accounts, for a branch that has none.
 *
 * Nothing here is mandatory — a hotel can build its own from the Group and
 * Ledger screens — but an empty accounts module is one nobody can start using,
 * and the first thing every Indian accountant looks for is this exact list of
 * groups. Getting them named the way Tally names them means the hotel's own
 * accountant recognises the screen.
 *
 * Safe to run twice: everything is matched on branch + name, so re-seeding
 * after adding a branch fills in the new one and leaves the old ones alone.
 * Rows are marked `is_system` so the screens refuse to let their nature be
 * changed — moving "Room Revenue" from income to expense would change last
 * year's profit without a single voucher being touched.
 */
class AccountingSeeder extends Seeder
{
    /** name => nature */
    public const GROUPS = [
        'Capital Account' => 'liability',
        'Current Assets' => 'asset',
        'Current Liabilities' => 'liability',
        'Sundry Debtors' => 'asset',
        'Sundry Creditors' => 'liability',
        'Cash-in-Hand' => 'asset',
        'Bank Accounts' => 'asset',
        'Fixed Assets' => 'asset',
        'Duties & Taxes' => 'liability',
        'Direct Income' => 'income',
        'Indirect Income' => 'income',
        'Direct Expenses' => 'expense',
        'Indirect Expenses' => 'expense',
    ];

    /** Which group sits inside which. */
    public const NESTING = [
        'Sundry Debtors' => 'Current Assets',
        'Cash-in-Hand' => 'Current Assets',
        'Bank Accounts' => 'Current Assets',
        'Sundry Creditors' => 'Current Liabilities',
        'Duties & Taxes' => 'Current Liabilities',
    ];

    /** name => [group, cash_type] */
    public const LEDGERS = [
        'Cash' => ['Cash-in-Hand', 'cash'],
        'Bank' => ['Bank Accounts', 'bank'],
        'Room Revenue' => ['Direct Income', 'none'],
        'Food & Beverage Revenue' => ['Direct Income', 'none'],
        'Banquet Revenue' => ['Direct Income', 'none'],
        'Pool & Other Revenue' => ['Indirect Income', 'none'],
        'GST Payable' => ['Duties & Taxes', 'none'],
        'Discount Allowed' => ['Indirect Expenses', 'none'],
        'Salaries & Wages' => ['Indirect Expenses', 'none'],
        'Electricity & Water' => ['Indirect Expenses', 'none'],
        'Laundry Expenses' => ['Direct Expenses', 'none'],
        'Kitchen Purchases' => ['Direct Expenses', 'none'],
    ];

    public function run(): void
    {
        foreach (Branch::query()->pluck('id') as $branchId) {
            $this->seedBranch((int) $branchId);
        }
    }

    private function seedBranch(int $branchId): void
    {
        $groups = [];

        foreach (self::GROUPS as $name => $nature) {
            $groups[$name] = AccountGroup::firstOrCreate(
                ['branch_id' => $branchId, 'name' => $name],
                ['nature' => $nature, 'is_system' => 1, 'status' => 1]
            );
        }

        /*
         * Parents are set in a second pass because a group cannot point at one
         * that has not been created yet, and the list above is in the order an
         * accountant reads it rather than in dependency order.
         */
        foreach (self::NESTING as $child => $parent) {
            if (isset($groups[$child], $groups[$parent]) && ! $groups[$child]->parent_id) {
                $groups[$child]->update(['parent_id' => $groups[$parent]->id]);
            }
        }

        foreach (self::LEDGERS as $name => [$group, $cashType]) {
            if (! isset($groups[$group])) {
                continue;
            }

            Ledger::firstOrCreate(
                ['branch_id' => $branchId, 'name' => $name],
                [
                    'account_group_id' => $groups[$group]->id,
                    'cash_type' => $cashType,
                    'opening_balance' => 0,
                    // An asset or expense opens on the debit side, income and
                    // liabilities on the credit side — at zero it makes no
                    // difference to the arithmetic, but it makes the Ledger
                    // screen read correctly from the first day.
                    'balance_type' => in_array($groups[$group]->nature, ['asset', 'expense'], true) ? 'dr' : 'cr',
                    'is_system' => 1,
                    'status' => 1,
                ]
            );
        }
    }
}
