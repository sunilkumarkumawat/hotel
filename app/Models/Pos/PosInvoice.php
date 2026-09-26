<?php

namespace App\Models\Pos;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** What the guest pays. Separate from the order — see the migration. */
class PosInvoice extends Model
{
    use RecordsActivity;

    protected $table = 'pos_invoices';

    protected $guarded = ['id'];

    public const STATUSES = [
        'open' => 'Unsettled',
        'settled' => 'Settled',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'invoice_at' => 'datetime',
            'settled_at' => 'datetime',
            'sub_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'folio_amount' => 'decimal:2',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'pos_invoice_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Bills printed but not yet paid for. */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /** What is still owed on this bill. */
    public function balance(): float
    {
        return round((float) $this->net_amount - (float) $this->paid_amount, 2);
    }

    /**
     * The next invoice number for an outlet.
     *
     * The series comes off the outlet, because two tills in one hotel are
     * normally required to number their bills separately — the restaurant runs
     * R/25-26/ and the bar B/25-26/. An outlet with no series set falls back to
     * a branch-wide one so a bill can always be raised.
     *
     * Read inside the settle transaction: two cashiers pressing Print at the
     * same instant must not get the same number.
     */
    public static function nextNumber(int $branchId, ?Outlet $outlet = null): string
    {
        $prefix = trim((string) ($outlet->bill_series ?? '')) ?: 'INV-' . $branchId . '-';

        $last = static::query()
            ->where('branch_id', $branchId)
            ->where('invoice_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $next = $last
            ? ((int) substr($last, strlen($prefix))) + 1
            : max(1, (int) ($outlet->bill_start_no ?? 1));

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Columns that move on their own.
     *
     * These are written by the code that keeps them up to date rather than
     * by a person, so logging them would bury the changes somebody actually
     * made under a drift of housekeeping.
     */
    public function auditHidden(): array
    {
        return ['print_count'];
    }
}
