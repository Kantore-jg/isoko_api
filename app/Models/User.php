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

    private const FALLBACK_ROLE_PERMISSIONS = [
        'SUPER_ADMIN' => [
            'dashboard.view',
            'blocks.manage',
            'places.manage',
            'merchants.manage',
            'banks.manage',
            'assignments.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'imports.manage',
            'exports.manage',
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'settings.manage',
            'reports.view',
        ],
        'ADMIN' => [
            'dashboard.view',
            'blocks.manage',
            'places.manage',
            'merchants.manage',
            'banks.manage',
            'assignments.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'imports.manage',
            'exports.manage',
            'users.manage',
            'settings.manage',
            'reports.view',
        ],
        'ACCOUNTANT' => [
            'dashboard.view',
            'banks.manage',
            'rents.manage',
            'payments.manage',
            'receipts.manage',
            'exports.manage',
            'reports.view',
        ],
    ];

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
        return in_array($permissionCode, $this->resolvedPermissionCodes(), true);
    }

    public function hasAnyPermission(array $permissionCodes): bool
    {
        $resolved = $this->resolvedPermissionCodes();

        foreach ($permissionCodes as $permissionCode) {
            if (in_array($permissionCode, $resolved, true)) {
                return true;
            }
        }

        return false;
    }

    public function resolvedPermissionCodes(): array
    {
        $roleCode = $this->role?->code ?? '';
        $fallbackPermissionCodes = self::FALLBACK_ROLE_PERMISSIONS[$roleCode] ?? [];
        $databasePermissionCodes = $this->role?->permissions?->pluck('code')->values()->all() ?? [];

        return array_values(array_unique(array_filter(array_merge($fallbackPermissionCodes, $databasePermissionCodes))));
    }
}
