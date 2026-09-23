<?php

namespace App\Support;

use App\Models\Master\TaxMaster;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Tax is a choice, never a side effect.
 *
 * The rule this class exists to enforce is one sentence long: **no screen in
 * this system adds tax to a figure unless somebody picked a tax.** Every place
 * that charges money — a room row, a folio line, a POS order, a pool or hall
 * booking, a car trip, an accounting voucher — carries a `tax_choice` beside
 * its amount, and that choice is what decides the percent. Left alone it is
 * `none`, and `none` means zero.
 *
 * The four kinds of choice:
 *
 *   `none`     no tax. The default everywhere, and what a blank field means.
 *   `slab`     the Indian hotel GST slab worked out from the rent for one room
 *              for one night (config('pms.room_tax_slabs')). Rooms only —
 *              on anything else it is the same as `none`, because a slab on a
 *              laundry bill is not a thing.
 *   `fixed`    whatever percent is already stored on the row. This is what a
 *              row written before tax became a choice resolves to, so
 *              re-pricing an old booking never silently changes its tax.
 *   `<id>`     a row from Masters → Tax. "GST 12%", "VAT 5%", anything the
 *              hotel set up.
 *
 * Nothing here reads a hidden default. `Tax::percent(null, ...)` is 0, and that
 * is the point — a field that was never filled in cannot quietly attract 18%.
 */
class Tax
{
    public const NONE = 'none';

    public const SLAB = 'slab';

    public const FIXED = 'fixed';

    /**
     * What a brand-new row starts as.
     *
     * `config('pms.tax_default')` exists so a hotel that always charges the
     * same tax can stop picking it on every screen — set it to `slab` or to a
     * tax id and the dropdowns open on that instead. Out of the box it is
     * `none`, which is what the dropdowns open on.
     */
    public static function defaultChoice(): string
    {
        $default = (string) config('pms.tax_default', self::NONE);

        return $default === '' ? self::NONE : $default;
    }

    /**
     * The percent a choice works out to.
     *
     * `$nightly` only matters for `slab`; `$stored` only for `fixed`. Both are
     * ignored otherwise, so callers can pass them unconditionally.
     */
    public static function percent(
        string|int|null $choice,
        ?int $branchId = null,
        float $nightly = 0,
        float $stored = 0
    ): float {
        $choice = self::normalise($choice);

        if ($choice === self::NONE) {
            return 0.0;
        }

        if ($choice === self::FIXED) {
            return max(0.0, round($stored, 2));
        }

        if ($choice === self::SLAB) {
            return self::slabPercent($nightly, $branchId);
        }

        // A tax that was deleted, or belongs to another branch, is no tax at
        // all. Falling back to a default here is exactly the silent behaviour
        // this class was written to remove.
        return (float) (self::masters($branchId)->firstWhere('id', (int) $choice)?->percent ?? 0);
    }

    /**
     * The Indian hotel GST slab for one room for one night.
     *
     * Only reachable by choosing `slab` — it is never the default.
     */
    public static function slabPercent(float $nightly, ?int $branchId = null): float
    {
        $slabs = config('pms.room_tax_slabs', []);

        if (empty($slabs)) {
            return (float) (self::masters($branchId)->firstWhere('is_default', 1)?->percent ?? 0);
        }

        foreach ($slabs as $upTo => $percent) {
            if ($upTo === 'above') {
                continue;
            }

            if ($nightly <= (float) $upTo) {
                return (float) $percent;
            }
        }

        return (float) ($slabs['above'] ?? 0);
    }

    /**
     * What to put in a Tax dropdown.
     *
     * `No Tax` is always first and always selectable. `$withSlab` adds the GST
     * slab option, which only the room screens pass.
     *
     * @return array<string, string>
     */
    public static function options(?int $branchId = null, bool $withSlab = false): array
    {
        $options = [self::NONE => 'No Tax'];

        if ($withSlab) {
            $options[self::SLAB] = 'GST slab (by room rent)';
        }

        foreach (self::masters($branchId) as $tax) {
            $options[(string) $tax->id] = self::describe($tax);
        }

        return $options;
    }

