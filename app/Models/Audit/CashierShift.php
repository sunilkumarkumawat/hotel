<?php

namespace App\Models\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cashier, one drawer, one stretch of time.
 *
 * The row is deliberately thin. It knows who opened it, when, what was in the
 * drawer to start with, and — once it is closed — what they counted and what
 * the books said they should have. Everything else is worked out from the
 * payment rows themselves by App\Support\Shifts, so a shift report and the
 * day book can never tell two different stories.
 */
class CashierShift extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'declared' => 'array',
            'expected' => 'array',
            'opening_float' => 'decimal:2',
            'cash_expected' => 'decimal:2',
            'cash_counted' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Who this shift belongs to, even after the user row has gone. */
    public function getCashierAttribute(): string
    {
        return $this->user?->name ?: ('User #' . $this->user_id);
    }

    /**
     * Short is the one that matters.
     *
     * Over is worth looking at — it usually means a payment was never entered
     * — but short is money that was in the drawer and is not any more, so the
     * two are never shown as the same kind of problem.
     */
    public function isShort(): bool
    {
        return $this->status === 'closed' && (float) $this->variance < -0.005;
    }

    public function isOver(): bool
    {
        return $this->status === 'closed' && (float) $this->variance > 0.005;
    }

    public function getVarianceToneAttribute(): string
    {
        if ($this->isOpen()) {
            return 'info';
        }

        return $this->isShort() ? 'danger' : ($this->isOver() ? 'warning' : 'success');
    }

    /** How long the drawer has been open, in plain words. */
    public function getLengthAttribute(): string
    {
        $end = $this->closed_at ?: now();
        $minutes = max(0, $this->opened_at->diffInMinutes($end));

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? $hours . 'h ' . $rest . 'm' : $rest . 'm';
    }

    /** A figure out of the frozen close, by dotted path. */
    public function figure(string $key, mixed $default = null): mixed
    {
        return data_get($this->expected ?? [], $key, $default);
    }
}
