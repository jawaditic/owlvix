<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuilderApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks a staff PIN typed on a customer's phone for a loyalty card (mobile app builder). The PIN's hash never leaves
 * the server, and wrong tries are limited per card (from every phone together), so a short PIN can't be guessed.
 */
class AppLoyaltyController extends Controller
{
    private const WRONG_PER_HOUR = 12; // per card, all phones together

    public function check(Request $request, string $id): JsonResponse
    {
        $app = BuilderApp::where('status', 'live')->find($id);
        $card = $request->input('card');
        $pin = $request->input('pin');
        if (! $app || ! is_string($card) || ! preg_match('/^[a-z0-9]{8,16}$/', $card) || ! is_string($pin) || ! preg_match('/^\d{4,8}$/', $pin)) {
            return response()->json(['ok' => false, 'error' => 'bad'], $app ? 422 : 404);
        }
        $hash = collect(\App\Support\AppBuilder::allLangTabs($app))->flatMap(fn ($t) => $t['blocks'] ?? [])
            ->first(fn ($b) => ($b['type'] ?? '') === 'loyalty' && ($b['card'] ?? '') === $card)['pinHash'] ?? null;
        if (! is_string($hash)) {
            return response()->json(['ok' => false, 'error' => 'bad'], 404);
        }
        $key = "app-loyalty:{$app->id}:{$card}";
        if (RateLimiter::tooManyAttempts($key, self::WRONG_PER_HOUR)) {
            return response()->json(['ok' => false, 'error' => 'locked'], 429);
        }
        if (! hash_equals($hash, hash('sha256', $card.':'.$pin))) {
            RateLimiter::hit($key, 3600);

            return response()->json(['ok' => false, 'error' => 'wrong'], 422);
        }

        return response()->json(['ok' => true]);
    }
}
