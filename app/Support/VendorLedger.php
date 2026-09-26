<?php

namespace App\Support;

use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Ledger;
use App\Models\Master\Vendor;

/**
 * Keeps a vendor and its ledger the same thing in two tables, not just in
 * name.
 *
 * Masters → Vendor is what Purchase Orders, Goods Receipts and the laundry
 * issue note point at. Accounting → Vendor Payment pays against a Ledger
 * under Sundry Creditors. `Vendor::booted()` calls sync() whenever a vendor
 * is created or renamed, so a laundry added from the House Keeping issue
 * screen shows up on Vendor Payment without anyone visiting Accounting
 * first — which is exactly what the Vendor model's own doc comment always
 * said should happen.
 *
 * A hotel that renames or deletes "Sundry Creditors" gets a vendor with no
 * ledger rather than a crash — the same fail-soft choice
 * AccountGroup::namedSubtreeIds() makes for the dropdown itself.
 */
class VendorLedger
{
    public static function sync(Vendor $vendor): void
    {
        $group = AccountGroup::query()
            ->forBranch($vendor->branch_id)
            ->where('name', AccountGroup::CREDITORS)
            ->first();

        if (! $group) {
            return;
        }

        if ($vendor->ledger_id) {
            $ledger = Ledger::find($vendor->ledger_id);

            if ($ledger) {
                if ($ledger->name !== $vendor->name) {
                    $ledger->update(['name' => $vendor->name]);
                }

                return;
            }

            // ledger_id pointed at a row that is gone — fall through and relink.
        }

        // Same name, same branch, already under Sundry Creditors: treat it as
        // this vendor's ledger rather than creating a duplicate next to it.
        $ledger = Ledger::query()
            ->where('branch_id', $vendor->branch_id)
            ->where('account_group_id', $group->id)
            ->where('name', $vendor->name)
            ->first();

        $ledger ??= Ledger::create([
            'branch_id' => $vendor->branch_id,
            'account_group_id' => $group->id,
            'name' => $vendor->name,
            'opening_balance' => 0,
            'balance_type' => 'cr',
            'cash_type' => 'none',
            'gst_no' => $vendor->gst_no,
            'mobile' => $vendor->mobile,
            'email' => $vendor->email,
            'address' => $vendor->address,
            'is_system' => 0,
            'status' => 1,
        ]);

        $vendor->update(['ledger_id' => $ledger->id]);
    }
}
