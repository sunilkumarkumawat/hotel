<?php

namespace App\Models\HouseKeeping;

use App\Models\Master\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A maintenance job card: what is broken, where, who is on it, by when.
 *
 * The category list lives in `config('pms.work_order_categories')` rather than
 * in a table, because it is a list every hotel edits once and then never
 * touches — and a config file needs no migration and no permission to change.
 *
 * `room_block_id` is the join to House Keeping → Room Blocked. A job that took
 * the room off sale remembers the block it made, so closing the job can put the
 * room back without the supervisor having to find the block by hand.
 */
class WorkOrder extends Model
{
    protected $table = 'work_orders';

    protected $guarded = ['id'];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that mean nobody is working on this any more. */
    public const CLOSED = ['done', 'cancelled'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'due_date' => 'date',
            'completed_on' => 'date',
        ];
    }

    /** What a job can be about. Edited in config/pms.php, not in a table. */
    public static function categories(): array
    {
        return config('pms.work_order_categories', ['other' => 'Other']);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(RoomBlock::class, 'room_block_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
            $w->where('order_no', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        }));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    /**
     * Past its deadline and still not done.
     *
     * The list paints these red, because a job card nobody looks at is the
     * whole reason a lift stays broken for a fortnight.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && ! $this->isClosed() && $this->due_date->isBefore(today());
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categories()[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** WO-<branch>-0001. */
    public static function nextNumber(int $branchId): string
    {
        $prefix = config('pms.work_order_prefix', 'WO') . '-' . $branchId . '-';

        $last = DB::table('work_orders')
            ->where('branch_id', $branchId)
            ->where('order_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
