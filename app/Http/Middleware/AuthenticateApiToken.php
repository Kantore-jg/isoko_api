<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Bearer ')) {
            return response()->json(['message' => 'Token API manquant.'], 401);
        }

        $plainToken = trim(substr($authorization, 7));
        if ($plainToken === '') {
            return response()->json(['message' => 'Token API manquant.'], 401);
        }

        $token = ApiToken::query()
            ->with('user.role.permissions')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (
            ! $token ||
            ! $token->user ||
            $token->user->status !== 'ACTIVE' ||
            $token->revoked_at !== null ||
            ($token->expires_at !== null && $token->expires_at->isPast())
        ) {
            return response()->json(['message' => 'Token API invalide ou expiré.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
