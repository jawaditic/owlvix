<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3 (invisible). The page asks Google for a token when the form is sent;
 * here we ask Google whether that token looks like a person.
 * Used by the contact form ("contact") and the admin login ("login").
 */
class Recaptcha
{
    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    public static function passes(Request $request, string $action): bool
    {
        // No keys (e.g. local development): skip the check
        if (! self::enabled()) {
            return true;
        }

        $token = $request->input('g-recaptcha-response');
        if (blank($token)) {
            Log::info("reCAPTCHA blocked [{$action}]: no token sent", ['ip' => $request->ip()]);

            return false;
        }

        try {
            $result = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ])->json();
        } catch (\Throwable $e) {
            // If Google can't be reached, don't lock people out; the form's own checks still apply.
            report($e);

            return true;
        }

        $passed = ($result['success'] ?? false)
            && ($result['action'] ?? null) === $action
            && ($result['score'] ?? 0) >= config('services.recaptcha.min_score');

        // Note why it was blocked, to help tune RECAPTCHA_MIN_SCORE
        if (! $passed) {
            Log::info("reCAPTCHA blocked [{$action}]", [
                'ip' => $request->ip(),
                'score' => $result['score'] ?? null,
                'action' => $result['action'] ?? null,
                'hostname' => $result['hostname'] ?? null,
                'errors' => $result['error-codes'] ?? [],
            ]);
        }

        return $passed;
    }
}
