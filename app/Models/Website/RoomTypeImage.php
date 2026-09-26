<?php

namespace App\Models\Website;

use App\Models\Master\RoomType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypeImage extends Model
{
    protected $table = 'room_type_images';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    /** The browser-servable URL for this photo. */
    public function getUrlAttribute(): string
    {
        return asset($this->path);
    }
}
