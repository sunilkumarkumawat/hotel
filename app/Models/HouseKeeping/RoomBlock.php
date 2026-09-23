<?php

namespace App\Models\HouseKeeping;

use App\Models\Master\Room;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room taken off sale for a stretch of nights.
 *
 * Two kinds, because they mean different things to the hotel:
 *
 * - **maintenance** — the room cannot be slept in. The tap is broken, the
 *   carpet is up. It is marked Repair in house keeping for as long as the
 *   block lasts, so nobody makes it up and puts it back on the board.
 * - **management** — the room is perfectly fine, it is simply not for sale:
 *   held for the owner, kept back for a group. House keeping goes on cleaning
 *   it as normal, so the status is left alone.
 *
 * Dates follow the same half-open rule as a stay: a block from the 10th to the
 * 12th covers the nights of the 10th and the 11th, and the room is free again
 * on the 12th. That is why every clash test is `from_date < to AND to_date >
 * from` — the same test a booking uses, so a block and a booking can never
 * disagree about whether the 12th is free.
 */
class RoomBlock extends Model
{
    use RecordsActivity;

    protected $table = 'room_blocks';

    protected $guarded = ['id'];

    public const TYPES = [
        'maintenance' => 'Maintenance',
        'management' => 'Management',
    ];

    /** What a block of this kind does to the room's house keeping status. */
    public const MARKS_ROOM_REPAIR = ['maintenance'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'blocked');
    }

    /**
     * Blocks that cover a given day.
     *
     * Written as `< tomorrow` / `>= tomorrow` rather than `<= today` because a
     * date column can come back with a 00:00:00 time attached, and
     * '2026-09-09 00:00:00' <= '2026-09-09' is false.
     */
    public function scopeCovering(Builder $query, string $date): Builder
    {
        $next = \Carbon\CarbonImmutable::parse($date)->addDay()->toDateString();

        return $query->where('from_date', '<', $next)->where('to_date', '>=', $next);
    }

    public function isMaintenance(): bool
    {
        return in_array($this->block_type, self::MARKS_ROOM_REPAIR, true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->block_type] ?? ucfirst((string) $this->block_type);
    }

    /** "10/09/2026 - 15/09/2026", the way the list shows it. */
    public function getRangeAttribute(): string
    {
        return $this->from_date->format('d/m/Y') . ' - ' . $this->to_date->format('d/m/Y');
    }
}
