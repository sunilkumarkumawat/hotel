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
 * One laundry note: the linen that left the hotel on one day, for one vendor.
 *
 * The note is the document both sides sign, so its lines keep the rate they
 * went out at rather than reading the item master again later.
 */
class HkIssue extends Model
{
    protected $table = 'hk_issues';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_qty' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HkIssueItem::class, 'hk_issue_id');
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
            $w->where('issue_no', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', "%{$term}%"));
        }));
    }

    /** ISS-<branch>-0001, the way the desk reads it out. */
    public static function nextNumber(int $branchId): string
    {
        $prefix = 'ISS-' . $branchId . '-';

        $last = DB::table('hk_issues')
            ->where('branch_id', $branchId)
            ->where('issue_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('issue_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
