<?php

namespace App\Models\FrontOffice;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day in the hotel's own calendar.
 *
 * A hotel's day does not end at midnight — the desk is still checking people
 * in at one in the morning and all of it belongs to yesterday's trading. So
 * the hotel keeps a date of its own, and the night auditor moves it forward
 * once the day's figures have been taken.
 *
 * While the row is `open` the day is still being traded. Once it is `closed`
 * its figures are frozen and the business date becomes the day after it.
 */
class BusinessDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'figures' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** The last night that was audited, or null if the hotel has never run one. */
    public static function lastClosed(int $branchId): ?self
    {
        return static::query()
            ->forBranch($branchId)
            ->closed()
            ->orderByDesc('business_date')
            ->first();
    }

    /**
     * A figure out of the frozen report, by dotted path.
     *
     * `figures` is stored as JSON and read back on screens that were written
     * long after the close, so a missing key has to be an empty cell rather
     * than a crash.
     */
    public function figure(string $key, mixed $default = null): mixed
    {
        return data_get($this->figures ?? [], $key, $default);
    }
}
