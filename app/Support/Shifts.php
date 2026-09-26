<?php

namespace App\Support;

use App\Models\Audit\CashierShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The drawer, and whether it is right.
 *
 * ── A shift holds no money of its own ────────────────────────────────────
 *
 * The figures on a shift report are a question asked of `settlements`,
 * `advance_deposits`, `pos_payments` and the two petty cash tables — the rows
 * the hotel already writes when it takes or pays out money. Nothing is copied.
 * That means a shift can never disagree with the day book, a hotel that turns
 * this screen on today can still close yesterday, and a payment entered late
 * lands in the shift it was entered in rather than nowhere at all.
 *
 * A payment belongs to a shift when the same user took it while that shift
 * was open. Not the date on the receipt — the moment it was entered, which is
 * when the money was actually in somebody's hand.
 *
 * ── Except the two things nothing else knows ─────────────────────────────
 *
 * What the cashier COUNTED, which exists in no other table, and a frozen copy
 * of the expected figures taken at the moment of closing. A bill corrected
 * next week must not quietly rewrite a variance somebody has already signed
 * for, so the close keeps its own photograph.
 *
 * ── Only cash can be short ───────────────────────────────────────────────
 *
 * Card and UPI are counted too, because a machine total that does not match
 * is worth knowing about tonight rather than at the month end. But the
 * headline variance is cash, because cash is the only one a person can walk
 * out of the building with.
 */
class Shifts
{
    /** How far out the cash may be before the close is called a problem. */
    public const TOLERANCE = 1.00;

    /*
    |--------------------------------------------------------------------------
    | Opening and closing
    |--------------------------------------------------------------------------
    */

    /** This user's open drawer, if they have one. */
    public static function current(int $branchId, int $userId): ?CashierShift
    {
        return CashierShift::query()
            ->forBranch($branchId)
            ->where('user_id', $userId)
            ->open()
            ->orderByDesc('opened_at')
            ->first();
    }

