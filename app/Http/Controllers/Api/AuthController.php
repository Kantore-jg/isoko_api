<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->with('role.permissions')
            ->where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || $user->status !== 'ACTIVE') {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $plainToken = Str::random(64);

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $data['device_name'] ?? 'API Token',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => $user->role?->permissions?->pluck('code')->values()->all() ?? [],
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role.permissions');

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json([
            'message' => 'Déconnecté avec succès.',
        ]);
    }

    private function userPayload(User $user): array
    {
        $permissions = $user->role?->permissions?->pluck('code')->values()->all() ?? [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'code' => $user->role->code,
            ] : null,
            'permissions' => $permissions,
        ];
    }
}
