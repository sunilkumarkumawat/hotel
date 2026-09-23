<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The GST side of an invoice: who the buyer is, where the supply happened,
 * and how the tax splits.
 *
 * This class records and arranges facts. It is not tax advice, and neither is
 * the software: a hotel's own accountant decides how anything beyond room rent
 * is treated, and the state code table below is a convenience that should be
 * checked against the current notifications rather than trusted because it is
 * in a file.
 *
 * ── The one rule worth stating ────────────────────────────────────────────
 *
 * For hotel accommodation the place of supply is the location of the hotel,
 * whoever the guest is and wherever they came from. So a room sold to a
 * Bangalore company by a Udaipur hotel is CGST + SGST of Rajasthan, not IGST —
 * which is the single most common thing to get wrong, because every other kind
 * of B2B sale works the other way round. It is still stored per bill: a bill
 * can carry more than accommodation, and a rule that is true today should
 * still be visible in the data on the day it changes.
 */
class Gst
{
    /**
     * State codes as GST numbers them — the first two digits of a GSTIN.
     *
     * Kept here rather than in the states table because these are the tax
     * department's numbers, not the app's, and they do not line up with any
     * other list of Indian states the system holds.
     *
     * Two of them have history worth knowing: 25 (Daman & Diu) was folded into
     * 26 when the union territories merged, and 28 was Andhra Pradesh before
     * the bifurcation — a present-day AP GSTIN starts 37. Old invoices carrying
     * the old codes are still valid documents, so both are listed.
     */
    public const STATES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '25' => 'Daman and Diu (merged into 26)',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '28' => 'Andhra Pradesh (before bifurcation)',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
        '99' => 'Centre Jurisdiction',
    ];

    /** The service code for accommodation, unless the branch has set its own. */
    public const DEFAULT_SAC = '996311';

    /*
    |--------------------------------------------------------------------------
    | GSTIN
    |--------------------------------------------------------------------------
    */

    /**
     * Does this look like a GSTIN?
     *
     * Shape only: two state digits, a ten-character PAN, an entity digit, a Z,
     * and a check character. It deliberately does NOT verify the checksum or
     * ask the portal whether the number is live — a screen that refuses a
     * valid new registration because a check digit rule changed is worse than
     * one that accepts a typo somebody will notice on the invoice.
     */
    public static function looksLikeGstin(?string $gstin): bool
    {
        $gstin = strtoupper(trim((string) $gstin));

        if ($gstin === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin);
    }

    /** The state a GSTIN belongs to — its first two digits. */
    public static function stateOf(?string $gstin): ?string
    {
        $gstin = strtoupper(trim((string) $gstin));

        if (strlen($gstin) < 2) {
            return null;
        }

        $code = substr($gstin, 0, 2);

        return isset(self::STATES[$code]) ? $code : null;
    }

    public static function stateName(?string $code): ?string
    {
        return $code ? (self::STATES[$code] ?? null) : null;
    }

    /** "08 — Rajasthan", the way a return wants it written. */
    public static function stateLabel(?string $code): ?string
    {
        $name = self::stateName($code);

        return $name ? $code . ' — ' . $name : null;
    }

    /**
     * The PAN inside a GSTIN — characters 3 to 12.
     *
     * Useful for matching a company that has registrations in several states:
     * they are different GSTINs but one business.
     */
    public static function panOf(?string $gstin): ?string
    {
        return self::looksLikeGstin($gstin) ? substr(strtoupper(trim((string) $gstin)), 2, 10) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Place of supply
    |--------------------------------------------------------------------------
    */

    /**
     * Where a stay was supplied, and therefore how its tax splits.
     *
     * For accommodation this is the hotel, full stop. The buyer's GSTIN is
     * taken only to record who the invoice is for; it does not move the supply.
     *
     * @return array{code: ?string, name: ?string, igst: bool, note: string}
     */
    public static function placeOfSupply(?string $branchStateCode, ?string $buyerGstin = null): array
    {
        $code = $branchStateCode ?: null;
        $buyerState = self::stateOf($buyerGstin);

        $note = 'Hotel accommodation is supplied where the hotel is, so this bill is CGST + SGST.';

        if ($buyerState && $code && $buyerState !== $code) {
            $note .= ' The buyer is registered in ' . (self::stateName($buyerState) ?? $buyerState)
                . ', which does not change that — it only means they claim the credit as an inter-state buyer.';
        }

        return [
            'code' => $code,
            'name' => self::stateName($code),
            'igst' => false,
            'note' => $note,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The tax on an invoice
    |--------------------------------------------------------------------------
    */

    /**
     * Group an invoice's lines by tax rate — one row per rate, which is what
     * both the printed summary and GSTR-1 want.
     *
     * @param  iterable<object>  $lines  rows with amount, tax_percent, tax_amount, total_amount
     * @return Collection<int, array{rate: float, taxable: float, tax: float, cgst: float, sgst: float, igst: float, total: float}>
     */
    public static function byRate(iterable $lines, bool $igst = false): Collection
    {
        return collect($lines)
            ->filter(fn ($line) => (float) ($line->tax_amount ?? 0) > 0 || (float) ($line->tax_percent ?? 0) > 0)
            ->groupBy(fn ($line) => (string) (float) ($line->tax_percent ?? 0))
            ->map(function ($group, $rate) use ($igst) {
                $rate = (float) $rate;
                $taxable = round(collect($group)->sum(fn ($l) => (float) ($l->amount ?? 0)), 2);
                $tax = round(collect($group)->sum(fn ($l) => (float) ($l->tax_amount ?? 0)), 2);

                return [
                    'rate' => $rate,
                    'taxable' => $taxable,
                    'tax' => $tax,
                    // Half each within a state; the whole lot as IGST across one.
                    'cgst' => $igst ? 0.0 : round($tax / 2, 2),
                    'sgst' => $igst ? 0.0 : round($tax - round($tax / 2, 2), 2),
                    'igst' => $igst ? $tax : 0.0,
                    'total' => round($taxable + $tax, 2),
                ];
            })
            ->sortBy('rate')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | GSTR-1
    |--------------------------------------------------------------------------
    */

    /**
     * The month's outward supplies, split the way GSTR-1 splits them.
     *
     * **B2B** — every invoice to somebody with a GSTIN, listed one by one,
     * because the buyer has to be able to find their own invoice in it.
     *
     * **B2CL** — invoices to unregistered buyers in another state above the
     * threshold, also listed one by one. Hotel accommodation is supplied in
     * the hotel's own state, so this table is normally empty for room bills
     * and is produced anyway rather than assumed away.
     *
     * **B2CS** — everybody else, summarised by rate. Most of a hotel's month.
     *
     * The figures come from the bills as they were issued. Nothing here
     * recalculates tax: a return that disagreed with the invoice the guest is
     * holding would be the worst possible outcome.
     *
     * @return array{
     *     from: string, to: string, b2b: Collection, b2cl: Collection,
     *     b2cs: Collection, totals: array<string, float>, hsn: Collection
     * }
     */
    public static function gstr1(int $branchId, string $from, string $to, ?string $branchState = null): array
    {
        $bills = DB::table('bills as b')
            ->leftJoin('check_ins as ci', 'ci.id', '=', 'b.check_in_id')
            ->where('b.branch_id', $branchId)
            ->where('b.status', '!=', 'cancelled')
            ->whereBetween('b.bill_date', [$from, $to])
            ->orderBy('b.bill_date')
            ->orderBy('b.bill_no')
            ->get([
                'b.id', 'b.bill_no', 'b.bill_date', 'b.buyer_gstin', 'b.buyer_name',
                'b.place_of_supply', 'b.place_of_supply_name', 'b.is_igst',
                'b.room_total', 'b.service_total', 'b.discount_total',
                'b.tax_total', 'b.net_amount', 'b.check_in_id', 'ci.guest_name',
            ]);

        $ids = $bills->pluck('id')->all() ?: [0];

        /*
         * The rate breakdown per bill comes off the folio lines, because that
         * is where the rate lives. A bill total carries one tax number and
         * GSTR-1 wants it split by rate.
         */
        $lines = DB::table('folio_charges as fc')
            ->join('bills as b', 'b.check_in_id', '=', 'fc.check_in_id')
            ->whereIn('b.id', $ids)
            ->selectRaw('b.id as bill_id, fc.tax_percent, '
                . 'COALESCE(SUM(fc.amount), 0) as taxable, COALESCE(SUM(fc.tax_amount), 0) as tax')
            ->groupBy('b.id', 'fc.tax_percent')
            ->get()
            ->groupBy('bill_id');

        $withRates = $bills->map(function ($bill) use ($lines, $branchState) {
            $rates = collect($lines[$bill->id] ?? [])
                ->filter(fn ($r) => (float) $r->taxable > 0 || (float) $r->tax > 0)
                ->map(fn ($r) => [
                    'rate' => (float) $r->tax_percent,
                    'taxable' => round((float) $r->taxable, 2),
                    'tax' => round((float) $r->tax, 2),
                ])
                ->sortBy('rate')
                ->values();

            $bill->rates = $rates;
            $bill->place_of_supply = $bill->place_of_supply ?: $branchState;
            $bill->place_of_supply_name = $bill->place_of_supply_name
                ?: self::stateName($bill->place_of_supply);

            return $bill;
        });

        $b2b = $withRates->filter(fn ($b) => self::looksLikeGstin($b->buyer_gstin))->values();
        $rest = $withRates->reject(fn ($b) => self::looksLikeGstin($b->buyer_gstin));

        // B2CL: unregistered, another state, above the invoice threshold.
        $b2cl = $rest->filter(fn ($b) => $branchState
            && $b->place_of_supply
            && $b->place_of_supply !== $branchState
            && (float) $b->net_amount > self::B2CL_THRESHOLD)->values();

        $b2clIds = $b2cl->pluck('id')->all();
        $b2csBills = $rest->reject(fn ($b) => in_array($b->id, $b2clIds, true));

        // B2CS is summarised, not listed: one row per place of supply per rate.
        $b2cs = $b2csBills
            ->flatMap(fn ($b) => $b->rates->map(fn ($r) => $r + [
                'place' => $b->place_of_supply,
                'place_name' => $b->place_of_supply_name,
            ]))
            ->groupBy(fn ($r) => $r['place'] . '|' . $r['rate'])
            ->map(fn ($group) => [
                'place' => $group->first()['place'],
                'place_name' => $group->first()['place_name'],
                'rate' => $group->first()['rate'],
                'taxable' => round($group->sum('taxable'), 2),
                'tax' => round($group->sum('tax'), 2),
            ])
            ->sortBy([['place', 'asc'], ['rate', 'asc']])
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'b2b' => $b2b,
            'b2cl' => $b2cl,
            'b2cs' => $b2cs,
            'hsn' => self::hsnSummary($branchId, $from, $to),
            'totals' => [
                'invoices' => $withRates->count(),
                'taxable' => round($withRates->sum(fn ($b) => $b->rates->sum('taxable')), 2),
                'tax' => round($withRates->sum(fn ($b) => $b->rates->sum('tax')), 2),
                'net' => round($withRates->sum(fn ($b) => (float) $b->net_amount), 2),
                'b2b' => $b2b->count(),
                'b2cl' => $b2cl->count(),
                'b2cs' => $b2csBills->count(),
            ],
        ];
    }

    /**
     * The threshold above which an inter-state sale to an unregistered buyer
     * is listed invoice by invoice rather than summarised.
     *
     * A number the department changes. It is a constant here so that changing
     * it is one edit in one place — check the current figure before filing.
     */
    public const B2CL_THRESHOLD = 250000;

    /**
     * The HSN/SAC table: what was sold, by code and rate.
     *
     * Room nights are accommodation; everything else is grouped under the
     * service's own code where it has one, and under the branch's default
     * where it does not.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function hsnSummary(int $branchId, string $from, string $to): Collection
    {
        $sac = DB::table('branches')->where('id', $branchId)->value('sac_code') ?: self::DEFAULT_SAC;

        $rows = DB::table('folio_charges as fc')
            ->join('check_ins as ci', 'ci.id', '=', 'fc.check_in_id')
            ->join('bills as b', 'b.check_in_id', '=', 'fc.check_in_id')
            ->where('b.branch_id', $branchId)
            ->where('b.status', '!=', 'cancelled')
            ->whereBetween('b.bill_date', [$from, $to])
            ->selectRaw('fc.charge_type, fc.tax_percent, COALESCE(SUM(fc.qty), 0) as qty, '
                . 'COALESCE(SUM(fc.amount), 0) as taxable, COALESCE(SUM(fc.tax_amount), 0) as tax')
            ->groupBy('fc.charge_type', 'fc.tax_percent')
            ->get();

        return collect($rows)
            ->filter(fn ($r) => (float) $r->taxable > 0)
            ->map(fn ($r) => [
                'code' => $sac,
                'description' => match ($r->charge_type) {
                    'room' => 'Accommodation',
                    'service' => 'Other services',
                    default => ucfirst((string) $r->charge_type),
                },
                'uqc' => 'OTH',
                'qty' => round((float) $r->qty, 2),
                'rate' => (float) $r->tax_percent,
                'taxable' => round((float) $r->taxable, 2),
                'tax' => round((float) $r->tax, 2),
                'total' => round((float) $r->taxable + (float) $r->tax, 2),
            ])
            ->sortBy([['description', 'asc'], ['rate', 'asc']])
            ->values();
    }
}
