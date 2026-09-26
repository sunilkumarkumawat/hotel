<?php

namespace App\Models\Website;

use App\Models\Branch\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchWebsite extends Model
{
    protected $table = 'branch_website';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'offers' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Every amenity as a plain label, whichever shape the JSON was saved in.
     *
     * The admin form saves a simple list of strings. Accepting
     * ["icon" => ..., "label" => ...] rows too means a future richer editor
     * can write that shape without a migration or a view that breaks on it.
     */
    public function amenityLabels(): array
    {
        return collect($this->amenities ?? [])
            ->map(fn ($row) => is_array($row) ? ($row['label'] ?? '') : (string) $row)
            ->filter(fn ($label) => trim($label) !== '')
            ->values()
            ->all();
    }
}
