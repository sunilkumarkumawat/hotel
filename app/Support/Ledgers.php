<?php

namespace App\Support;

use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Ledger;
use App\Models\Accounting\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * All the balance arithmetic in the accounts module, in one file.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * THE SIGN CONVENTION — read this before changing anything below
 * ──────────────────────────────────────────────────────────────────────────
 * Every balance this class returns is a **signed rupee figure**:
 *
 *     positive  =  Dr        negative  =  Cr
 *
 * so a balance is `opening ± every posted entry` and nothing has to carry a
 * separate "which side?" flag beside the number.
 *
 * What the sign *means* depends on the group's nature, and this is the single
 * most confusing thing in the module:
 *
 *   asset, expense     Dr-positive. Cash you hold and money you spent are
 *                      positive here. A negative cash balance means the box is
 *                      overdrawn — a mistake worth seeing.
 *   liability, income  Cr-positive. What you owe and what you earned come back
 *                      NEGATIVE from balanceOf(), and the screens flip the sign
 *                      to show them: a vendor you owe ₹5,000 has a balance of
 *                      −5000, shown as "₹5,000.00 Cr".
 *
 * So: Trial Balance puts a positive balance in the Dr column and a negative one
 * in the Cr column, always, whatever the nature. Profit & Loss flips income
 * (−signed) and leaves expense as it is, because that is the only way the two
 * add up to a profit rather than to nothing.
 *
 * Cancelled vouchers are excluded everywhere, and only because every query in
 * here goes through Voucher::liveEntries(), which is where that rule lives.
 */
class Ledgers
{
    /** Natures whose "normal", positive balance sits on the debit side. */
    public const DEBIT_NATURES = ['asset', 'expense'];

    /*
    |--------------------------------------------------------------------------
    | One ledger
    |--------------------------------------------------------------------------
    */

    /**
     * What a ledger stands at — opening balance plus everything posted to it.
     *
     * `$upto` is inclusive of that whole day. A branch id is worth passing:
     * a shared (NULL branch) ledger can hold entries from two properties, and
     * adding both together would be wrong in a way nobody notices for a month.
     */
    public static function balanceOf(int $ledgerId, ?string $upto = null, ?int $branchId = null): float
    {
        $ledger = Ledger::find($ledgerId);

        if (! $ledger) {
            return 0.0;
        }

        $move = self::movements($branchId, [$ledgerId], null, $upto)->get($ledgerId);

        return round(
            $ledger->signedOpening() + (float) ($move['debit'] ?? 0) - (float) ($move['credit'] ?? 0),
            2
        );
    }

    /**
     * The same figure for a whole list of ledgers, in two queries rather than
     * two hundred. Keyed by ledger id, signed, and every asked-for ledger is
     * present even when it has never been posted to.
     *
     * @param  array<int, int>|null  $ledgerIds  null = every ledger of the branch
     * @return Collection<int, float>
     */
    public static function balancesFor(int $branchId, ?array $ledgerIds = null, ?string $upto = null): Collection
    {
        $ledgers = Ledger::query()
            ->forBranch($branchId)
            ->when($ledgerIds !== null, fn ($q) => $q->whereIn('id', $ledgerIds))
            ->get(['id', 'opening_balance', 'balance_type']);

        $moves = self::movements($branchId, $ledgers->pluck('id')->map(fn ($id) => (int) $id)->all(), null, $upto);

        return $ledgers->mapWithKeys(function (Ledger $ledger) use ($moves) {
            $move = $moves->get((int) $ledger->id);

            return [(int) $ledger->id => round(
                $ledger->signedOpening() + (float) ($move['debit'] ?? 0) - (float) ($move['credit'] ?? 0),
                2
            )];
        });
    }

