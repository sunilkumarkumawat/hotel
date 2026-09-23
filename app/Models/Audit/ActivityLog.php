<?php

namespace App\Models\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing somebody did.
 *
 * Written once and never touched again: there is no edit screen and no delete
 * button anywhere in this application for this table, because a log a manager
 * can tidy up is not evidence of anything.
 *
 * The user's name and the row's label are COPIED in rather than joined. A
 * deleted user, a cancelled bill or a renamed rate plan must not turn a year
 * of history into blank cells — which is exactly what a join would do, and
 * always on the day somebody is looking for something.
 */
class ActivityLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    /** What the action did, in one word a manager would use. */
    public const ACTIONS = [
        'created' => 'Added',
        'updated' => 'Changed',
        'deleted' => 'Deleted',
        'posted' => 'Posted',
        'cancelled' => 'Cancelled',
        'settled' => 'Settled',
        'printed' => 'Printed',
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Sign-in refused',
        'shift_opened' => 'Shift opened',
        'shift_closed' => 'Shift closed',
        'audit_closed' => 'Night audit closed',
        'audit_reopened' => 'Night audit reopened',
        'permissions_changed' => 'Permissions changed',
        'exported' => 'Exported',
    ];

    /**
     * The five things a hotel actually audits.
     *
     * A trail that cannot be narrowed is a trail nobody reads, and the useful
     * narrowing is not by table — it is "show me everything that touched
     * money", which is four tables and a handful of actions.
     */
    public const AREAS = [
        'money' => 'Money',
        'stay' => 'Guests and stays',
        'rates' => 'Rates and rules',
        'stock' => 'Store and stock',
        'access' => 'Users and access',
        'system' => 'System',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        // A row with no branch is a system event; it belongs to everyone.
        return $query->where(function (Builder $q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', (string) $this->action));
    }

    public function getAreaLabelAttribute(): string
    {
        return self::AREAS[$this->area] ?? 'Other';
    }

    public function getToneAttribute(): string
    {
        return match ($this->action) {
            'deleted', 'cancelled', 'login_failed' => 'danger',
            'created', 'posted', 'settled', 'login' => 'success',
            'updated', 'permissions_changed' => 'warning',
            default => 'muted',
        };
    }

    /** What sort of thing it happened to — the class name, without its path. */
    public function getSubjectNameAttribute(): string
    {
        $type = (string) $this->subject_type;

        return $type === '' ? '—' : class_basename($type);
    }

    /**
     * The changed columns, ready to show.
     *
     * Values are rendered here rather than in the view because a null has to
     * read as "empty" and a boolean as Yes/No — a blank cell in an audit trail
     * looks like the log failed rather than like the field was cleared.
     */
    public function changeList(): array
    {
        $out = [];

        foreach ($this->changes ?? [] as $column => $pair) {
            $out[] = [
                'column' => ucfirst(str_replace('_', ' ', (string) $column)),
                'from' => self::render($pair['from'] ?? null),
                'to' => self::render($pair['to'] ?? null),
            ];
        }

        return $out;
    }

    private static function render(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        return (string) $value;
    }
}
