<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            ->where(function ($query) use ($data): void {
                $query->where('username', $data['login'])
                    ->orWhere('email', $data['login']);
            })
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || $user->status !== 'ACTIVE') {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $expiresAt = now()->addDays(30);
        $token = $user->createToken(
            $data['device_name'] ?? 'API Token',
            $user->resolvedPermissionCodes(),
            $expiresAt,
        );

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
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
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Déconnecté avec succès.',
        ]);
    }

    private function userPayload(User $user): array
    {
        $permissions = $user->resolvedPermissionCodes();

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
