<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The store: what the hotel bought, what it has, and what it cost.
 *
 * ── The ledger is the truth ───────────────────────────────────────────────
 *
 * `stock_ledger` holds one row per movement and is never updated or deleted.
 * A correction is another row; a cancelled document posts its reverse. The
 * quantity and average rate on `store_items` are a CACHE of running that
 * ledger, and `rebuild()` can produce them again from nothing at any time.
 * If the two ever disagree, the ledger is right.
 *
 * ── Valuation ────────────────────────────────────────────────────────────
 *
 * Moving weighted average. A kitchen buys the same onions at four prices in a
 * month, and the average is what survives that — and what an auditor expects.
 *
 *     new average = (held × old average + received × paid) ÷ (held + received)
 *
 * It moves only on a RECEIPT. Issuing stock cannot change what the stock still
 * on the shelf cost, and an implementation that recalculated on the way out
 * would quietly drift every time the kitchen drew anything.
 *
 * ── Negative stock is allowed, and shown ─────────────────────────────────
 *
 * A kitchen that used forty kilos before anybody entered the delivery note is
 * a hotel, not a bug. Refusing the issue would mean the books say the stock is
 * still there; allowing it and drawing the balance in red says what actually
 * happened. See `shortIssues()`.
 */
class Store
{
    /** Which way each kind of document moves stock. */
    public const DIRECTION = [
        'grn' => 'in',
        'opening' => 'in',
        'issue' => 'out',
        'wastage' => 'out',
        // An adjustment carries its own sign — see post().
        'adjustment' => 'signed',
        // A purchase order moves nothing. It is a promise.
        'po' => 'none',
        // Leaves the source outlet...
        'transfer_out' => 'out',
        // ...and lands on the destination outlet once it posts its own receipt.
        'transfer_in' => 'in',
    ];

    /** Where stock goes when it leaves the store. */
    public const DEPARTMENTS = [
        'kitchen' => 'Kitchen',
        'bar' => 'Bar',
        'housekeeping' => 'Housekeeping',
        'maintenance' => 'Maintenance',
        'front_office' => 'Front Office',
        'banquet' => 'Banquet',
        'other' => 'Other',
    ];

    public const KINDS = [
        'po' => 'Purchase Order',
        'grn' => 'Goods Receipt',
        'issue' => 'Issue',
        'wastage' => 'Wastage',
        'adjustment' => 'Stock Adjustment',
        'transfer_out' => 'Transfer Out',
        'transfer_in' => 'Transfer In',
    ];

    /*
    |--------------------------------------------------------------------------
    | Posting
    |--------------------------------------------------------------------------
    */

