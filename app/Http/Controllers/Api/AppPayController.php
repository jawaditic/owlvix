<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuilderApp;
use App\Support\AppBuilder;
use App\Support\Stripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Card payments for orders in apps made with the mobile app builder. The phone sends what's in the basket (names
 * and quantities); prices come from the app's own products here, never from the phone. The customer pays on
 * Stripe's checkout page, on the owner's Stripe account; the app then asks whether it was paid.
 */
class AppPayController extends Controller
{
    public function start(Request $request, string $id): JsonResponse
    {
        $app = BuilderApp::where('status', 'live')->find($id);
        $mode = $app ? $this->mode($app) : null;
        if (! $mode) {
            return response()->json(['ok' => false, 'error' => 'off'], $app ? 409 : 404);
        }
        // every product's price, by name, in every language
        $prices = [];
        foreach (AppBuilder::allLangTabs($app) as $tab) {
            foreach ($tab['blocks'] ?? [] as $b) {
                foreach (($b['type'] ?? '') === 'products' ? ($b['items'] ?? []) : [] as $item) {
                    if (isset($item['name'], $item['price']) && is_numeric($item['price'])) {
                        $prices[$item['name']] ??= (float) $item['price'];
                    }
                }
            }
        }
        $lines = [];
        foreach (array_slice(is_array($request->input('items')) ? $request->input('items') : [], 0, 50) as $l) {
            $name = is_array($l) && is_string($l['name'] ?? null) ? $l['name'] : '';
            $qty = is_array($l) ? (int) ($l['qty'] ?? 0) : 0;
            if (! isset($prices[$name]) || $qty < 1 || $qty > 99) {
                return response()->json(['ok' => false, 'error' => 'items'], 422); // a price "on request", or not in the app
            }
            $lines[] = ['name' => $name, 'price' => $prices[$name], 'qty' => $qty];
        }
        if (! $lines || array_sum(array_map(fn ($l) => $l['price'] * $l['qty'], $lines)) <= 0) {
            return response()->json(['ok' => false, 'error' => 'items'], 422);
        }
        $back = route('builder.apps.paid', $app->id);
        try {
            $s = Stripe::checkout($app, $mode, $app->settings['shop']['pay']['currency'] ?? 'usd', $lines, $back.'?r=ok', $back.'?r=cancel');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'stripe'], 502);
        }

        return response()->json(['ok' => true, 'url' => $s['url'], 'session' => $s['id'], 'test' => $mode === 'test']);
    }

    public function status(string $id, string $session): JsonResponse
    {
        $app = BuilderApp::where('status', 'live')->find($id);
        $mode = $app ? $this->mode($app) : null;
        if (! $mode) {
            return response()->json(['ok' => false], 404);
        }
        try {
            $s = Stripe::session($app, $mode, $session);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false], 502);
        }

        return response()->json(['ok' => true, 'paid' => $s['paid'], 'amount' => $s['amount'], 'currency' => $s['currency']]);
    }

    /** 'test' or 'live' when this app takes card payments now, else null. */
    private function mode(BuilderApp $app): ?string
    {
        $pay = $app->settings['shop']['pay'] ?? [];
        $mode = ($pay['mode'] ?? 'test') === 'live' ? 'live' : 'test';

        return ! empty($pay['online']) && Stripe::available($mode) && Stripe::account($app, $mode) ? $mode : null;
    }
}
