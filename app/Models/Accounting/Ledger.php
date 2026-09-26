<?php

namespace App\Models\Accounting;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ledger — one named account money can move to or from.
 *
 * "Cash", "HDFC Current A/c", "Room Revenue", a vendor, a company that books
 * rooms on credit. Every voucher line points at one of these, and every report
 * in the module is these rows with their entries added up.
 *
 * `cash_type` is the flag that makes the Cash Book, the Bank Book and the
 * Contra voucher possible. Naming a ledger "Cash" is not enough — two branches
 * have two cash boxes with the same name — so the ledger says outright whether
 * it *is* the cash box, *is* a bank account, or is neither.
 */
class Ledger extends BaseMaster
{
    protected $table = 'ledgers';

    /** Worded the way the form asks the question, not the way the column stores it. */
    public const CASH_TYPES = [
        'none' => 'Neither — an ordinary ledger',
        'cash' => 'This is the cash box',
        'bank' => 'This is a bank account',
    ];

    public const BALANCE_TYPES = [
        'dr' => 'Dr',
        'cr' => 'Cr',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_system' => 'integer',
            'status' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class, 'ledger_id');
    }

    /** Name, code or mobile — a clerk searches with whichever they remember. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        }));
    }

    /** The cash box(es) and the bank account(s) — what a Contra moves between. */
    public function scopeCashOrBank(Builder $query): Builder
    {
        return $query->whereIn('cash_type', ['cash', 'bank']);
    }

    public function scopeOfCashType(Builder $query, string $type): Builder
    {
        return $query->where('cash_type', $type);
    }

    public function isCashOrBank(): bool
    {
        return in_array($this->cash_type, ['cash', 'bank'], true);
    }

    public function isSystem(): bool
    {
        return (int) $this->is_system === 1;
    }

    /**
     * The opening balance as a signed figure: **positive is Dr, negative is Cr**.
     *
     * Every balance in this module is carried that way, so nothing has to pass
     * a "which side is this?" flag around beside the number. See
     * App\Support\Ledgers for the rest of the rule.
     */
    public function signedOpening(): float
    {
        $amount = round((float) $this->opening_balance, 2);

        return $this->balance_type === 'cr' ? -$amount : $amount;
    }

    /** Has anything ever been posted against it? Decides delete vs deactivate. */
    public function hasEntries(): bool
    {
        return $this->entries()->exists();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->code ? $this->name . ' (' . $this->code . ')' : (string) $this->name;
    }
}
