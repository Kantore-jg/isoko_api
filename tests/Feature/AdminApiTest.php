<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;

class AdminApiTest extends ApiTestCase
{
    public function test_roles_support_crud_and_permission_sync(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['roles.manage', 'permissions.manage']);

        $permission = Permission::query()->create([
            'code' => 'settings.manage',
            'name' => 'Gérer les paramètres',
            'module' => 'administration',
        ]);

        $created = $this->postJson('/api/roles', [
            'code' => 'SUPERVISOR',
            'name' => 'Superviseur',
            'description' => 'Rôle de supervision',
            'permission_ids' => [$permission->id],
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('roles', [
            'id' => $created['id'],
            'code' => 'SUPERVISOR',
        ]);

        $this->getJson("/api/roles/{$created['id']}", $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.permissions.0.code', 'settings.manage');

        $this->patchJson("/api/roles/{$created['id']}", [
            'name' => 'Superviseur Principal',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Superviseur Principal');

        $this->deleteJson("/api/roles/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_permissions_support_crud(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['permissions.manage']);

        $created = $this->postJson('/api/permissions', [
            'code' => 'reports.export',
            'name' => 'Exporter les rapports',
            'module' => 'reports',
            'description' => 'Permission de test',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('permissions', [
            'id' => $created['id'],
            'code' => 'reports.export',
        ]);

        $this->patchJson("/api/permissions/{$created['id']}", [
            'name' => 'Exporter les états',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Exporter les états');

        $this->deleteJson("/api/permissions/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_users_support_crud(): void
    {
        $role = Role::query()->create([
            'code' => 'CLERK',
            'name' => 'Guichetier',
        ]);

        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['users.manage']);

        $created = $this->postJson('/api/users', [
            'role_id' => $role->id,
            'name' => 'Mireille',
            'username' => 'mireille',
            'email' => 'mireille@example.test',
            'phone' => '+257 79 000 111',
            'password' => 'password123',
            'status' => 'ACTIVE',
        ], $this->authHeaders($token))
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('users', [
            'id' => $created['id'],
            'username' => 'mireille',
        ]);

        $this->patchJson("/api/users/{$created['id']}", [
            'name' => 'Mireille Mise à Jour',
            'status' => 'INACTIVE',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Mireille Mise à Jour')
            ->assertJsonPath('data.status', 'INACTIVE');

        $this->deleteJson("/api/users/{$created['id']}", [], $this->authHeaders($token))
            ->assertOk();
    }

    public function test_settings_can_be_read_and_updated(): void
    {
        [, $token] = $this->makeUserWithToken('SUPER_ADMIN', ['settings.manage']);

        Market::query()->create([
            'code' => 'MKT-BJM-001',
            'name' => 'Marché Central de Bujumbura',
            'status' => 'ACTIVE',
        ]);

        SystemSetting::query()->create([
            'key' => 'receipt_prefix',
            'value' => 'REC',
            'type' => 'string',
            'description' => 'Préfixe des reçus',
        ]);

        $this->getJson('/api/settings', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('market.code', 'MKT-BJM-001');

        $this->putJson('/api/settings', [
            'market' => [
                'name' => 'Marché Principal de Bujumbura',
            ],
            'settings' => [
                [
                    'key' => 'receipt_prefix',
                    'value' => 'RCP',
                    'type' => 'string',
                    'description' => 'Préfixe reçu mis à jour',
                ],
            ],
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.market.name', 'Marché Principal de Bujumbura')
            ->assertJsonPath('data.settings.0.value', 'RCP');
    }

    public function test_users_route_is_forbidden_without_permission(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['dashboard.view']);

        $this->getJson('/api/users', $this->authHeaders($token))
            ->assertForbidden();
    }
}
