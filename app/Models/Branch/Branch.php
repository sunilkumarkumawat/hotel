<?php

namespace App\Models\Branch;

use App\Models\City\City;
use App\Models\Country\Country;
use App\Models\State\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'branch_code',
        'branch_name',
        // Printed on the registration card and every bill after it.
        'legal_name',
        'gst_no',
        'sac_code',
        'logo',
        'reg_card_terms',
        'director_administrator',
        'mobile_number',
        'email',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'pin_code',
        'expert_name',
        'business_type',
        'status',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }
}
