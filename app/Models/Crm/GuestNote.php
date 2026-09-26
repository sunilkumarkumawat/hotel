<?php

namespace App\Models\Crm;

use App\Models\FrontOffice\CheckIn;
use App\Models\Master\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the desk learned about a guest.
 *
 * "Asks for a high floor." "Complained about the lift, comped a breakfast."
 * The kind that matters most is `preference`, because it is the one that can
 * be acted on before the guest asks a second time.
 *
 * `pinned` is what puts a note in front of whoever checks them in next. Only a
 * few notes should ever be pinned: history that shouts is history nobody reads.
 */
class GuestNote extends Model
{
    protected $table = 'guest_notes';

    protected $guarded = ['id'];

    public const KINDS = [
        'preference' => 'Preference',
        'complaint' => 'Complaint',
        'compliment' => 'Compliment',
        'note' => 'Note',
    ];

    /** The tone each kind is drawn in. */
    public const TONES = [
        'preference' => 'info',
        'complaint' => 'danger',
        'compliment' => 'success',
        'note' => 'muted',
    ];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('pinned', true);
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? 'Note';
    }

    public function getToneAttribute(): string
    {
        return self::TONES[$this->kind] ?? 'muted';
    }
}
