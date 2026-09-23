<?php

namespace App\Support;

/**
 * Put this on a model and every add, change and delete of it is logged.
 *
 * It is deliberately not on everything. A trail that logs each housekeeping
 * tick and every notification read is one nobody scrolls through, and the
 * row that mattered is on page four hundred. It goes on what a hotel would
 * argue about: money, stays, rates, stock and who is allowed to do what.
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(function ($model) {
            Audit::on($model, 'created');
        });

        static::updated(function ($model) {
            Audit::on($model, 'updated');
        });

        static::deleted(function ($model) {
            Audit::on($model, 'deleted');
        });
    }

    /**
     * Columns this model keeps out of the log.
     *
     * Override where a column is noise (a cached total that moves on its own)
     * or private. Passwords and timestamps are dropped for every model
     * already — see App\Support\Audit.
     */
    public function auditHidden(): array
    {
        return [];
    }
}
