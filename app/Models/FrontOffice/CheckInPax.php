<?php

namespace App\Models\FrontOffice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The other people sharing a checked-in room — the register the police
 * verification form is filled in from.
 */
class CheckInPax extends Model
{
    protected $table = 'check_in_pax';

    protected $guarded = ['id'];

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ];

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }
}
