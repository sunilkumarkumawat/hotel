<?php

namespace App\Models\Accounting;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account group — the shelf a ledger stands on.
 *
 * "Sundry Debtors", "Bank Accounts", "Indirect Expenses": every ledger belongs
 * to one, and the group is what tells the Trial Balance which column a figure
 * belongs in and the Profit & Loss whether to read it at all.
 *
 * `nature` is the whole of that decision, so it is the one field on this screen
 * that must never be changed casually. Move "Room Revenue" from income to
 * expense and last year's profit changes sign without a single voucher being
 * touched — which is why a system group refuses the change outright.
 */
class AccountGroup extends BaseMaster
{
    protected $table = 'account_groups';

    /**
     * The four natures, and which way each one leans.
     *
     * Assets and expenses are **Dr-positive**: money you have or money you
     * spent grows on the debit side. Liabilities and income are
     * **Cr-positive**: money you owe or money you earned grows on the credit
     * side. Everything in App\Support\Ledgers follows from that one sentence.
     */
    public const NATURES = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'income' => 'Income',
        'expense' => 'Expense',
    ];

    /** The two groups the party screens narrow to, by name. */
    public const CREDITORS = 'Sundry Creditors';

    public const DEBTORS = 'Sundry Debtors';

    protected function casts(): array
    {
        return [
            'is_system' => 'integer',
            'status' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class, 'account_group_id');
    }

    public function scopeOfNature(Builder $query, string|array $nature): Builder
    {
        return $query->whereIn('nature', (array) $nature);
    }

    public function getNatureLabelAttribute(): string
    {
        return self::NATURES[$this->nature] ?? ucfirst((string) $this->nature);
    }

    /** A group the hotel did not make and may not unmake. */
    public function isSystem(): bool
    {
        return (int) $this->is_system === 1;
    }

    /**
     * This group and everything under it, as ids.
     *
     * Vendor Payment asks for "ledgers under Sundry Creditors", and a hotel
     * that has split its creditors into "Suppliers" and "Contractors" still
     * means both. The walk is depth-capped because a parent_id that somehow
     * points at its own descendant would otherwise spin for ever — the Group
     * screen refuses to write such a loop, but this runs on data it did not
     * write.
     *
     * @param  int|array<int, int>  $rootIds
     * @return array<int, int>
     */
    public static function subtreeIds(int $branchId, int|array $rootIds): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) $rootIds)));

        if ($ids === []) {
            return [];
        }

        $found = $ids;
        $frontier = $ids;

        for ($depth = 0; $depth < 10 && $frontier !== []; $depth++) {
            $frontier = self::query()
                ->forBranch($branchId)
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $found)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $found = array_merge($found, $frontier);
        }

        return array_values(array_unique($found));
    }

    /**
     * @return array<int, int>
     */
    public static function namedSubtreeIds(int $branchId, string $name): array
    {
        $roots = self::query()
            ->forBranch($branchId)
            ->where('name', $name)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return self::subtreeIds($branchId, $roots);
    }

    /** "Current Assets → Bank Accounts" — what the dropdowns read. */
    public function getPathAttribute(): string
    {
        return $this->parent ? $this->parent->name . ' → ' . $this->name : $this->name;
    }
}