    /**
     * Open a drawer.
     *
     * One at a time per person: two open shifts would each claim the same
     * payments, and both reports would be right about half the money.
     */
    public static function open(int $branchId, int $userId, float $float = 0, ?string $name = null, ?string $remark = null): CashierShift
    {
        return DB::transaction(function () use ($branchId, $userId, $float, $name, $remark) {
            if (self::current($branchId, $userId)) {
                throw new ShiftRefused('You already have a shift open. Close that one first.');
            }

            $shift = CashierShift::create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'shift_no' => self::nextNo($branchId),
                'name' => $name ?: self::partOfDay(),
                'opened_at' => now(),
                'opening_float' => round($float, 2),
                'status' => 'open',
                'remark' => $remark,
            ]);

            Audit::note('shift_opened', 'Shift ' . $shift->shift_no . ' opened with '
                . '₹' . number_format($float, 2) . ' float', [
                    'area' => 'money',
                    'branch_id' => $branchId,
                    'subject_type' => CashierShift::class,
                    'subject_id' => $shift->id,
                    'subject_label' => $shift->shift_no,
                ]);

            return $shift;
        });
    }

    /**
     * Count the drawer and close.
     *
     * `$counted` is keyed by pay mode id. A mode the cashier left blank counts
     * as zero rather than as "not checked": a close where half the boxes are
     * empty is not a close, and the screen makes that plain before it is sent.
     */
    public static function close(CashierShift $shift, array $counted, ?string $note = null, ?int $byUserId = null): CashierShift
    {
        return DB::transaction(function () use ($shift, $counted, $note, $byUserId) {
            $fresh = CashierShift::query()->lockForUpdate()->find($shift->id);

            if (! $fresh) {
                throw new ShiftRefused('That shift no longer exists.');
            }

            if (! $fresh->isOpen()) {
                throw new ShiftRefused('This shift was already closed at '
                    . $fresh->closed_at?->format('d M Y, h:i A') . '.');
            }

            /*
             * One instant, used for both. If the figures were taken at one
             * moment and the shift stamped closed at the next, a payment
             * entered in between would sit inside the window but outside the
             * frozen report — and the two would never add up again.
             */
            $at = now();
            $figures = self::figures($fresh, $at->toDateTimeString());

            $declared = [];
            $cashCounted = 0.0;

            foreach ($figures['modes'] as $mode) {
                $amount = round((float) ($counted[$mode['id']] ?? 0), 2);
                $declared[(string) $mode['id']] = $amount;

                if ($mode['type'] === 'cash') {
                    $cashCounted += $amount;
                }
            }

            $cashExpected = round((float) $figures['cash']['expected'], 2);
            $cashCounted = round($cashCounted, 2);

            $fresh->update([
                'closed_at' => $at,
                'declared' => $declared,
                'expected' => $figures,
                'cash_expected' => $cashExpected,
                'cash_counted' => $cashCounted,
                'variance' => round($cashCounted - $cashExpected, 2),
                'status' => 'closed',
                'close_note' => $note,
                'closed_by' => $byUserId,
            ]);

            self::announce($fresh->refresh());

            return $fresh;
        });
    }

    /**
     * Tell whoever needs to know.
     *
     * Wrapped, and after the transaction has done its work: a notification
     * that fails must not undo a close the cashier has already been told
     * about on screen.
     */
    public static function announce(CashierShift $shift): void
    {
        rescue(function () use ($shift) {
            $short = abs((float) $shift->variance) > self::TOLERANCE;

            Notify::event('shift.closed')
                ->title('Shift ' . $shift->shift_no . ' closed — ' . $shift->cashier)
                ->body(
                    '₹' . number_format((float) $shift->cash_counted, 2) . ' counted against '
                    . '₹' . number_format((float) $shift->cash_expected, 2) . ' expected'
                    . ($short
                        ? ' — ' . ($shift->isShort() ? 'short ' : 'over ')
                            . '₹' . number_format(abs((float) $shift->variance), 2)
                        : ' — it balances')
                )
                ->url(route('shift.reports.show', $shift->id))
                ->send();

            Audit::note('shift_closed', 'Shift ' . $shift->shift_no . ' closed — '
                . ($short
                    ? ($shift->isShort() ? 'short ' : 'over ') . '₹' . number_format(abs((float) $shift->variance), 2)
                    : 'balanced'), [
                        'area' => 'money',
                        'branch_id' => (int) $shift->branch_id,
                        'subject_type' => CashierShift::class,
                        'subject_id' => $shift->id,
                        'subject_label' => $shift->shift_no,
                    ]);
        }, null, false);
    }

    /*
    |--------------------------------------------------------------------------
    | The figures
    |--------------------------------------------------------------------------
    */

    /**
     * Everything this shift has taken, as it stands right now.
     *
     * A closed shift shows what was frozen at the close instead — see
     * frozenOrLive(). This is the live one, and it is what the cashier is
     * looking at while they count.
     */
    public static function figures(CashierShift $shift, ?string $until = null): array
    {
        $branchId = (int) $shift->branch_id;
        $userId = (int) $shift->user_id;
        $from = $shift->opened_at->toDateTimeString();
        $to = $until ?: ($shift->closed_at ?: now())->toDateTimeString();

        $modes = self::payModes($branchId);
        $lines = self::linesFor($branchId, $userId, $from, $to);

        $byMode = [];

        foreach ($modes as $id => $mode) {
            $byMode[$id] = [
                'id' => $id,
                'name' => $mode['name'],
                'type' => $mode['type'],
                'in' => 0.0,
                'out' => 0.0,
                'net' => 0.0,
                'count' => 0,
            ];
        }

        $sources = [
            'rooms' => 0.0, 'deposits' => 0.0, 'refunds' => 0.0,
            'pos' => 0.0, 'petty_in' => 0.0, 'petty_out' => 0.0,
        ];

        foreach ($lines as $line) {
            $id = (int) ($line['pay_mode_id'] ?: 0);

            // A payment against a pay mode somebody has since deleted still
            // happened, and its money is still in the drawer.
            if (! isset($byMode[$id])) {
                $byMode[$id] = [
                    'id' => $id,
                    'name' => $id === 0 ? 'Not stated' : 'Pay mode #' . $id,
                    'type' => 'cash',
                    'in' => 0.0, 'out' => 0.0, 'net' => 0.0, 'count' => 0,
                ];
            }

            $amount = round((float) $line['amount'], 2);

            if ($line['direction'] === 'in') {
                $byMode[$id]['in'] += $amount;
                $byMode[$id]['net'] += $amount;
            } else {
                $byMode[$id]['out'] += $amount;
                $byMode[$id]['net'] -= $amount;
            }

            $byMode[$id]['count']++;

            $sources[$line['bucket']] = round(($sources[$line['bucket']] ?? 0) + $amount, 2);
        }

        $modeRows = array_values(array_map(
            fn (array $row) => array_merge($row, [
                'in' => round($row['in'], 2),
                'out' => round($row['out'], 2),
                'net' => round($row['net'], 2),
            ]),
            $byMode
        ));

        $cashIn = 0.0;
        $cashOut = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($modeRows as $row) {
            $totalIn += $row['in'];
            $totalOut += $row['out'];

            if ($row['type'] === 'cash') {
                $cashIn += $row['in'];
                $cashOut += $row['out'];
            }
        }

        $float = round((float) $shift->opening_float, 2);

        return [
            'window' => ['from' => $from, 'to' => $to],
            'taken_at' => now()->toDateTimeString(),
            'modes' => $modeRows,
            'sources' => array_map(fn ($v) => round((float) $v, 2), $sources),
            'totals' => [
                'in' => round($totalIn, 2),
                'out' => round($totalOut, 2),
                'net' => round($totalIn - $totalOut, 2),
                'count' => count($lines),
            ],
            'cash' => [
                'float' => $float,
                'in' => round($cashIn, 2),
                'out' => round($cashOut, 2),
                // What should be in the drawer: the float, plus what came in,
                // less what went back out of it.
                'expected' => round($float + $cashIn - $cashOut, 2),
            ],
        ];
    }

    /**
     * What a screen should show for this shift.
     *
     * Closed shifts show the frozen figures. That is the whole point of
     * freezing them, and a report that recalculates every time it is opened
     * is one that eventually contradicts the signature at the bottom of it.
     */
    public static function frozenOrLive(CashierShift $shift): array
    {
        if (! $shift->isOpen() && is_array($shift->expected) && $shift->expected !== []) {
            return $shift->expected;
        }

        return self::figures($shift);
    }

    /**
     * Every payment this person took in this window, oldest first.
     *
     * Five tables, one shape. `created_at` is the moment it was entered,
     * which is the only column that says which shift had the money.
     *
     * @return array<int, array{at: string, bucket: string, source: string, particulars: string, reference: ?string, pay_mode_id: ?int, amount: float, direction: string}>
     */
    public static function linesFor(int $branchId, int $userId, string $from, string $to): array
    {
        $rows = [];

        $settlements = DB::table('settlements as s')
            ->leftJoin('bills as b', 'b.id', '=', 's.bill_id')
            ->leftJoin('check_ins as ci', 'ci.id', '=', 's.check_in_id')
            ->where('s.branch_id', $branchId)
            ->where('s.created_by', $userId)
            ->whereBetween('s.created_at', [$from, $to])
            ->orderBy('s.created_at')
            ->get([
                's.created_at', 's.pay_mode_id', 's.amount', 's.reference_no',
                'b.bill_no', 'ci.guest_name', 'ci.folio_no',
            ]);

        foreach ($settlements as $row) {
            $rows[] = self::line($row->created_at, 'rooms', 'Room bill',
                trim(($row->bill_no ?: $row->folio_no ?: 'Payment') . ' · ' . ($row->guest_name ?: 'Guest')),
                $row->reference_no, $row->pay_mode_id, (float) $row->amount, 'in');
        }

        $deposits = DB::table('advance_deposits as d')
        
            ->leftJoin('reservations as r', 'r.id', '=', 'd.reservation_id')
            ->where('d.branch_id', $branchId)
            ->where('d.created_by', $userId)
            ->whereBetween('d.created_at', [$from, $to])
            ->orderBy('d.created_at')
            ->get(['d.created_at', 'd.pay_mode_id', 'd.amount', 'd.type', 'd.reference_no', 'r.first_name']);
            
        foreach ($deposits as $row) {
            $refund = $row->type === 'refund';

            $rows[] = self::line($row->created_at, $refund ? 'refunds' : 'deposits',
                $refund ? 'Deposit refund' : 'Advance deposit',
                trim(($row->booking_no ?: 'Booking') . ' · ' . ($row->first_name ?: 'Guest')),
                $row->reference_no, $row->pay_mode_id, (float) $row->amount, $refund ? 'out' : 'in');
        }

        $pos = DB::table('pos_payments as p')
            ->leftJoin('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->where('p.branch_id', $branchId)
            ->where('p.created_by', $userId)
            ->whereBetween('p.created_at', [$from, $to])
            ->orderBy('p.created_at')
            ->get(['p.created_at', 'p.pay_mode_id', 'p.amount', 'p.reference_no', 'i.invoice_no']);

        foreach ($pos as $row) {
            $rows[] = self::line($row->created_at, 'pos', 'Restaurant',
                $row->invoice_no ?: 'POS bill', $row->reference_no, $row->pay_mode_id, (float) $row->amount, 'in');
        }

        $receipts = DB::table('petty_cash_receipts')
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['created_at', 'pay_mode_id', 'amount', 'voucher_no', 'received_from']);

        foreach ($receipts as $row) {
            $rows[] = self::line($row->created_at, 'petty_in', 'Cash receipt',
                trim($row->voucher_no . ' · ' . ($row->received_from ?: 'Received')),
                null, $row->pay_mode_id, (float) $row->amount, 'in');
        }

        // A rejected voucher never left the drawer; a pending one already has,
        // because the desk pays first and gets it signed afterwards.
        $payments = DB::table('petty_cash_payments')
            ->where('branch_id', $branchId)
            ->where('created_by', $userId)
            ->where('approval_status', '!=', 'rejected')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['created_at', 'pay_mode_id', 'amount', 'voucher_no', 'paid_to']);

        foreach ($payments as $row) {
            $rows[] = self::line($row->created_at, 'petty_out', 'Cash paid out',
                trim($row->voucher_no . ' · ' . ($row->paid_to ?: 'Paid')),
                null, $row->pay_mode_id, (float) $row->amount, 'out');
        }

        usort($rows, fn ($a, $b) => strcmp($a['at'], $b['at']));

        return $rows;
    }

    private static function line(
        mixed $at,
        string $bucket,
        string $source,
        string $particulars,
        ?string $reference,
        mixed $payModeId,
        float $amount,
        string $direction
    ): array {
        return [
            'at' => (string) $at,
            'bucket' => $bucket,
            'source' => $source,
            'particulars' => $particulars,
            'reference' => $reference ?: null,
            'pay_mode_id' => $payModeId ? (int) $payModeId : null,
            'amount' => round($amount, 2),
            'direction' => $direction,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | What a manager looks at
    |--------------------------------------------------------------------------
    */

    /** Every shift in a period, newest first. */
    public static function between(int $branchId, string $from, string $to): Collection
    {
        return CashierShift::query()
            ->forBranch($branchId)
            ->with('user')
            ->whereBetween('opened_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('opened_at')
            ->get();
    }

    /** The headline figures over a set of shifts. */
    public static function summary(Collection $shifts): array
    {
        $closed = $shifts->where('status', 'closed');

        return [
            'shifts' => $shifts->count(),
            'open' => $shifts->where('status', 'open')->count(),
            'counted' => round((float) $closed->sum(fn ($s) => (float) $s->cash_counted), 2),
            'expected' => round((float) $closed->sum(fn ($s) => (float) $s->cash_expected), 2),
            'short' => round((float) $closed->filter(fn ($s) => $s->isShort())->sum(fn ($s) => abs((float) $s->variance)), 2),
            'over' => round((float) $closed->filter(fn ($s) => $s->isOver())->sum(fn ($s) => (float) $s->variance), 2),
            'off' => $closed->filter(fn ($s) => abs((float) $s->variance) > self::TOLERANCE)->count(),
        ];
    }

    /**
     * Money taken while nobody had a drawer open.
     *
     * The control report that makes the rest of this worth having: a hotel
     * where half the payments belong to no shift has a screen nobody uses,
     * and the shift reports that do exist are only a part of the story.
     *
     * @return array<int, array{user_id: int, user: string, count: int, amount: float}>
     */
    public static function unattached(int $branchId, string $from, string $to): array
    {
        $shifts = CashierShift::query()
            ->forBranch($branchId)
            ->where('opened_at', '<=', $to . ' 23:59:59')
            ->where(function ($q) use ($from) {
                $q->whereNull('closed_at')->orWhere('closed_at', '>=', $from . ' 00:00:00');
            })
            ->get(['user_id', 'opened_at', 'closed_at']);

        $users = DB::table('users')->pluck('name', 'user_id')->all();
        $out = [];

        foreach (self::allTakers($branchId, $from, $to) as $row) {
            $userId = (int) $row->user_id;
            $at = (string) $row->created_at;

            $covered = $shifts->contains(function ($shift) use ($userId, $at) {
                if ((int) $shift->user_id !== $userId) {
                    return false;
                }

                $opened = (string) $shift->opened_at;
                $closed = $shift->closed_at ? (string) $shift->closed_at : '9999-12-31 23:59:59';

                return $at >= $opened && $at <= $closed;
            });

            if ($covered) {
                continue;
            }

            $out[$userId] ??= [
                'user_id' => $userId,
                'user' => $users[$userId] ?? ('User #' . $userId),
                'count' => 0,
                'amount' => 0.0,
            ];

            $out[$userId]['count']++;
            $out[$userId]['amount'] = round($out[$userId]['amount'] + (float) $row->amount, 2);
        }

        usort($out, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $out;
    }

    /** Every money row in a period, whoever took it — the raw list behind unattached(). */
    private static function allTakers(int $branchId, string $from, string $to): Collection
    {
        $window = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $rows = collect();

        foreach ([
            'settlements', 'advance_deposits', 'pos_payments',
            'petty_cash_receipts', 'petty_cash_payments',
        ] as $table) {
            $rows = $rows->merge(
                DB::table($table)
                    ->where('branch_id', $branchId)
                    ->whereNotNull('created_by')
                    ->whereBetween('created_at', $window)
                    ->get(['created_by as user_id', 'created_at', 'amount'])
            );
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Small things
    |--------------------------------------------------------------------------
    */

    /** Pay modes as [id => ['name' => …, 'type' => …]], active ones first. */
    public static function payModes(int $branchId): array
    {
        $rows = DB::table('pay_mode')
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('status', 1)
            ->orderByRaw("FIELD(type, 'cash', 'card', 'upi', 'bank', 'cheque', 'other')")
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->id] = ['name' => $row->name, 'type' => $row->type];
        }

        return $out;
    }

    /** SHF-1-0001, in the same shape as every other number in the system. */
    public static function nextNo(int $branchId): string
    {
        $prefix = 'SHF-' . $branchId . '-';

        $last = DB::table('cashier_shifts')
            ->where('branch_id', $branchId)
            ->where('shift_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('shift_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** What to call a shift nobody named. */
    public static function partOfDay(?string $at = null): string
    {
        $hour = (int) CarbonImmutable::parse($at ?: now())->format('G');

        return match (true) {
            $hour < 6 => 'Night',
            $hour < 14 => 'Morning',
            $hour < 21 => 'Evening',
            default => 'Night',
        };
    }
}
