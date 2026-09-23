<?php

namespace App\Support;

use App\Models\Accounting\Ledger;
use App\Models\Accounting\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The only way a voucher gets into the books.
 *
 * Payment, Receipt, Contra, Journal, Vendor Payment, Customer Receipt and
 * anything the rest of the system posts for itself all come through post().
 * There is deliberately no other door, because four rules have to hold for
 * every single voucher and a screen that wrote its own INSERT would sooner or
 * later forget one of them:
 *
 *   1. **It balances.** Total debit equals total credit to the paisa, or
 *      nothing is stored and the clerk is told what the difference is.
 *   2. **Its ledgers are this branch's.** A dropdown is not a permission check;
 *      an id that arrives in a form is checked against the branch here.
 *   3. **Its number is its own.** Taken inside the transaction with the last
 *      row locked, so two clerks pressing Save in the same second cannot both
 *      be given PAY-1-000042.
 *   4. **It is written whole.** Header and entries go in one transaction, so a
 *      voucher with half its lines cannot exist even if the server dies
 *      mid-save.
 *
 * On tax: a voucher does not compute tax, and there is no tax dropdown on these
 * screens. That is not an omission. GST on a supplier's bill is a line of the
 * voucher — Dr Expenses, Dr Duties & Taxes, Cr the vendor — typed by the person
 * who is reading the bill. Anything else would mean this class inventing a rate,
 * which is the one thing App\Support\Tax exists to prevent.
 */
class Vouchers
{
    /**
     * Half a paisa.
     *
     * Two decimal figures added up in floating point can land a hair apart
     * (0.1 + 0.2 is famously not 0.3), so the balance test allows less than
     * half of the smallest coin that exists. Anything bigger is a real
     * difference and a real difference is refused.
     */
    public const EPSILON = 0.005;