    /**
     * The same list, plus `fixed` — but only when a row is already on it.
     *
     * An edit screen has to be able to show a booking that was priced before
     * tax became a choice without changing what it charges the moment it is
     * opened. So the option appears for exactly that row and for no other.
     *
     * @return array<string, string>
     */
    public static function optionsFor(
        string|int|null $choice,
        ?int $branchId = null,
        bool $withSlab = false,
        float $stored = 0
    ): array {
        $options = self::options($branchId, $withSlab);
        $choice = self::normalise($choice);

        if ($choice === self::FIXED) {
            $options[self::FIXED] = 'As billed — ' . self::trim($stored) . '%';
        }

        // A tax that has since been deleted still has to be nameable, or the
        // dropdown would quietly re-point an old row at "No Tax" on save.
        if (! array_key_exists($choice, $options)) {
            $options[$choice] = 'Tax #' . $choice . ' (removed)';
        }

        return $options;
    }

    /** What the row says on a bill — "GST 12%", or nothing at all. */
    public static function label(
        string|int|null $choice,
        ?int $branchId = null,
        float $nightly = 0,
        float $stored = 0
    ): string {
        $choice = self::normalise($choice);

        if ($choice === self::NONE) {
            return 'No Tax';
        }

        $percent = self::percent($choice, $branchId, $nightly, $stored);

        return match ($choice) {
            self::SLAB => 'GST slab — ' . self::trim($percent) . '%',
            self::FIXED => 'As billed — ' . self::trim($percent) . '%',
            default => self::masters($branchId)->firstWhere('id', (int) $choice)
                ? self::describe(self::masters($branchId)->firstWhere('id', (int) $choice))
                : self::trim($percent) . '%',
        };
    }

    /**
     * The validation rule for a tax dropdown.
     *
     * Written as a closure over the live list rather than as `in:` with a
     * hard-coded set, so a tax added in Masters works on the next request
     * without anybody remembering to edit a rule.
     */
    public static function rule(?int $branchId = null, bool $withSlab = false): array
    {
        $allowed = array_keys(self::options($branchId, $withSlab));
        $allowed[] = self::FIXED;

        return ['nullable', 'string', Rule::in($allowed)];
    }

    /**
     * Turn whatever the form posted into one of the four kinds.
     *
     * An empty string, a null, a zero and the word "none" all mean the same
     * thing — nobody picked a tax.
     */
    public static function normalise(string|int|null $choice): string
    {
        $choice = trim((string) $choice);

        if ($choice === '' || $choice === '0' || strtolower($choice) === self::NONE) {
            return self::NONE;
        }

        $lower = strtolower($choice);

        if (in_array($lower, [self::SLAB, self::FIXED], true)) {
            return $lower;
        }

        return ctype_digit($choice) ? $choice : self::NONE;
    }

    /** True when this row will not add a paisa of tax to anything. */
    public static function isOff(string|int|null $choice): bool
    {
        return self::normalise($choice) === self::NONE;
    }

    /**
     * The tax a service master suggests, as a choice rather than a percent.
     *
     * Masters → Service can point at a tax. That is a *suggestion* the screen
     * fills the dropdown with; the clerk can still set it back to No Tax, and
     * a service with no tax attached suggests nothing.
     */
    public static function suggestFor(?int $taxMasterId): string
    {
        return $taxMasterId ? (string) $taxMasterId : self::NONE;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Per-request cache — a dropdown on twenty grid rows costs one query. */
    private static array $cache = [];

    private static function masters(?int $branchId): Collection
    {
        $key = (string) ($branchId ?? 'all');

        return self::$cache[$key] ??= TaxMaster::query()
            ->forBranch($branchId)
            ->where('status', 1)
            ->orderBy('percent')
            ->get(['id', 'name', 'percent', 'is_default']);
    }

    /**
     * "GST" at 12% written out as "GST — 12%".
     *
     * Public because more than one screen has to name a tax the same way, and
     * two places formatting it differently is how a hotel ends up wondering
     * whether "GST" and "GST 12%" are the same thing.
     */
    public static function describe(object $tax): string
    {
        $percent = self::trim((float) $tax->percent);

        // "GST 12%" named "GST 12%" should not read "GST 12% — 12%".
        return str_contains($tax->name, $percent . '%')
            ? $tax->name
            : $tax->name . ' — ' . $percent . '%';
    }

    /** 12.00 → "12", 2.50 → "2.5". */
    private static function trim(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Tests reach for this; nothing in the app does. */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
