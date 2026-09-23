<?php

namespace App\Models\HouseKeeping;

use App\Models\Master\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One receipt note: the linen that came back from one vendor on one day.
 *
 * A receipt is not tied to a single issue on purpose. A laundry that has four
 * notes outstanding sends back one van with a mix of all four, and making the
 * clerk split that van across four documents is how counts stop matching.
 * The receipt settles the vendor's running balance per item instead.
 */
class HkReceipt extends Model
{
    protected $table = 'hk_receipts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'receive_date' => 'date',
            'total_qty' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HkReceiptItem::class, 'hk_receipt_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
            $w->where('receipt_no', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', "%{$term}%"));
        }));
    }

    /** REC-<branch>-0001. */
    public static function nextNumber(int $branchId): string
    {
        $prefix = 'REC-' . $branchId . '-';

        $last = DB::table('hk_receipts')
            ->where('branch_id', $branchId)
            ->where('receipt_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('receipt_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
