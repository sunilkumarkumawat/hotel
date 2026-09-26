<?php

namespace App\Models\HouseKeeping;

use App\Models\Master\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who changed a room's status, when, and from what.
 *
 * The reason this table exists is one conversation: a guest says the room was
 * never cleaned, the housekeeper says it was, and without a log the argument
 * has no facts in it. It is also what the housekeeping report reads.
 */
class HousekeepingLog extends Model
{
    protected $table = 'housekeeping_logs';

    protected $guarded = ['id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function housekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'housekeeper_id', 'user_id');
    }

    public function getFromLabelAttribute(): string
    {
        return Room::HOUSEKEEPING[$this->from_status] ?? ucfirst((string) $this->from_status);
    }

    public function getToLabelAttribute(): string
    {
        return Room::HOUSEKEEPING[$this->to_status] ?? ucfirst((string) $this->to_status);
    }
}
