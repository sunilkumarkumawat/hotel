<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The month's sales, as a file Tally will swallow.
 *
 * Most Indian hotels keep their books in Tally whatever else they run, and the
 * alternative to this is somebody typing four hundred invoices in by hand on
 * the 5th of every month. So: one XML file of sales vouchers, one voucher per
 * bill, imported with Gateway of Tally → Import Data → Vouchers.
 *
 * ── Two things about the format that are easy to get wrong ────────────────
 *
 * **The sign convention is inverted.** In Tally's XML a debit entry carries
 * ISDEEMEDPOSITIVE "Yes" and a NEGATIVE amount; a credit carries "No" and a
 * positive one. Get it backwards and every voucher imports as a purchase.
 *
 * **Ledger names must already exist in the company**, spelled exactly. Tally
 * creates missing ledgers under its own defaults and the accountant finds out
 * in March. So the names used here are the ones this system already seeds into
 * its own books (see App\Support\Ledgers), and they are listed on screen
 * before the download so they can be created in Tally first.
 */
class Tally
{
    /**
     * The ledgers a voucher from this system will name.
     *
     * Shown to the user before they download, because an import against a
     * company that does not have these is worse than no import at all.
     */
    public const LEDGERS = [
        'Room Revenue' => 'Sales — accommodation, net of tax',
        'Food & Beverage Revenue' => 'Sales — restaurant and room service',
        'Other Revenue' => 'Sales — services and sundries',
        'CGST Payable' => 'Duties & Taxes — central',
        'SGST Payable' => 'Duties & Taxes — state',
        'IGST Payable' => 'Duties & Taxes — inter-state',
        'Discount Allowed' => 'Indirect expense, when a bill carries one',
    ];