    /**
     * Put a document's lines through the ledger and move the stock.
     *
     * Everything happens in one transaction: a document that half-posted would
     * leave a store whose book balance nobody could explain. A purchase order
     * posts nothing — it is a promise, not a movement — and is simply marked
     * as ordered.
     *
     * @return int how many ledger rows were written
     *
     * @throws PostingRefused when the document is not in a state to be posted
     */
    public static function post(int $docId, ?int $userId = null): int
    {
        return DB::transaction(function () use ($docId, $userId) {
            $doc = DB::table('store_docs')->where('id', $docId)->lockForUpdate()->first();

            if (! $doc) {
                throw new PostingRefused('That document no longer exists.');
            }

            if ($doc->status !== 'draft') {
                throw new PostingRefused(
                    'This ' . (self::KINDS[$doc->kind] ?? 'document') . ' has already been posted.'
                );
            }

            $lines = DB::table('store_doc_items')->where('store_doc_id', $doc->id)->get();

            if ($lines->isEmpty()) {
                throw new PostingRefused('There is nothing on this document to post.');
            }

            $direction = self::DIRECTION[$doc->kind] ?? 'none';

            // A purchase order promises; it does not move anything.
            if ($direction === 'none') {
                DB::table('store_docs')->where('id', $doc->id)->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => $userId,
                    'updated_at' => now(),
                ]);

                return 0;
            }

            $written = 0;

            foreach ($lines as $line) {
                $qty = (float) $line->qty;

                if (abs($qty) < 0.0005) {
                    continue;
                }

                $way = $direction === 'signed'
                    ? ($qty >= 0 ? 'in' : 'out')
                    : $direction;

                self::move(
                    (int) $doc->branch_id,
                    (int) $line->store_item_id,
                    $doc->doc_date,
                    $way,
                    $doc->kind,
                    abs($qty),
                    (float) $line->rate,
                    (int) $doc->id,
                    $doc->doc_no,
                    $userId
                );

                $written++;
            }

            DB::table('store_docs')->where('id', $doc->id)->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
                'updated_at' => now(),
            ]);

            // A goods receipt against a purchase order closes off what it filled.
            if ($doc->kind === 'grn' && $doc->against_id) {
                self::settlePurchaseOrder((int) $doc->against_id);
            }

            // A transfer received at the destination closes off what it filled
            // at the source, the same way — see settleTransferOut() below.
            if ($doc->kind === 'transfer_in' && $doc->against_id) {
                self::settleTransferOut((int) $doc->against_id);
            }

            /*
             * Posting is done with the query builder rather than through the
             * model, so the model's own audit hook never sees it — and posting
             * is the moment stock actually moves, which is the one a manager
             * asks about later. It is logged here by hand.
             */
            Audit::note('posted', (self::KINDS[$doc->kind] ?? 'Document') . ' ' . $doc->doc_no
                . ' posted — ' . $written . ' ledger ' . ($written === 1 ? 'row' : 'rows')
                . ', ₹' . number_format((float) $doc->net_amount, 2), [
                    'area' => 'stock',
                    'branch_id' => (int) $doc->branch_id,
                    'subject_type' => \App\Models\Store\StoreDoc::class,
                    'subject_id' => (int) $doc->id,
                    'subject_label' => $doc->doc_no,
                    'user_id' => $userId,
                ]);

            return $written;
        });
    }

    /**
     * Undo a posted document by posting its opposite.
     *
     * Nothing is deleted. The original rows stay in the ledger and a matching
     * set goes in the other direction, so the history reads as what actually
     * happened: it was received, and then it was sent back.
     */
    public static function cancel(int $docId, ?int $userId = null): int
    {
        return DB::transaction(function () use ($docId, $userId) {
            $doc = DB::table('store_docs')->where('id', $docId)->lockForUpdate()->first();

            if (! $doc) {
                throw new PostingRefused('That document no longer exists.');
            }

            if ($doc->status === 'cancelled') {
                throw new PostingRefused('This document is already cancelled.');
            }

            $written = 0;

            if ($doc->status === 'posted' || $doc->status === 'partial') {
                $rows = DB::table('stock_ledger')
                    ->where('store_doc_id', $doc->id)
                    ->where('kind', '!=', 'reversal')
                    ->get();

                foreach ($rows as $row) {
                    self::move(
                        (int) $row->branch_id,
                        (int) $row->store_item_id,
                        today()->toDateString(),
                        $row->direction === 'in' ? 'out' : 'in',
                        'reversal',
                        (float) $row->qty,
                        (float) $row->rate,
                        (int) $doc->id,
                        'Cancelled ' . $doc->doc_no,
                        $userId
                    );

                    $written++;
                }
            }

            DB::table('store_docs')->where('id', $doc->id)->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            Audit::note('cancelled', (self::KINDS[$doc->kind] ?? 'Document') . ' ' . $doc->doc_no
                . ' cancelled — ' . $written . ' movement' . ($written === 1 ? '' : 's') . ' reversed', [
                    'area' => 'stock',
                    'branch_id' => (int) $doc->branch_id,
                    'subject_type' => \App\Models\Store\StoreDoc::class,
                    'subject_id' => (int) $doc->id,
                    'subject_label' => $doc->doc_no,
                    'user_id' => $userId,
                ]);

            return $written;
        });
    }

    /**
     * One movement: a ledger row, and the item's cache brought up to date.
     *
     * The item row is locked first. Two deliveries of the same item posted at
     * the same moment would otherwise each read the old balance and write the
     * same new one, and a sack of rice would vanish.
     */
    public static function move(
        int $branchId,
        int $itemId,
        string $date,
        string $direction,
        string $kind,
        float $qty,
        float $rate,
        ?int $docId = null,
        ?string $reference = null,
        ?int $userId = null
    ): int {
        $item = DB::table('store_items')->where('id', $itemId)->lockForUpdate()->first();

        if (! $item) {
            throw new PostingRefused('An item on this document no longer exists.');
        }

        $held = (float) $item->current_qty;
        $avg = (float) $item->avg_rate;

        if ($direction === 'in') {
            $newQty = $held + $qty;

            /*
             * The weighted average, and the two cases where it cannot be one.
             * Stock at or below zero has no meaningful average to blend with,
             * so a receipt onto an empty (or negative) shelf simply takes the
             * price that was paid.
             */
            $newAvg = $held > 0 && $newQty > 0
                ? round((($held * $avg) + ($qty * $rate)) / $newQty, 2)
                : round($rate, 2);

            $value = round($qty * $rate, 2);
        } else {
            $newQty = $held - $qty;
            // Issuing cannot change what the remaining stock cost.
            $newAvg = $avg;
            // It leaves at the average, not at whatever it was last bought for.
            $rate = $avg;
            $value = round($qty * $avg, 2);
        }

        $id = DB::table('stock_ledger')->insertGetId([
            'branch_id' => $branchId,
            'store_item_id' => $itemId,
            'entry_date' => $date,
            'direction' => $direction,
            'kind' => $kind,
            'store_doc_id' => $docId,
            'reference' => $reference,
            'qty' => round($qty, 3),
            'rate' => round($rate, 2),
            'value' => $value,
            'balance_qty' => round($newQty, 3),
            'balance_rate' => $newAvg,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_items')->where('id', $itemId)->update([
            'current_qty' => round($newQty, 3),
            'avg_rate' => $newAvg,
            'last_rate' => $direction === 'in' ? round($rate, 2) : (float) $item->last_rate,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * How much of a purchase order has actually turned up.
     *
     * Read back from the receipts rather than counted up as they are entered,
     * so a cancelled or corrected GRN cannot leave a PO thinking it is closed.
     */
    public static function settlePurchaseOrder(int $poId): void
    {
        $lines = DB::table('store_doc_items')->where('store_doc_id', $poId)->get();

        if ($lines->isEmpty()) {
            return;
        }

        $received = DB::table('store_doc_items as gi')
            ->join('store_docs as g', 'g.id', '=', 'gi.store_doc_id')
            ->where('g.against_id', $poId)
            ->where('g.kind', 'grn')
            ->whereIn('g.status', ['posted', 'partial'])
            ->groupBy('gi.store_item_id')
            ->selectRaw('gi.store_item_id, COALESCE(SUM(gi.qty), 0) as qty')
            ->pluck('qty', 'store_item_id');

        $complete = true;

        foreach ($lines as $line) {
            $got = (float) ($received[$line->store_item_id] ?? 0);

            DB::table('store_doc_items')->where('id', $line->id)
                ->update(['received_qty' => round($got, 3), 'updated_at' => now()]);

            if ($got + 0.0005 < (float) $line->qty) {
                $complete = false;
            }
        }

        DB::table('store_docs')->where('id', $poId)->update([
            'status' => $complete ? 'closed' : 'partial',
            'updated_at' => now(),
        ]);
    }

    /**
     * How much of a transfer out has actually been received at the other end.
     *
     * The transfer_out equivalent of settlePurchaseOrder() above — kept as its
     * own method rather than folded into that one, so a change to how a
     * purchase order settles can never accidentally reach a transfer, and the
     * other way round.
     */
    public static function settleTransferOut(int $transferOutId): void
    {
        $lines = DB::table('store_doc_items')->where('store_doc_id', $transferOutId)->get();

        if ($lines->isEmpty()) {
            return;
        }

        $received = DB::table('store_doc_items as ti')
            ->join('store_docs as t', 't.id', '=', 'ti.store_doc_id')
            ->where('t.against_id', $transferOutId)
            ->where('t.kind', 'transfer_in')
            ->whereIn('t.status', ['posted', 'partial'])
            ->groupBy('ti.store_item_id')
            ->selectRaw('ti.store_item_id, COALESCE(SUM(ti.qty), 0) as qty')
            ->pluck('qty', 'store_item_id');

        $complete = true;

        foreach ($lines as $line) {
            $got = (float) ($received[$line->store_item_id] ?? 0);

            DB::table('store_doc_items')->where('id', $line->id)
                ->update(['received_qty' => round($got, 3), 'updated_at' => now()]);

            if ($got + 0.0005 < (float) $line->qty) {
                $complete = false;
            }
        }

        DB::table('store_docs')->where('id', $transferOutId)->update([
            'status' => $complete ? 'closed' : 'partial',
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Rebuilding
    |--------------------------------------------------------------------------
    */

    /**
     * Replay an item's whole ledger and fix its running balances.
     *
     * The thing that makes the cache safe to have. Nothing in normal use calls
     * it; it exists so that when somebody asks "are these numbers right?", the
     * answer can be produced rather than argued about.
     *
     * @return array{qty: float, rate: float, rows: int}
     */
    public static function rebuild(int $itemId): array
    {
        return DB::transaction(function () use ($itemId) {
            $item = DB::table('store_items')->where('id', $itemId)->lockForUpdate()->first();

            if (! $item) {
                return ['qty' => 0.0, 'rate' => 0.0, 'rows' => 0];
            }

            $qty = (float) $item->opening_qty;
            $avg = (float) $item->opening_rate;
            $rows = 0;

            $ledger = DB::table('stock_ledger')
                ->where('store_item_id', $itemId)
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get();

            foreach ($ledger as $row) {
                $moved = (float) $row->qty;

                if ($row->direction === 'in') {
                    $next = $qty + $moved;
                    $avg = $qty > 0 && $next > 0
                        ? round((($qty * $avg) + ($moved * (float) $row->rate)) / $next, 2)
                        : round((float) $row->rate, 2);
                    $qty = $next;
                } else {
                    $qty -= $moved;
                }

                DB::table('stock_ledger')->where('id', $row->id)->update([
                    'balance_qty' => round($qty, 3),
                    'balance_rate' => $avg,
                ]);

                $rows++;
            }

            DB::table('store_items')->where('id', $itemId)->update([
                'current_qty' => round($qty, 3),
                'avg_rate' => $avg,
                'updated_at' => now(),
            ]);

            return ['qty' => round($qty, 3), 'rate' => $avg, 'rows' => $rows];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * What the store is worth, and what is wrong with it.
     *
     * @return array<string, mixed>
     */
    public static function summary(int $branchId): array
    {
        $items = DB::table('store_items')
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('status', 1)
            ->get(['id', 'current_qty', 'avg_rate', 'reorder_level']);

        return [
            'items' => $items->count(),
            'value' => round($items->sum(fn ($i) => (float) $i->current_qty * (float) $i->avg_rate), 2),
            // At or below the reorder level, and the level is actually set.
            'reorder' => $items->filter(fn ($i) => (float) $i->reorder_level > 0
                && (float) $i->current_qty <= (float) $i->reorder_level)->count(),
            'out' => $items->filter(fn ($i) => (float) $i->current_qty <= 0)->count(),
            'negative' => $items->filter(fn ($i) => (float) $i->current_qty < 0)->count(),
        ];
    }

    /**
     * Items at or below their reorder level, shortest first.
     *
     * "Shortest" is the gap as a share of the level, not in kilos: being two
     * kilos under on salt is not the same emergency as being two kilos under
     * on chicken.
     *
     * @return Collection<int, object>
     */
    public static function reorder(int $branchId, int $limit = 50): Collection
    {
        return collect(DB::table('store_items as i')
            ->leftJoin('store_categories as c', 'c.id', '=', 'i.store_category_id')
            ->where(fn ($q) => $q->whereNull('i.branch_id')->orWhere('i.branch_id', $branchId))
            ->where('i.status', 1)
            ->where('i.reorder_level', '>', 0)
            ->whereColumn('i.current_qty', '<=', 'i.reorder_level')
            ->orderByRaw('(i.current_qty / NULLIF(i.reorder_level, 0)) asc')
            ->limit($limit)
            ->get([
                'i.id', 'i.name', 'i.code', 'i.unit', 'i.current_qty', 'i.reorder_level',
                'i.avg_rate', 'i.last_rate', 'c.name as category',
            ]));
    }

    /**
     * Issues that took stock below zero — the paperwork that is late.
     *
     * Not an error list: it is the list of items where a delivery note has not
     * been entered yet, and it is the fastest way to find them.
     *
     * @return Collection<int, object>
     */
    public static function shortIssues(int $branchId): Collection
    {
        return collect(DB::table('store_items')
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('status', 1)
            ->where('current_qty', '<', 0)
            ->orderBy('current_qty')
            ->get(['id', 'name', 'code', 'unit', 'current_qty', 'avg_rate']));
    }

    /**
     * What THIS branch actually holds of one item — not the cache on
     * store_items, which nets every branch that shares the item together.
     *
     * Read live from the ledger rather than cached anywhere, because it only
     * exists to answer one question, asked rarely: "how much can this outlet
     * send away?" on the stock transfer screen, where a stale number would
     * mean somebody sending more than is really on that outlet's own shelf.
     */
    public static function balanceAtBranch(int $itemId, int $branchId): float
    {
        $in = (float) DB::table('stock_ledger')
            ->where('store_item_id', $itemId)
            ->where('branch_id', $branchId)
            ->where('direction', 'in')
            ->sum('qty');

        $out = (float) DB::table('stock_ledger')
            ->where('store_item_id', $itemId)
            ->where('branch_id', $branchId)
            ->where('direction', 'out')
            ->sum('qty');

        return round($in - $out, 3);
    }

    /** One item's movements, oldest first — the screen that answers "why 40kg?". */
    public static function ledger(int $itemId, ?string $from = null, ?string $to = null, int $limit = 200): Collection
    {
        return collect(DB::table('stock_ledger as l')
            ->leftJoin('store_docs as d', 'd.id', '=', 'l.store_doc_id')
            ->where('l.store_item_id', $itemId)
            ->when($from, fn ($q) => $q->whereDate('l.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('l.entry_date', '<=', $to))
            ->orderByDesc('l.entry_date')
            ->orderByDesc('l.id')
            ->limit($limit)
            ->get([
                'l.id', 'l.entry_date', 'l.direction', 'l.kind', 'l.qty', 'l.rate', 'l.value',
                'l.balance_qty', 'l.balance_rate', 'l.reference', 'd.doc_no', 'd.kind as doc_kind',
                'd.department',
            ]));
    }

    /**
     * What a department drew in a period, and what it cost.
     *
     * The figure a food cost percentage is built on, so it is issues at
     * average rate rather than purchases: what the kitchen consumed, not what
     * the store bought.
     *
     * @return Collection<int, object>
     */
    public static function consumption(int $branchId, string $from, string $to): Collection
    {
        return collect(DB::table('stock_ledger as l')
            ->join('store_docs as d', 'd.id', '=', 'l.store_doc_id')
            ->where('l.branch_id', $branchId)
            ->where('l.direction', 'out')
            ->whereIn('l.kind', ['issue', 'wastage'])
            ->whereBetween('l.entry_date', [$from, $to])
            ->groupBy('d.department', 'l.kind')
            ->selectRaw('d.department, l.kind, COUNT(*) as `lines`, COALESCE(SUM(`l`.`value`), 0) as `value`')
            ->get());
    }

    /**
     * What recipes say the kitchen should have used, against what actually
     * left the store as an issue or wastage — per item, side by side.
     *
     * Not an accusation: a gap is exactly as likely to be a recipe nobody
     * linked to a menu item, or stock issued in bulk ahead of when it is
     * actually cooked, as it is spillage or a mistake. It is the fastest way
     * to see where the gaps are, over a period long enough that "issued
     * Monday, cooked Tuesday" timing noise washes out.
     *
     * Expected is read from POS order lines that were actually sent to the
     * kitchen (`kot_no > 0`), on a live (non-cancelled) order, scaled by the
     * recipe's yield — a no-charge or room-service line still used the same
     * ingredients, so neither is excluded.
     *
     * @return Collection<int, object>
     */
    public static function consumptionVariance(int $branchId, string $from, string $to): Collection
    {
        $expected = DB::table('pos_order_items as oi')
            ->join('pos_orders as o', 'o.id', '=', 'oi.pos_order_id')
            ->join('recipes as r', 'r.pos_item_id', '=', 'oi.pos_menu_item_id')
            ->join('recipe_items as ri', 'ri.recipe_id', '=', 'r.id')
            ->where('o.branch_id', $branchId)
            ->where('o.status', '!=', 'cancelled')
            ->where('oi.kot_no', '>', 0)
            ->whereDate('oi.fired_at', '>=', $from)
            ->whereDate('oi.fired_at', '<=', $to)
            ->groupBy('ri.store_item_id')
            ->selectRaw('ri.store_item_id, SUM(ri.qty * oi.qty / r.yield_qty) as expected_qty')
            ->pluck('expected_qty', 'store_item_id');

        $actual = DB::table('stock_ledger as l')
            ->where('l.branch_id', $branchId)
            ->where('l.direction', 'out')
            ->whereIn('l.kind', ['issue', 'wastage'])
            ->whereBetween('l.entry_date', [$from, $to])
            ->groupBy('l.store_item_id')
            ->selectRaw('l.store_item_id, SUM(l.qty) as actual_qty')
            ->pluck('actual_qty', 'store_item_id');

        $itemIds = $expected->keys()->merge($actual->keys())->unique()->values();

        if ($itemIds->isEmpty()) {
            return collect();
        }

        $items = DB::table('store_items')->whereIn('id', $itemIds)->get(['id', 'name', 'unit'])->keyBy('id');

        return $itemIds
            ->map(function ($id) use ($expected, $actual, $items) {
                $exp = round((float) ($expected[$id] ?? 0), 3);
                $act = round((float) ($actual[$id] ?? 0), 3);

                return (object) [
                    'store_item_id' => $id,
                    'name' => $items[$id]->name ?? 'Deleted item',
                    'unit' => $items[$id]->unit ?? '',
                    'expected_qty' => $exp,
                    'actual_qty' => $act,
                    'variance_qty' => round($act - $exp, 3),
                ];
            })
            ->sortByDesc(fn ($row) => abs($row->variance_qty))
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Recipes
    |--------------------------------------------------------------------------
    */

    /**
     * What one portion of a dish costs to make, at today's average rates.
     *
     * Priced from the moving average rather than the last purchase, so a single
     * expensive delivery does not make the menu look unprofitable for a week.
     *
     * @return array{total: float, per_portion: float, lines: Collection, missing: int}
     */
    public static function recipeCost(int $recipeId): array
    {
        $recipe = DB::table('recipes')->where('id', $recipeId)->first();

        if (! $recipe) {
            return ['total' => 0.0, 'per_portion' => 0.0, 'lines' => collect(), 'missing' => 0];
        }

        $lines = collect(DB::table('recipe_items as ri')
            ->leftJoin('store_items as i', 'i.id', '=', 'ri.store_item_id')
            ->where('ri.recipe_id', $recipeId)
            ->get([
                'ri.id', 'ri.qty', 'ri.remark', 'ri.store_item_id',
                'i.name', 'i.unit', 'i.avg_rate', 'i.current_qty',
            ]))
            ->map(function ($line) {
                $line->cost = round((float) $line->qty * (float) ($line->avg_rate ?? 0), 2);

                return $line;
            });

        $total = round($lines->sum('cost'), 2);
        $yield = max(0.001, (float) $recipe->yield_qty);

        return [
            'total' => $total,
            'per_portion' => round($total / $yield, 2),
            'lines' => $lines,
            /*
             * Ingredients the store has never bought have no average rate, so
             * they cost nothing — which would make a dish look cheaper than it
             * is. Counted, and said on screen.
             */
            'missing' => $lines->filter(fn ($l) => (float) ($l->avg_rate ?? 0) <= 0)->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    */

    /**
     * The next document number for a branch and a kind.
     *
     * Format: PO-1-0001, GRN-1-0001, ISS-1-0001 — the same shape as every
     * other number in the system. Read inside the transaction that writes the
     * document, so two clerks cannot land on the same one.
     */
    public static function nextNo(int $branchId, string $kind): string
    {
        $prefix = match ($kind) {
            'po' => 'PO',
            'grn' => 'GRN',
            'issue' => 'ISS',
            'wastage' => 'WST',
            'adjustment' => 'ADJ',
            'transfer_out' => 'TRO',
            'transfer_in' => 'TRI',
            default => 'DOC',
        } . '-' . $branchId . '-';

        $last = DB::table('store_docs')
            ->where('branch_id', $branchId)
            ->where('kind', $kind)
            ->where('doc_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('doc_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Add a document's lines up. Tax is per line, because rates differ. */
    public static function totals(array $lines): array
    {
        $sub = 0.0;
        $tax = 0.0;

        foreach ($lines as $line) {
            $amount = round((float) ($line['qty'] ?? 0) * (float) ($line['rate'] ?? 0), 2);
            $lineTax = round($amount * (float) ($line['tax_percent'] ?? 0) / 100, 2);

            $sub += $amount;
            $tax += $lineTax;
        }

        return [
            'sub_total' => round($sub, 2),
            'tax_total' => round($tax, 2),
            'net_amount' => round($sub + $tax, 2),
        ];
    }
}
