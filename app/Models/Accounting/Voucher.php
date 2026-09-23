<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One voucher — the header of a double-entry posting.
 *
 * Payment, Receipt, Contra, Journal and the two the rest of the system posts
 * for itself (Sales, Purchase) are all the same row with a different
 * `voucher_type`; the money lives in `voucher_entries`, which must balance.
 * Nothing writes here directly — App\Support\Vouchers::post() is the only door,
 * because it is the only place that checks the balance and takes the number.
 *
 * **A voucher is never deleted, only cancelled.** Voucher numbers are a
 * continuous series and a missing number is the first thing an auditor asks
 * about, so a mistake keeps its number and gets `is_cancelled = 1`. Its entries
 * stay on disk — the Day Book can still show that something was entered and
 * withdrawn — and every report leaves them out through the `posted()` scope
 * below, which is the one place that rule is written.
 */
class Voucher extends Model
{
    use RecordsActivity;

    protected $table = 'vouchers';

    protected $guarded = ['id'];

    public const TYPES = [
        'payment' => 'Payment',
        'receipt' => 'Receipt',
        'contra' => 'Contra',
        'journal' => 'Journal',
        'sales' => 'Sales',
        'purchase' => 'Purchase',
    ];

    /** The letters a clerk reads out over the phone. */
    public const PREFIXES = [
        'payment' => 'PAY',
        'receipt' => 'REC',
        'contra' => 'CON',
        'journal' => 'JV',
        'sales' => 'SAL',
        'purchase' => 'PUR',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'is_auto' => 'integer',
            'is_cancelled' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class, 'voucher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Live vouchers — the only ones any report is allowed to add up.
     *
     * Every list and every total in the module goes through this scope or
     * through liveEntries() below, so "exclude cancelled" is written once and
     * cannot be forgotten on the one screen nobody re-reads.
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('is_cancelled', 0);
    }

    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= (int) \App\Helpers\Helper::getActiveBranchId();

        return $query->where('branch_id', $branchId);
    }

    public function scopeOfType(Builder $query, string|array|null $type): Builder
    {
        return $query->when($type, fn (Builder $q) => $q->whereIn('voucher_type', (array) $type));
    }

    /**
     * The joined entry rows every balance is built from — cancelled left out.
     *
     * Returns a query builder over `voucher_entries` joined to its header, with
     * the "not cancelled" rule and the branch filter already applied. The
     * columns are prefixed `ve.` and `v.`.
     *
     * A branch id is not optional by accident: a NULL-branch (shared) ledger
     * can carry entries from two properties, and a cash book that added both up
     * would be wrong in a way nobody would spot for a month.
     */
    public static function liveEntries(?int $branchId = null): \Illuminate\Database\Query\Builder
    {
        return DB::table('voucher_entries as ve')
            ->join('vouchers as v', 'v.id', '=', 've.voucher_id')
            ->where('v.is_cancelled', 0)
            ->when($branchId, fn ($q, $id) => $q->where('v.branch_id', $id));
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->voucher_type] ?? ucfirst((string) $this->voucher_type);
    }

    public function isCancelled(): bool
    {
        return (int) $this->is_cancelled === 1;
    }

    /** Written by another module (a checkout, a bill) rather than typed here. */
    public function isAuto(): bool
    {
        return (int) $this->is_auto === 1;
    }
}
