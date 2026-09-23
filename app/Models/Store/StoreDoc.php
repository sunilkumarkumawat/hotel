<?php

namespace App\Models\Store;

use App\Models\Branch\Branch;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Support\RecordsActivity;
use App\Support\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece of store paperwork — an order, a receipt, an issue, a wastage
 * note, a stock correction.
 *
 * They share a table because they share a shape: a header with a date and a
 * party, and a list of items with quantities and rates. Six near-identical
 * pairs of tables would mean six near-identical controllers, and the sixth
 * would be the one with the bug in it.
 *
 * `draft` is the only state in which a document can be edited. `posted` means
 * it has hit the ledger and the stock has moved; from there the only way back
 * is to cancel it, which posts the reverse rather than deleting anything.
 */
class StoreDoc extends Model
{
    use RecordsActivity;

    protected $table = 'store_docs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date',
            'invoice_date' => 'date',
            'expected_on' => 'date',
            'posted_at' => 'datetime',
            'sub_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreDocItem::class, 'store_doc_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /** The outlet this document itself belongs to. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** A transfer_out's destination — the outlet it is bound for. */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** The purchase order a goods receipt — or the transfer a receipt — was raised against. */
    public function against(): BelongsTo
    {
        return $this->belongsTo(self::class, 'against_id');
    }

    /** What was received against this: a PO's goods receipts, or a transfer's receiving documents. */
    public function receipts(): HasMany
    {
        return $this->hasMany(self::class, 'against_id')->whereIn('kind', ['grn', 'transfer_in']);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /** Orders that still have something outstanding on them. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'posted', 'partial']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Has this document moved any stock? A purchase order never does. */
    public function movesStock(): bool
    {
        return (Store::DIRECTION[$this->kind] ?? 'none') !== 'none';
    }

    public function getKindLabelAttribute(): string
    {
        return Store::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function getDepartmentLabelAttribute(): string
    {
        return Store::DEPARTMENTS[$this->department] ?? ($this->department ? ucfirst($this->department) : '—');
    }

    /** Who or what the document is with — a vendor, a department, or an outlet. */
    public function getPartyAttribute(): string
    {
        if ($this->kind === 'transfer_out') {
            return $this->toBranch ? 'To ' . $this->toBranch->branch_name : '—';
        }

        if ($this->kind === 'transfer_in') {
            return $this->against?->branch ? 'From ' . $this->against->branch->branch_name : '—';
        }

        return $this->vendor?->name
            ?: ($this->department ? $this->department_label : ($this->issued_to ?: '—'));
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'warning',
            'posted' => 'success',
            'partial' => 'info',
            'closed' => 'muted',
            'cancelled' => 'danger',
            default => 'muted',
        };
    }
}
