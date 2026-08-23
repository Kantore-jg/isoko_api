<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeRole(string $code, array $permissionCodes = []): Role
    {
        $role = Role::query()->create([
            'name' => str_replace('_', ' ', Str::title(strtolower($code))),
            'code' => $code,
            'description' => null,
        ]);

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => $permissionCode,
                    'module' => Str::before($permissionCode, '.'),
                    'description' => null,
                ]
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role->refresh();
    }

    protected function makeUserWithToken(string $roleCode, array $permissionCodes = [], array $attributes = []): array
    {
        $role = $this->makeRole($roleCode, $permissionCodes);

        $user = User::query()->create(array_merge([
            'role_id' => $role->id,
            'name' => ucfirst($roleCode),
            'username' => strtolower($roleCode),
            'email' => strtolower($roleCode).'@example.test',
            'phone' => null,
            'password' => Hash::make('password'),
            'status' => 'ACTIVE',
        ], $attributes));

        $plainToken = Str::random(64);
        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => $permissionCodes,
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $plainToken];
    }

    protected function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
