<?php

namespace App\Models\FrontOffice;

use App\Models\Master\BillingInstruction;
use App\Models\Master\Guest;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * The bill a guest is given at checkout.
 *
 * Written once, when the desk presses Checkout. Everything on it is copied
 * from the folio at that moment rather than being recalculated on every view,
 * because a bill the guest has signed must not change if a rate is edited
 * afterwards.
 */
class Bill extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    public const STATUSES = [
        'open' => 'Open',
        'partial' => 'Part paid',
        'settled' => 'Settled',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'room_total' => 'decimal:2',
            'service_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function billingInstruction(): BelongsTo
    {
        return $this->belongsTo(BillingInstruction::class, 'billing_instruction_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * The next bill number for a branch.
     *
     * Read inside the checkout transaction so two desks settling at the same
     * moment cannot land on the same number.
     */
    public static function nextNumber(int $branchId): string
    {
        $prefix = config('pms.bill_prefix', 'BILL') . '-' . $branchId . '-';

        $last = DB::table('bills')
            ->where('branch_id', $branchId)
            ->where('bill_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('bill_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | Group bills — one family, several rooms
    |--------------------------------------------------------------------------
    | Every room still gets its own numbered bill, because that is what room
    | revenue and a tax return are reported against. The group number is what
    | lets those bills be printed as one document with one grand total, which is
    | what the family at the desk actually wants.
    */

    /** The other bills printed with this one, including this one. */
    public function group(): HasMany
    {
        return $this->hasMany(self::class, 'group_no', 'group_no')
            ->where('branch_id', $this->branch_id)
            ->where('status', '!=', 'cancelled');
    }

    public function isGrouped(): bool
    {
        return filled($this->group_no);
    }

    /** The next group number for a branch. Read inside the checkout transaction. */
    public static function nextGroupNumber(int $branchId): string
    {
        $prefix = config('pms.bill_group_prefix', 'GRP') . '-' . $branchId . '-';

        $last = DB::table('bills')
            ->where('branch_id', $branchId)
            ->where('group_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('group_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
