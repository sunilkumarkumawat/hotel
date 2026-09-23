<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to get one notification to one address or number.
 *
 * This table exists so "the guest never got the mail" has an answer. A failed
 * send writes the error here and the Notification Log screen shows it; nothing
 * about a failed WhatsApp message is allowed to be invisible, and nothing about
 * it is allowed to break the booking that triggered it.
 */
class NotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'app_notification_id');
    }

    public function getToneAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'success',
            'failed' => 'danger',
            default => 'warning',
        };
    }
}
