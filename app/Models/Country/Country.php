<?php

namespace App\Models\Country;
use App\Models\State\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = "countries";
    use HasFactory;

    protected $fillable = ['name'];

    public function states()
    {
        return $this->hasMany(State::class);
    }
}
