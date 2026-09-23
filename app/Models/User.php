<?php

namespace App\Models;

use App\Models\Branch\Branch;
use App\Models\Role\Role;
use App\Models\UserPermission\UserPermission;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, RecordsActivity;

    /*
     * `users.deleted_at` exists so the table matches the original database,
     * but SoftDeletes is deliberately NOT used: `username` is unique, and a
     * soft-deleted row would keep that username locked forever. Deleting a
     * user removes the row and its `user_permission` rows in one transaction
     * (see UserController::destroy). Add `use SoftDeletes;` here if you would
     * rather keep the history — then make `username` unique per deleted_at.
     */

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name',
        'username',
        'mobile',
        // Where notifications reach them, and whether they want them.
        'email',
        'whatsapp_no',
        'notify_web',
        'notify_mail',
        'role_id',
        'branch_id',
        'password',
        'gender',
        'dob',
        'image',
        'pincode',
        'city',
        'state',
        'country',
        'address',
        'status',
        'is_verified',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'user_id', 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return (int) $this->role_id === 1;
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function getRoleNameAttribute(): string
    {
        return $this->role?->name ?? 'No role';
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name ?: $this->username)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::substr($part, 0, 1))
            ->implode('');
    }
}