    /**
     * Write a voucher, or refuse it.
     *
     * @param  array{
     *     branch_id: int, voucher_type: string, voucher_date: string,
     *     reference_no?: string|null, narration?: string|null, created_by?: int|null,
     *     source_type?: string|null, source_id?: int|null, is_auto?: int
     * }  $header
     * @param  array<int, array{ledger_id: int|string, debit?: mixed, credit?: mixed, narration?: string|null}>  $lines
     *
     * @throws PostingRefused
     */
    public static function post(array $header, array $lines): Voucher
    {
        $branchId = (int) ($header['branch_id'] ?? 0);
        $type = (string) ($header['voucher_type'] ?? '');

        if ($branchId <= 0) {
            throw new PostingRefused('No branch is selected, so there is nothing to post this voucher to.');
        }

        if (! array_key_exists($type, Voucher::TYPES)) {
            throw new PostingRefused("'{$type}' is not a kind of voucher this system keeps.");
        }

        $clean = self::cleanLines($lines);

        if (count($clean) < 2) {
            throw new PostingRefused(
                'A voucher needs at least two lines — one ledger giving and one receiving. '
                . 'Fill in a ledger and an amount on a second row.'
            );
        }

        $ledgers = self::ledgersFor($branchId, $clean);

        self::guardShape($type, $clean, $ledgers);

        $debit = round(array_sum(array_column($clean, 'debit')), 2);
        $credit = round(array_sum(array_column($clean, 'credit')), 2);

        /*
         * The balance check, and the reason this class exists. It is here
         * rather than in a form request because the Journal, the Payment
         * voucher, the Vendor Payment screen and any future automatic posting
         * all have to obey it, and only one of those is a form.
         */
        if (abs($debit - $credit) > self::EPSILON) {
            throw new PostingRefused(sprintf(
                'This voucher does not balance — debit ₹%s against credit ₹%s, out by ₹%s. '
                . 'Nothing was saved.',
                number_format($debit, 2),
                number_format($credit, 2),
                number_format(abs(round($debit - $credit, 2)), 2)
            ));
        }

        if ($debit <= 0) {
            throw new PostingRefused('Every line is zero — there is no money on this voucher.');
        }

        $date = rescue(
            fn () => CarbonImmutable::parse($header['voucher_date'] ?? 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        /*
         * `(branch_id, voucher_type, voucher_no)` is unique. The lock inside
         * nextNumber() is what stops two clerks taking the same number; the
         * retry is the second line of defence for the one case a lock cannot
         * cover — the very first voucher of a series, where there is no row to
         * lock yet. A loser of that race gets a duplicate-key error, rolls
         * back, reads the next free number and saves. Nobody sees a 500 page.
         */
        return retry(3, fn () => DB::transaction(function () use ($branchId, $type, $date, $header, $clean, $debit) {
            // Posting the same source document twice is the classic accounting
            // bug: a checkout screen re-opened, one bill in the books twice,
            // and a trial balance out by exactly that amount. A voucher that
            // names its source is written once and once only.
            if (! empty($header['source_type']) && ! empty($header['source_id'])) {
                $already = Voucher::query()
                    ->where('branch_id', $branchId)
                    ->where('source_type', $header['source_type'])
                    ->where('source_id', $header['source_id'])
                    ->posted()
                    ->first();

                if ($already) {
                    return $already;
                }
            }

            $voucher = Voucher::create([
                'branch_id' => $branchId,
                'voucher_type' => $type,
                'voucher_no' => self::nextNumber($branchId, $type, true),
                'voucher_date' => $date,
                'reference_no' => $header['reference_no'] ?? null,
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'is_auto' => (int) ($header['is_auto'] ?? 0),
                'is_cancelled' => 0,
                'amount' => $debit,
                'narration' => $header['narration'] ?? null,
                'created_by' => $header['created_by'] ?? null,
            ]);

            foreach ($clean as $line) {
                $voucher->entries()->create([
                    'ledger_id' => $line['ledger_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'narration' => $line['narration'],
                ]);
            }

            return $voucher;
        }), 50);
    }

    /**
     * The next number in a series — PAY-1-000001.
     *
     * `$lock` must be true when the number is about to be used, and only ever
     * inside a transaction: it holds the last row of the series until the new
     * one is committed, so the clerk who arrives a millisecond later reads the
     * number that was just taken rather than the one before it. The screens
     * call it with `$lock = false` to *show* the next number on a blank form,
     * where locking a row for the length of a page render would be a fine way
     * to make the accounts screen feel broken.
     */
    public static function nextNumber(int $branchId, string $type, bool $lock = false): string
    {
        $prefix = (Voucher::PREFIXES[$type] ?? 'VCH') . '-' . $branchId . '-';

        $query = DB::table('vouchers')
            ->where('branch_id', $branchId)
            ->where('voucher_type', $type)
            ->where('voucher_no', 'like', $prefix . '%')
            ->orderByDesc('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $last = $query->value('voucher_no');

        // Read the trailing digits rather than trusting the prefix length: a
        // branch id that grew a digit must not restart the series at 1.
        $next = ($last && preg_match('/(\d+)$/', (string) $last, $match))
            ? ((int) $match[1]) + 1
            : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Cancel a voucher. It keeps its number and its lines for ever.
     *
     * Deleting would leave a hole in the number series, which is the first
     * thing an auditor asks about, and would destroy the evidence that anything
     * was entered at all. `is_cancelled = 1` keeps the record and takes it out
     * of every report — see Voucher::scopePosted() and Voucher::liveEntries(),
     * which every balance in the module reads through.
     */
    public static function cancel(Voucher $voucher): Voucher
    {
        $voucher->update(['is_cancelled' => 1]);

        return $voucher;
    }

    /**
     * Tell the hotel a voucher was posted.
     *
     * Called from the screens rather than from post(), because a posting made
     * by another module on a guest's behalf is not news — a person pressing
     * Save is.
     */
    public static function announce(Voucher $voucher, ?string $who = null): void
    {
        $event = match ($voucher->voucher_type) {
            'payment' => 'accounts.payment',
            'receipt' => 'accounts.receipt',
            default => 'accounts.voucher',
        };

        $route = match ($voucher->source_type) {
            'vendor_payment' => 'accounting.vendor-payment',
            'customer_receipt' => 'accounting.customer-receipt',
            default => match ($voucher->voucher_type) {
                'payment' => 'accounting.payment-voucher',
                'receipt' => 'accounting.receipt-voucher',
                'contra' => 'accounting.contra-voucher',
                'journal' => 'accounting.journal',
                default => 'accounting.day-book',
            },
        };

        Notify::event($event)
            ->branch((int) $voucher->branch_id)
            ->title($voucher->type_label . ' voucher ' . $voucher->voucher_no . ' — ₹' . number_format((float) $voucher->amount, 2))
            ->body(trim(($who ? $who . ' · ' : '') . ($voucher->narration ?: $voucher->voucher_date?->format('d M Y'))))
            ->url(Route::has($route) ? route($route) : null)
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Throw away the empty rows and make the rest trustworthy.
     *
     * Nothing the browser sends is believed: the amounts are re-read as
     * numbers, rounded to the paisa the database stores, and a row that tries
     * to be a debit and a credit at once is refused rather than quietly
     * netted — a line that balances itself would let a voucher pass the
     * balance check while saying nothing at all.
     *
     * @return array<int, array{ledger_id: int, debit: float, credit: float, narration: string|null}>
     */
    private static function cleanLines(array $lines): array
    {
        $clean = [];

        foreach ($lines as $line) {
            $ledgerId = (int) ($line['ledger_id'] ?? 0);
            $debit = round(max(0, (float) ($line['debit'] ?? 0)), 2);
            $credit = round(max(0, (float) ($line['credit'] ?? 0)), 2);

            // A blank row on a grid of blank rows is not an error.
            if ($ledgerId <= 0 && $debit <= 0 && $credit <= 0) {
                continue;
            }

            if ($ledgerId <= 0) {
                throw new PostingRefused('One line has an amount but no ledger. Pick a ledger or clear the amount.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new PostingRefused(
                    'A line is either a debit or a credit, never both. Put the two halves on two lines.'
                );
            }

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $clean[] = [
                'ledger_id' => $ledgerId,
                'debit' => $debit,
                'credit' => $credit,
                'narration' => isset($line['narration']) && $line['narration'] !== ''
                    ? mb_substr((string) $line['narration'], 0, 255)
                    : null,
            ];
        }

        return $clean;
    }

    /**
     * Every ledger on the voucher, checked against the branch.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return \Illuminate\Support\Collection<int, Ledger>
     */
    private static function ledgersFor(int $branchId, array $lines)
    {
        $ids = array_values(array_unique(array_column($lines, 'ledger_id')));

        $ledgers = Ledger::query()
            ->forBranch($branchId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $ledger = $ledgers->get($id);

            if (! $ledger) {
                throw new PostingRefused('One of those ledgers does not belong to this branch.');
            }

            if (! $ledger->isActive()) {
                throw new PostingRefused(
                    "{$ledger->name} is deactivated, so nothing new can be posted to it. "
                    . 'Switch it back on from Accounting → Ledger first.'
                );
            }
        }

        return $ledgers;
    }

    /**
     * What each kind of voucher is, enforced.
     *
     * These are not screen preferences — they are what makes the Cash Book
     * readable. A "payment" that never credits cash or a bank is not a payment,
     * and a contra that touches an ordinary ledger is a journal wearing a
     * disguise. Both are refused here rather than on the form, so no future
     * screen can post one by accident.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  \Illuminate\Support\Collection<int, Ledger>  $ledgers
     */
    private static function guardShape(string $type, array $lines, $ledgers): void
    {
        $isCashBank = fn (array $line) => (bool) $ledgers->get($line['ledger_id'])?->isCashOrBank();

        if ($type === 'contra') {
            foreach ($lines as $line) {
                if (! $isCashBank($line)) {
                    $name = $ledgers->get($line['ledger_id'])?->name ?? 'That ledger';

                    throw new PostingRefused(
                        "A Contra voucher only moves money between the cash box and a bank account. "
                        . "{$name} is neither — mark it as cash or bank on the Ledger screen, or use a Journal."
                    );
                }
            }

            /*
             * Both sides the same ledger balances perfectly and means nothing:
             * money taken out of the cash box and put straight back in. It
             * would pass every other check, sit in the day book for ever and
             * make the cash book harder to read, so it is refused here.
             */
            if (count(array_unique(array_column($lines, 'ledger_id'))) < 2) {
                throw new PostingRefused(
                    'A Contra moves money from one place to another — pick two different ledgers.'
                );
            }

            return;
        }

        if ($type === 'payment') {
            $paid = array_filter($lines, fn ($line) => $line['credit'] > 0 && $isCashBank($line));

            if ($paid === []) {
                throw new PostingRefused(
                    'A Payment voucher pays money out of the cash box or a bank account, '
                    . 'so one of its credit lines must be a cash or bank ledger.'
                );
            }
        }

        if ($type === 'receipt') {
            $taken = array_filter($lines, fn ($line) => $line['debit'] > 0 && $isCashBank($line));

            if ($taken === []) {
                throw new PostingRefused(
                    'A Receipt voucher takes money into the cash box or a bank account, '
                    . 'so one of its debit lines must be a cash or bank ledger.'
                );
            }
        }
    }
}
