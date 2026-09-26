<?php

namespace App\Models\Facility;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bay in the car park.
 *
 * Optional throughout: a hotel with an open forecourt and no marked bays parks
 * cars without picking one, and the ticket is still a ticket. The bay exists so
 * that a hotel which *does* mark them can be told 14 is taken.
 */
class ParkingSlot extends BaseMaster
{
    protected $table = 'parking_slots';

    public const VEHICLE_TYPES = [
        'car' => 'Car',
        'bike' => 'Two-wheeler',
        'bus' => 'Bus / Tempo',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['status' => 'integer'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(ParkingRecord::class, 'parking_slot_id');
    }

    /** The name on the screen — "Basement · P-14". */
    public function getLabelAttribute(): string
    {
        return trim(($this->zone ? $this->zone . ' · ' : '') . $this->code);
    }

    /**
     * A bay is searched by its code and its zone, not by a name.
     *
     * BaseMaster's search scope looks at `name`, which this table does not
     * have — so it is overridden rather than a `name` column being added that
     * would only ever duplicate `code`.
     *
     * The signature has to match the parent's exactly, return type included:
     * PHP treats an overriding method that drops its parent's return type as
     * incompatible and refuses to load the class at all.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $w) use ($term) {
            $w->where('code', 'like', "%{$term}%")->orWhere('zone', 'like', "%{$term}%");
        }));
    }
}
