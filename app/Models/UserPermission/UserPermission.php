<?php

namespace App\Models\UserPermission;

use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, branch).
 *
 *   module_id     "1,2,6"                       — comma separated module ids
 *   submodule_id  "1,2,3,18,19"                 — comma separated submodule ids
 *   permissions   {"1":{"view":1,"add":0,...}}  — what they may do in each
 */
class UserPermission extends Model
{
    use RecordsActivity;

    protected $table = 'user_permission';

    protected $fillable = [
        'user_id',
        'branch_id',
        'module_id',
        'submodule_id',
        'permissions',
        'view',
        'add',
        'edit',
        'delete',
    ];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
