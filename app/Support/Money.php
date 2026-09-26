<?php

namespace App\Support;

/**
 * The money rules for a reservation, in one place.
 *
 * The browser mirrors these same rules so the totals move as you type, but
 * this class is the one that decides what gets stored — never trust the
 * numbers the form posts back.
 *
 * **Tax is never added here on its own.** Every row that reaches this class
 * carries a `tax_choice`, and {@see Tax} turns that into a percent. A row that
 * carries none is a row with no tax, which is what a blank Tax dropdown means
 * and what every screen starts on.
 */
class Money
{
    /**
     * GST percent for a room, from its rent for one room for one night.
     *
     * @deprecated Kept so nothing that still calls it breaks. It is the `slab`
     * choice and nothing else — {@see Tax::percent()} is what the app uses, and
     * it only reaches this when somebody picked "GST slab" from a dropdown.
     */
    public static function roomTaxPercent(float $nightlyRent, ?int $branchId = null): float
    {
        return Tax::slabPercent($nightlyRent, $branchId);
    }

    /**
     * Split a gross figure into taxable value + tax.
     *
     * Exclusive: the gross IS the taxable value and tax is added on top.
     * Inclusive: the gross already contains the tax, so it is worked backwards.
     *
     * @return array{amount: float, tax: float, net: float}
     */
    public static function split(float $gross, float $percent, string $taxType = 'exclusive'): array
    {
        $gross = round($gross, 2);

        if ($percent <= 0) {
            return ['amount' => $gross, 'tax' => 0.0, 'net' => $gross];
        }

        if ($taxType === 'inclusive') {
            $amount = round($gross / (1 + $percent / 100), 2);

            return [
                'amount' => $amount,
                'tax' => round($gross - $amount, 2),
                'net' => $gross,
            ];
        }

        $tax = round($gross * $percent / 100, 2);

        return [
            'amount' => $gross,
            'tax' => $tax,
            'net' => round($gross + $tax, 2),
        ];
    }

    /**
     * Work out one row of the Rooms Allotment grid.
     *
     * `tax_choice` is what decides the tax: leave it out and the row is taxed
     * at nothing, which is the default the whole system runs on.
     *
     * @param  array{room_rent: float, discount: float, plan_charge: float, no_of_days: int, no_of_rooms: int, tax_type: string, tax_choice?: string, tax_percent?: float}  $row
     * @return array{nightly: float, amount: float, tax_percent: float, tax_amount: float, net_amount: float}
     */
    public static function roomRow(array $row, ?int $branchId = null): array
    {
        $days = max(1, (int) ($row['no_of_days'] ?? 1));
        $count = max(1, (int) ($row['no_of_rooms'] ?? 1));

        // Per room, per night — the figure both the slab and the guest see.
        $nightly = round(
            max(0, (float) ($row['room_rent'] ?? 0))
            + max(0, (float) ($row['plan_charge'] ?? 0))
            - max(0, (float) ($row['discount'] ?? 0)),
            2
        );

        $nightly = max(0, $nightly);
        $gross = round($nightly * $days * $count, 2);

        $percent = Tax::percent(
            $row['tax_choice'] ?? Tax::NONE,
            $branchId,
            $nightly,
            (float) ($row['tax_percent'] ?? 0)
        );

        $split = self::split($gross, $percent, $row['tax_type'] ?? 'exclusive');

        return [
            'nightly' => $nightly,
            'amount' => $split['amount'],
            'tax_percent' => $percent,
            'tax_amount' => $split['tax'],
            'net_amount' => $split['net'],
        ];
    }

    /**
     * Work out one row of the service grid.
     *
     * Pass `tax_choice` and the choice wins; pass only `tax_percent` and that
     * percent is used as given. The second form is how a row that was already
     * priced gets re-priced without its tax moving.
     *
     * @param  array{qty: float, price: float, tax_percent?: float, tax_type: string, tax_choice?: string}  $row
     * @return array{amount: float, tax_amount: float, tax_percent: float, total_amount: float}
     */
    public static function serviceRow(array $row, ?int $branchId = null): array
    {
        $gross = round(
            max(0, (float) ($row['qty'] ?? 1)) * max(0, (float) ($row['price'] ?? 0)),
            2
        );

        $percent = array_key_exists('tax_choice', $row)
            ? Tax::percent($row['tax_choice'], $branchId, 0, (float) ($row['tax_percent'] ?? 0))
            : (float) ($row['tax_percent'] ?? 0);

        $split = self::split($gross, $percent, $row['tax_type'] ?? 'exclusive');

        return [
            'amount' => $split['amount'],
            'tax_percent' => $percent,
            'tax_amount' => $split['tax'],
            'total_amount' => $split['net'],
        ];
    }

    /** Nights between two dates — same-day stays still count as one. */
    public static function nights(string $from, string $to): int
    {
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400);

        return max(1, $days);
    }
}
