<?php

namespace App\Http\Middleware;

use App\Models\BuilderAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Landing page builder API: the X-Builder-Token header must belong to an account. */
class AuthenticateBuilder
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = BuilderAccount::forToken($request->header('X-Builder-Token'));
        if (! $account) {
            return response()->json(['ok' => false, 'error' => 'Please sign in again.'], 401);
        }
        $request->attributes->set('builder_account', $account);

        return $next($request);
    }
}
