<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A section of an outlet's floor — Non AC, Terrace, Pool Side.
 *
 * `kind` is what the seats inside are called. A restaurant has tables, a resort
 * has villas, a service apartment has flats; the seating screen is the same
 * screen and only the word changes, so the word is data rather than three
 * near-identical screens.
 */
class PosTableGroup extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_table_groups';

    public const KINDS = [
        'apartment' => 'Apartment',
        'room' => 'Room',
        'table' => 'Table',
        'villa' => 'Villa',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(PosTable::class, 'pos_table_group_id')->orderBy('sort')->orderBy('name');
    }

    /** "Table" / "Villa" — what the button under this group should say. */
    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Table';
    }
}
