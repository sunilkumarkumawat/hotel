<?php

namespace App\Models\Role;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'role';

    protected $fillable = ['name', 'description', 'branch_id'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /** Role 1 is the administrator and is never editable or deletable. */
    public function isAdmin(): bool
    {
        return (int) $this->id === 1;
    }
}
