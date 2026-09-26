<?php

namespace App\Models\Master;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared behaviour for every master list.
 *
 * Masters belong to a branch, so `forBranch()` is what keeps one property from
 * seeing another's rooms and rates. A NULL branch_id means "shared by every
 * branch" — handy for things like tax slabs.
 */
abstract class BaseMaster extends Model
{
    protected $guarded = ['id'];

    /** Rows this branch may use: its own, plus the shared ones. */
    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= Helper::getActiveBranchId();

        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }
}
