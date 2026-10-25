<?php

namespace App\Http\Middleware;

use App\Models\AdminToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin API: the mobile app sends "Authorization: Bearer <token>" from its login. */
class EnsureAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = AdminToken::findByPlain($request->bearerToken());
        if (! $token) {
            return response()->json(['message' => 'Please log in again.'], 401);
        }

        // Note when it was last used (at most once a minute, to save writes)
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
