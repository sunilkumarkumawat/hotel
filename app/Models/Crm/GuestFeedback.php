<?php

namespace App\Models\Crm;

use App\Models\FrontOffice\CheckIn;
use App\Models\Master\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a guest said after they left.
 *
 * The row is made when the link is SENT, not when the guest answers, so "we
 * asked and they did not reply" is a fact the hotel holds rather than a
 * silence it has to interpret. `answered_at` is what separates the two.
 *
 * A low score is a job, not a statistic — `handled_at` is what empties the
 * inbox, and it is only set by somebody writing down what they did about it.
 */
class GuestFeedback extends Model
{
    protected $table = 'guest_feedback';

    protected $guarded = ['id'];

    /** The five things asked about, beyond the overall score. */
    public const AREAS = [
        'room' => 'The room',
        'cleanliness' => 'Cleanliness',
        'staff' => 'The staff',
        'food' => 'Food',
        'value' => 'Value for money',
    ];

    /** At or below this, somebody rings the guest. */
    public const DETRACTOR_AT = 3;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'answered_at' => 'datetime',
            'handled_at' => 'datetime',
            'would_return' => 'boolean',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeAnswered(Builder $query): Builder
    {
        return $query->whereNotNull('answered_at');
    }

    /** Low scores nobody has dealt with yet — the only list that is a to-do. */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereNotNull('answered_at')
            ->where('overall', '<=', self::DETRACTOR_AT)
            ->whereNull('handled_at');
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    public function isDetractor(): bool
    {
        return $this->overall !== null && (int) $this->overall <= self::DETRACTOR_AT;
    }

    public function needsAttention(): bool
    {
        return $this->isDetractor() && ! $this->handled_at;
    }

    /**
     * The tone a score is drawn in.
     *
     * Four is not "good" — it is the score of a guest who found something
     * wrong and did not say what. Only five is green.
     */
    public function toneFor(?int $score): string
    {
        return match (true) {
            $score === null => 'muted',
            $score >= 5 => 'success',
            $score === 4 => 'info',
            $score === 3 => 'warning',
            default => 'danger',
        };
    }

    /** The average of whatever they did answer, ignoring what they skipped. */
    public function getAverageAttribute(): ?float
    {
        $scores = collect(array_keys(self::AREAS))
            ->map(fn (string $area) => $this->{$area})
            ->filter(fn ($v) => $v !== null);

        return $scores->isEmpty() ? null : round($scores->sum() / $scores->count(), 1);
    }
}
