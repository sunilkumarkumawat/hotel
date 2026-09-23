<?php

namespace App\Models\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thing that happened, once.
 *
 * A notification aimed at everybody is stored once with a null `user_id`
 * rather than once per user — a forty-person hotel would otherwise write forty
 * rows every time somebody takes a booking. The cost of that decision is that
 * "read" has to mean something slightly different for a broadcast: see
 * {@see \App\Models\Notification\NotificationRead} — there isn't one. A
 * broadcast is marked read for everybody by whoever clears it, which is the
 * behaviour a front desk actually wants: the message is for the desk, and once
 * the desk has seen it, it has been seen.
 */
class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'app_notification_id');
    }

    /** Everything this user should see in this branch: theirs, plus everyone's. */
    public function scopeVisibleTo(Builder $query, int $branchId, ?int $userId): Builder
    {
        return $query
            ->where('branch_id', $branchId)
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /** The event's settings row from config/notifications.php, if it has one. */
    public function meta(): array
    {
        return event_meta($this->event);
    }

    public function getLevelToneAttribute(): string
    {
        return match ($this->level) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            default => 'info',
        };
    }
}