    /**
     * A ledger's statement: what it stood at on the morning of `$from`, every
     * movement since, a running balance down the page, and where it closes.
     *
     * The opening figure is the ledger's own opening balance carried forward
     * through everything posted *before* the window — which is what makes a
     * statement for one month agree with a statement for the year.
     *
     * @return array{
     *     ledger: Ledger|null, from: string, to: string, opening: float,
     *     lines: Collection<int, object>, debit: float, credit: float, closing: float
     * }
     */
    public static function statement(int $ledgerId, string $from, string $to, ?int $branchId = null): array
    {
        $ledger = Ledger::with('group')->find($ledgerId);

        $empty = [
            'ledger' => $ledger,
            'from' => $from,
            'to' => $to,
            'opening' => 0.0,
            'lines' => collect(),
            'debit' => 0.0,
            'credit' => 0.0,
            'closing' => 0.0,
        ];

        if (! $ledger) {
            return $empty;
        }

        // Everything before the window, rolled into one figure.
        $before = self::movements($branchId, [$ledgerId], null, self::dayBefore($from))->get($ledgerId);

        $opening = round(
            $ledger->signedOpening() + (float) ($before['debit'] ?? 0) - (float) ($before['credit'] ?? 0),
            2
        );

        $lines = Voucher::liveEntries($branchId)
            ->where('ve.ledger_id', $ledgerId)
            ->where('v.voucher_date', '>=', $from)
            // A date column can come back as '2026-09-09 00:00:00', so "on or
            // before $to" is written as "before the day after $to".
            ->where('v.voucher_date', '<', self::dayAfter($to))
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->orderBy('ve.id')
            ->get([
                've.id',
                've.debit',
                've.credit',
                've.narration as line_narration',
                'v.id as voucher_id',
                'v.voucher_no',
                'v.voucher_type',
                'v.voucher_date',
                'v.reference_no',
                'v.narration',
            ]);

        $against = self::againstLedgers($lines->pluck('voucher_id')->all(), $ledgerId);

        $running = $opening;
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $line->debit = round((float) $line->debit, 2);
            $line->credit = round((float) $line->credit, 2);

            $debit += $line->debit;
            $credit += $line->credit;

            $running = round($running + $line->debit - $line->credit, 2);

            $line->running = $running;
            // "Particulars" on a printed ledger: the other side of the entry.
            $line->against = $against[(int) $line->voucher_id] ?? '—';
        }

