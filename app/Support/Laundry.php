<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What each vendor is still holding, per item.
 *
 * This is the one sum the whole laundry cycle turns on. The Issue screen shows
 * it as **Prev Qty** — "you already have 40 of my bedsheets" — and the
 * Received screen shows it as **Pending**, the most the clerk can tick off.
 * Both read it from here so the two screens can never quote different numbers.
 *
 *     outstanding = everything issued − everything settled
 *
 * "Settled" counts damaged and missing pieces as well as the ones that came
 * back: the hotel is not getting those either, so leaving them on the list
 * would keep a finished job open for ever. They are written off on the receipt
 * note, where the loss is visible.
 *
 * Amounts are money, quantities are pieces — this class only counts pieces.
 * What the wash costs is settled on the issue note itself.
 */
class Laundry
{
    /**
     * Pieces still with one vendor, keyed by hk_item_id.
     *
     * @return Collection<int, float>
     */
    public static function outstandingFor(int $branchId, ?int $vendorId): Collection
    {
        if (! $vendorId) {
            return collect();
        }

        $issued = self::issued($branchId, $vendorId);
        $back = self::settled($branchId, $vendorId);

        return $issued
            ->map(fn ($sent, $itemId) => round((float) $sent - (float) ($back[$itemId] ?? 0), 2))
            // A vendor cannot owe less than nothing. A negative would mean more
            // came back than went out, which is a counting mistake somewhere —
            // showing it as 0 stops that mistake becoming a credit.
            ->map(fn ($qty) => max(0, $qty))
            ->filter(fn ($qty) => $qty > 0);
    }

    /** Pieces out with every vendor together, keyed by hk_item_id. */
    public static function outstandingTotals(int $branchId): Collection
    {
        $issued = self::issued($branchId, null);
        $back = self::settled($branchId, null);

        return $issued
            ->map(fn ($sent, $itemId) => round(max(0, (float) $sent - (float) ($back[$itemId] ?? 0)), 2))
            ->filter(fn ($qty) => $qty > 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    | Written as select + get + mapWithKeys rather than a plucked aggregate,
    | because a raw SUM does not survive pluck's column handling on every
    | driver. Two small queries, one shape, no surprises.
    */

    /** Pieces sent out, keyed by hk_item_id. @return Collection<int, float> */
    private static function issued(int $branchId, ?int $vendorId): Collection
    {
        return DB::table('hk_issue_items as l')
            ->join('hk_issues as i', 'i.id', '=', 'l.hk_issue_id')
            ->where('i.branch_id', $branchId)
            ->when($vendorId, fn ($q, $id) => $q->where('i.vendor_id', $id))
            ->groupBy('l.hk_item_id')
            ->selectRaw('l.hk_item_id as item_id, SUM(l.std_qty + l.exp_qty + l.rewash_qty) as qty')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id => (float) $row->qty]);
    }

    /** Pieces accounted for, keyed by hk_item_id. @return Collection<int, float> */
    private static function settled(int $branchId, ?int $vendorId): Collection
    {
        return DB::table('hk_receipt_items as l')
            ->join('hk_receipts as r', 'r.id', '=', 'l.hk_receipt_id')
            ->where('r.branch_id', $branchId)
            ->when($vendorId, fn ($q, $id) => $q->where('r.vendor_id', $id))
            ->groupBy('l.hk_item_id')
            ->selectRaw('l.hk_item_id as item_id, SUM(l.received_qty + l.damaged_qty + l.missing_qty) as qty')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id => (float) $row->qty]);
    }

    /**
     * What one issue line is worth.
     *
     * A rewash is the vendor's own mistake being put right, so it goes out
     * free — it is counted, because those pieces are out of the hotel and have
     * to come back, but it is not charged for.
     */
    public static function lineAmount(float $stdQty, float $stdRate, float $expQty, float $expRate): float
    {
        return round(max(0, $stdQty) * max(0, $stdRate) + max(0, $expQty) * max(0, $expRate), 2);
    }
}
