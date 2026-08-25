<?php

namespace Tests\Feature;

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
        $role = Role::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => str_replace('_', ' ', Str::title(strtolower($code))),
                'description' => null,
            ]
        );

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

        $token = $user->createToken('test', $user->resolvedPermissionCodes(), now()->addDay());

        return [$user, $token->plainTextToken];
    }

    protected function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
