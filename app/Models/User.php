<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->role?->code === $roleCode;
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->role?->permissions()->where('code', $permissionCode)->exists() ?? false;
    }

    public function hasAnyPermission(array $permissionCodes): bool
    {
        return $this->role?->permissions()->whereIn('code', $permissionCodes)->exists() ?? false;
    }
}
