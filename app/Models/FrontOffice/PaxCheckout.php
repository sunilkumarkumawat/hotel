<?php

namespace App\Models\FrontOffice;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Some of the people in a room leaving before the rest.
 *
 * Two of four guests going home on Tuesday and the rest on Thursday is two
 * events, so this is a log rather than a number on the check-in.
 */
class PaxCheckout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checkout_date' => 'date'];
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