        return [
            'ledger' => $ledger,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'lines' => $lines,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'closing' => $running,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    /**
     * Every ledger with a balance on one date, under its group.
     *
     * The two totals must be equal. They can only differ if somebody wrote to
     * the database by hand — nothing in this module can store a voucher that
     * does not balance — so the screen says the difference out loud rather than
     * rounding it away.
     *
     * @return array{
     *     upto: string, groups: array<int, array<string, mixed>>,
     *     debit: float, credit: float, difference: float, count: int
     * }
     */
    public static function trialBalance(int $branchId, string $upto): array
    {
        $ledgers = Ledger::query()
            ->forBranch($branchId)
            ->with('group')
            ->orderBy('name')
            ->get();

        $balances = self::balancesFor($branchId, $ledgers->pluck('id')->map(fn ($id) => (int) $id)->all(), $upto);

        $groups = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $count = 0;

        foreach ($ledgers as $ledger) {
            $balance = (float) ($balances[(int) $ledger->id] ?? 0);

            // A ledger that stands at nothing is noise on a trial balance.
            if (abs($balance) < 0.005) {
                continue;
            }

            $key = $ledger->account_group_id ? (int) $ledger->account_group_id : 0;

            $groups[$key] ??= [
                'name' => $ledger->group?->name ?? 'Ungrouped',
                'nature' => $ledger->group?->nature ?? 'asset',
                'rows' => [],
                'debit' => 0.0,
                'credit' => 0.0,
            ];

            $debit = $balance > 0 ? round($balance, 2) : 0.0;
            $credit = $balance < 0 ? round(-$balance, 2) : 0.0;

            $groups[$key]['rows'][] = [
                'ledger' => $ledger,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $groups[$key]['debit'] = round($groups[$key]['debit'] + $debit, 2);
            $groups[$key]['credit'] = round($groups[$key]['credit'] + $credit, 2);

            $totalDebit = round($totalDebit + $debit, 2);
            $totalCredit = round($totalCredit + $credit, 2);
            $count++;
        }

        // Groups in a familiar order — assets, liabilities, income, expenses.
        $order = array_flip(array_keys(AccountGroup::NATURES));

        uasort($groups, fn ($a, $b) => [$order[$a['nature']] ?? 9, $a['name']] <=> [$order[$b['nature']] ?? 9, $b['name']]);

        return [
            'upto' => $upto,
            'groups' => array_values($groups),
            'debit' => $totalDebit,
            'credit' => $totalCredit,
            'difference' => round($totalDebit - $totalCredit, 2),
            'count' => $count,
        ];
    }

    /**
     * Income against expenses over a window, and what is left.
     *
     * Only the groups whose nature is `income` or `expense` are read, and only
     * what was posted **inside the window** — a ledger's opening balance belongs
     * to an earlier year and would otherwise turn up as this month's profit.
     * The screen prints the group names it read for exactly that reason: a
     * profit figure nobody can trace is a profit figure nobody believes.
     *
     * @return array{
     *     from: string, to: string,
     *     income: array{groups: array<int, array<string, mixed>>, total: float},
     *     expense: array{groups: array<int, array<string, mixed>>, total: float},
     *     net: float, read: array{income: array<int, string>, expense: array<int, string>}
     * }
     */
    public static function profitAndLoss(int $branchId, string $from, string $to): array
    {
        $groups = AccountGroup::query()
            ->forBranch($branchId)
            ->ofNature(['income', 'expense'])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $ledgers = Ledger::query()
            ->forBranch($branchId)
            ->whereIn('account_group_id', $groups->keys())
            ->orderBy('name')
            ->get();

        $moves = self::movements(
            $branchId,
            $ledgers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $from,
            $to
        );

        $side = ['income' => [], 'expense' => []];
        $totals = ['income' => 0.0, 'expense' => 0.0];

        foreach ($ledgers as $ledger) {
            $group = $groups->get($ledger->account_group_id);

            if (! $group) {
                continue;
            }

            $move = $moves->get((int) $ledger->id);
            $signed = round((float) ($move['debit'] ?? 0) - (float) ($move['credit'] ?? 0), 2);

            // Income is Cr-positive, so its sign is flipped to read as earnings;
            // expense is Dr-positive and is already the right way round.
            $amount = $group->nature === 'income' ? -$signed : $signed;

            if (abs($amount) < 0.005) {
                continue;
            }

            $nature = $group->nature;
            $key = (int) $group->id;

            $side[$nature][$key] ??= ['name' => $group->name, 'rows' => [], 'total' => 0.0];

            $side[$nature][$key]['rows'][] = ['ledger' => $ledger, 'amount' => $amount];
            $side[$nature][$key]['total'] = round($side[$nature][$key]['total'] + $amount, 2);

            $totals[$nature] = round($totals[$nature] + $amount, 2);
        }

        return [
            'from' => $from,
            'to' => $to,
            'income' => ['groups' => array_values($side['income']), 'total' => $totals['income']],
            'expense' => ['groups' => array_values($side['expense']), 'total' => $totals['expense']],
            'net' => round($totals['income'] - $totals['expense'], 2),
            'read' => [
                'income' => $groups->where('nature', 'income')->pluck('name')->values()->all(),
                'expense' => $groups->where('nature', 'expense')->pluck('name')->values()->all(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lists the screens need
    |--------------------------------------------------------------------------
    */

    /**
     * The cash boxes and bank accounts of a branch.
     *
     * `$type` is 'cash', 'bank', or null for both.
     *
     * @return Collection<int, Ledger>
     */
    public static function cashBankLedgers(int $branchId, ?string $type = null): Collection
    {
        return Ledger::query()
            ->forBranch($branchId)
            ->active()
            ->when($type, fn ($q, $t) => $q->ofCashType($t), fn ($q) => $q->cashOrBank())
            ->orderBy('name')
            ->get();
    }

    /**
     * Ledgers a voucher may be posted against, as id => name, for a dropdown.
     *
     * @return array<int, string>
     */
    public static function options(int $branchId, ?array $onlyIds = null): array
    {
        return Ledger::query()
            ->forBranch($branchId)
            ->active()
            ->when($onlyIds !== null, fn ($q) => $q->whereIn('id', $onlyIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Ledger $l) => [(int) $l->id => $l->display_name])
            ->all();
    }

    /**
     * Ledgers that belong to one or more account groups, as id => name.
     *
     * `options()` narrows by a *ledger's own id* — no help to a screen that
     * only knows the group, such as Vendor Payment asking for "everything
     * under Sundry Creditors". This is that other lookup: by
     * `account_group_id`, not `id`. Easy to reach for the wrong one, since
     * both take a list of ints — which is exactly what put the wrong ledger
     * on the Vendor Payment dropdown before this method existed.
     *
     * @param  array<int, int>  $groupIds
     * @return array<int, string>
     */
    public static function optionsInGroups(int $branchId, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Ledger::query()
            ->forBranch($branchId)
            ->active()
            ->whereIn('account_group_id', $groupIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Ledger $l) => [(int) $l->id => $l->display_name])
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Dates
    |--------------------------------------------------------------------------
    | A date column can come back as a datetime, so nothing here ever writes
    | `<= $today`. The day after is the boundary.
    */

    /** A date the user typed, or the fallback if it was nonsense. */
    public static function date(?string $value, string $fallback): string
    {
        return rescue(
            fn () => CarbonImmutable::parse(($value ?: '') ?: $fallback)->toDateString(),
            $fallback,
            false
        );
    }

    public static function dayAfter(string $date): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($date)->addDay()->toDateString(),
            $date,
            false
        );
    }

    public static function dayBefore(string $date): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($date)->subDay()->toDateString(),
            $date,
            false
        );
    }

    /** 'dr', 'cr', or '' for a balance that is exactly nothing. */
    public static function side(float $signed): string
    {
        if (abs($signed) < 0.005) {
            return '';
        }

        return $signed > 0 ? 'dr' : 'cr';
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Posted debits and credits per ledger, over an optional window.
     *
     * Written as selectRaw + get + mapWithKeys rather than a plucked aggregate,
     * because a raw SUM does not survive pluck's column handling on every
     * driver.
     *
     * @param  array<int, int>|null  $ledgerIds
     * @return Collection<int, array{debit: float, credit: float}>
     */
    private static function movements(?int $branchId, ?array $ledgerIds, ?string $from, ?string $upto): Collection
    {
        if ($ledgerIds !== null && $ledgerIds === []) {
            return collect();
        }

        return Voucher::liveEntries($branchId)
            ->when($ledgerIds !== null, fn ($q) => $q->whereIn('ve.ledger_id', $ledgerIds))
            ->when($from, fn ($q, $d) => $q->where('v.voucher_date', '>=', $d))
            ->when($upto, fn ($q, $d) => $q->where('v.voucher_date', '<', self::dayAfter($d)))
            ->groupBy('ve.ledger_id')
            ->selectRaw('ve.ledger_id as ledger_id, SUM(ve.debit) as debit, SUM(ve.credit) as credit')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->ledger_id => [
                'debit' => round((float) $row->debit, 2),
                'credit' => round((float) $row->credit, 2),
            ]]);
    }

    /**
     * The other ledgers on each voucher — the "Particulars" column.
     *
     * @param  array<int, int|string>  $voucherIds
     * @return array<int, string>
     */
    private static function againstLedgers(array $voucherIds, int $exceptLedgerId): array
    {
        $ids = array_values(array_unique(array_map('intval', $voucherIds)));

        if ($ids === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('voucher_entries as ve')
            ->join('ledgers as l', 'l.id', '=', 've.ledger_id')
            ->whereIn('ve.voucher_id', $ids)
            ->where('ve.ledger_id', '!=', $exceptLedgerId)
            ->orderBy('ve.id')
            ->get(['ve.voucher_id', 'l.name'])
            ->groupBy('voucher_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->take(3)->implode(', ')
                . ($rows->pluck('name')->unique()->count() > 3 ? ' …' : ''))
            ->mapWithKeys(fn ($names, $voucherId) => [(int) $voucherId => $names])
            ->all();
    }
}
