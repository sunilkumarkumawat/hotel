<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sidebar group. Table name is `module` (singular), as in the old system. */
class Module extends Model
{
    protected $table = 'module';

    protected $fillable = ['name', 'url', 'icon', 'sort'];

    public function submodules(): HasMany
    {
        return $this->hasMany(SubModule::class, 'module_id')->orderBy('sort');
    }
}