    /**
     * Every bill in the range, as vouchers.
     *
     * @return array{xml: string, count: int, total: float, from: string, to: string}
     */
    public static function sales(int $branchId, string $from, string $to, ?string $company = null): array
    {
        $bills = self::bills($branchId, $from, $to);
        $company = trim((string) ($company ?: DB::table('branches')->where('id', $branchId)->value('branch_name')));

        $messages = $bills->map(fn ($bill) => self::voucher($bill, $company))->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . "<ENVELOPE>\n"
            . "  <HEADER>\n    <TALLYREQUEST>Import Data</TALLYREQUEST>\n  </HEADER>\n"
            . "  <BODY>\n    <IMPORTDATA>\n"
            . "      <REQUESTDESC>\n"
            . "        <REPORTNAME>Vouchers</REPORTNAME>\n"
            . "        <STATICVARIABLES>\n"
            . '          <SVCURRENTCOMPANY>' . self::esc($company) . "</SVCURRENTCOMPANY>\n"
            . "        </STATICVARIABLES>\n"
            . "      </REQUESTDESC>\n"
            . "      <REQUESTDATA>\n"
            . $messages . "\n"
            . "      </REQUESTDATA>\n"
            . "    </IMPORTDATA>\n  </BODY>\n</ENVELOPE>\n";

        return [
            'xml' => $xml,
            'count' => $bills->count(),
            'total' => round($bills->sum(fn ($b) => (float) $b->net_amount), 2),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** One bill, as one sales voucher. */
    private static function voucher(object $bill, string $company): string
    {
        $date = CarbonImmutable::parse($bill->bill_date)->format('Ymd');
        $party = trim((string) ($bill->buyer_name ?: $bill->guest_name ?: 'Walk-in Guest'));

        /*
         * The party is debited for what they owe; revenue and tax are credited
         * for what made it up. The two sides are built from the folio rather
         * than from the bill's own totals, so a voucher can never be off by
         * the rounding the bill carries.
         */
        $entries = [self::entry($party, true, (float) $bill->net_amount)];

        foreach (self::revenueOf($bill) as $ledger => $amount) {
            if (abs($amount) >= 0.005) {
                $entries[] = self::entry($ledger, false, $amount);
            }
        }

        $tax = round((float) $bill->tax_total, 2);

        if ($tax >= 0.005) {
            if ($bill->is_igst) {
                $entries[] = self::entry('IGST Payable', false, $tax);
            } else {
                $half = round($tax / 2, 2);
                $entries[] = self::entry('CGST Payable', false, $half);
                $entries[] = self::entry('SGST Payable', false, round($tax - $half, 2));
            }
        }

        if ((float) $bill->discount_total >= 0.005) {
            // A discount is an expense the hotel carried, so it is debited.
            $entries[] = self::entry('Discount Allowed', true, (float) $bill->discount_total);
        }

        /*
         * Every voucher has to balance. If it does not — a bill saved before a
         * rounding fix, say — the difference goes to the party rather than
         * being left for Tally to reject the whole file over. It is flagged in
         * the narration so it can be found.
         */
        $out = array_sum(array_map(fn ($e) => $e['signed'], $entries));
        $note = '';

        if (abs($out) >= 0.005) {
            $entries[] = self::entry($party, $out > 0, abs($out));
            $note = ' | Balanced by ' . number_format(abs($out), 2) . ' — check this bill';
        }

        $lines = implode("\n", array_map(fn ($e) => $e['xml'], $entries));

        return "        <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n"
            . '          <VOUCHER VCHTYPE="Sales" ACTION="Create" OBJVIEW="Accounting Voucher View">' . "\n"
            . '            <DATE>' . $date . "</DATE>\n"
            . "            <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>\n"
            . '            <VOUCHERNUMBER>' . self::esc($bill->bill_no) . "</VOUCHERNUMBER>\n"
            . '            <PARTYLEDGERNAME>' . self::esc($party) . "</PARTYLEDGERNAME>\n"
            . '            <NARRATION>' . self::esc(self::narration($bill) . $note) . "</NARRATION>\n"
            . ($bill->buyer_gstin ? '            <PARTYGSTIN>' . self::esc($bill->buyer_gstin) . "</PARTYGSTIN>\n" : '')
            . ($bill->place_of_supply_name ? '            <PLACEOFSUPPLY>' . self::esc($bill->place_of_supply_name) . "</PLACEOFSUPPLY>\n" : '')
            . $lines . "\n"
            . "          </VOUCHER>\n"
            . '        </TALLYMESSAGE>';
    }

    /**
     * One ledger line.
     *
     * `$debit` decides both halves of Tally's inverted convention at once, so
     * no caller has to remember which way round it goes.
     *
     * @return array{xml: string, signed: float}
     */
    private static function entry(string $ledger, bool $debit, float $amount): array
    {
        $amount = round(abs($amount), 2);
        $signed = $debit ? -$amount : $amount;

        return [
            'signed' => $signed,
            'xml' => "            <ALLLEDGERENTRIES.LIST>\n"
                . '              <LEDGERNAME>' . self::esc($ledger) . "</LEDGERNAME>\n"
                . '              <ISDEEMEDPOSITIVE>' . ($debit ? 'Yes' : 'No') . "</ISDEEMEDPOSITIVE>\n"
                . '              <AMOUNT>' . number_format($signed, 2, '.', '') . "</AMOUNT>\n"
                . '            </ALLLEDGERENTRIES.LIST>',
        ];
    }

    /**
     * What the bill earned, split across the ledgers an accountant expects to
     * see it under — net of tax, because the tax is credited separately.
     *
     * @return array<string, float>
     */
    private static function revenueOf(object $bill): array
    {
        return [
            'Room Revenue' => round((float) $bill->room_taxable, 2),
            'Other Revenue' => round((float) $bill->service_taxable, 2),
            'Food & Beverage Revenue' => round((float) $bill->pos_taxable, 2),
        ];
    }

    private static function narration(object $bill): string
    {
        return trim(implode(' · ', array_filter([
            $bill->guest_name ? 'Guest: ' . $bill->guest_name : null,
            $bill->room_no ? 'Room ' . $bill->room_no : null,
            'Bill ' . $bill->bill_no,
        ])));
    }

    /**
     * The bills, with their revenue already split by charge type.
     *
     * Split in SQL rather than in PHP because the alternative is loading every
     * folio line of a busy month to add three numbers up.
     *
     * @return Collection<int, object>
     */
    private static function bills(int $branchId, string $from, string $to): Collection
    {
        return collect(DB::table('bills as b')
            ->leftJoin('check_ins as ci', 'ci.id', '=', 'b.check_in_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'ci.room_id')
            ->where('b.branch_id', $branchId)
            ->where('b.status', '!=', 'cancelled')
            ->whereBetween('b.bill_date', [$from, $to])
            ->orderBy('b.bill_date')
            ->orderBy('b.bill_no')
            ->selectRaw("b.id, b.bill_no, b.bill_date, b.net_amount, b.tax_total, b.discount_total,
                b.buyer_name, b.buyer_gstin, b.place_of_supply_name, b.is_igst,
                ci.guest_name, r.room_no,
                COALESCE((SELECT SUM(fc.amount) FROM folio_charges fc
                    WHERE fc.check_in_id = b.check_in_id AND fc.charge_type = 'room'), 0) as room_taxable,
                COALESCE((SELECT SUM(fc.amount) FROM folio_charges fc
                    WHERE fc.check_in_id = b.check_in_id AND fc.charge_type IN ('service','misc')), 0) as service_taxable,
                COALESCE((SELECT SUM(po.net_amount - po.tax_total) FROM pos_orders po
                    WHERE po.check_in_id = b.check_in_id AND po.status IN ('billed','settled')), 0) as pos_taxable")
            ->get());
    }

    /** XML is not HTML: five characters, escaped the way a parser expects. */
    private static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
