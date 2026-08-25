<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiTest extends ApiTestCase
{
    public function test_login_returns_bearer_token_and_user_payload(): void
    {
        $role = $this->makeRole('ACCOUNTANT', ['dashboard.view', 'payments.manage']);
        User::query()->create([
            'role_id' => $role->id,
            'name' => 'Alexis',
            'username' => 'accountant',
            'email' => 'accountant@example.test',
            'phone' => null,
            'password' => Hash::make('password'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'accountant',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.username', 'accountant')
            ->assertJsonPath('user.role.code', 'ACCOUNTANT');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_me_requires_valid_token_and_logout_revokes_token(): void
    {
        [, $token] = $this->makeUserWithToken('ADMIN', ['dashboard.view', 'blocks.manage']);

        $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('user.role.code', 'ADMIN');

        $this->postJson('/api/auth/logout', [], $this->authHeaders($token))
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertUnauthorized();

        $this->assertSame(
            0,
            PersonalAccessToken::query()->count()
        );
    }

    public function test_protected_route_rejects_missing_token(): void
    {
        $this->getJson('/api/dashboard/summary')->assertUnauthorized();
    }
}
