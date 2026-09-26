<?php

namespace App\Models\Website;

use App\Models\Master\RoomType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypeWebsite extends Model
{
    protected $table = 'room_type_website';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }
}
