<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** One KOT run: what the kitchen was asked for, and when it closed. */
class PosOrder extends Model
{
    protected $table = 'pos_orders';

    protected $guarded = ['id'];

    public const TYPES = [
        'dine_in' => 'Dine-In',
        'room_service' => 'Room Service',
        'delivery' => 'Delivery',
        'take_away' => 'Take Away',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'billed' => 'Billed',
        'settled' => 'Settled',
        'cancelled' => 'Cancelled',
    ];

    /** The statuses that still hold a table. */
    public const HOLDS_TABLE = ['open', 'billed'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_complimentary' => 'boolean',
            'sub_total' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'round_off' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'pos_table_id');
    }

    public function steward(): BelongsTo
    {
        return $this->belongsTo(PosSteward::class, 'pos_steward_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(PosRatePlan::class, 'pos_rate_plan_id');
    }

    public function ncType(): BelongsTo
    {
        return $this->belongsTo(PosNcType::class, 'nc_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'pos_order_id')->orderBy('sort')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(PosInvoice::class, 'pos_order_id')->where('status', '!=', 'cancelled');
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /** Orders that actually count as business — a cancelled one does not. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Orders still on the floor: being taken, or waiting to be paid for. */
    public function scopeOnFloor(Builder $query): Builder
    {
        return $query->whereIn('status', self::HOLDS_TABLE);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    /** "Table 7" / "Room 203" / "Take Away" — what to call this order on a list. */
    public function getWhereLabelAttribute(): string
    {
        if ($this->table_no) {
            return ($this->order_type === 'room_service' ? 'Room ' : '') . $this->table_no;
        }

        return self::TYPES[$this->order_type] ?? 'Order';
    }

    /** How long this order has been open, in whole seconds. */
    public function openSeconds(): int
    {
        $end = $this->closed_at ?: now();

        return max(0, $this->opened_at ? $this->opened_at->diffInSeconds($end) : 0);
    }

    /**
     * The next order number for a branch.
     *
     * Read inside the transaction that creates the order, so two tills opening
     * a table at the same moment cannot land on the same number.
     */
    public static function nextNumber(int $branchId): string
    {
        $prefix = config('pms.pos_order_prefix', 'ORD') . '-' . $branchId . '-';

        $last = DB::table('pos_orders')
            ->where('branch_id', $branchId)
            ->where('order_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
