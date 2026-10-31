<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Push notifications for apps made with the mobile app builder, through Google's Firebase Cloud Messaging. Uses the
 * owlvix-apps Firebase project's service account key (config builder.apps.firebase, a JSON file kept out of the
 * web root). Each app is registered in that project when it's built (so it gets its google-services.json), phones
 * subscribe to the app's topic, and owners send to that topic: no device IDs are stored here.
 */
class Firebase
{
    private const API = 'https://firebase.googleapis.com/v1beta1/';

    /** The service account key, or null when push notifications aren't set up. */
    public static function credentials(): ?array
    {
        $path = (string) config('builder.apps.firebase');
        if ($path === '' || ! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && ! empty($json['private_key']) && ! empty($json['client_email']) && ! empty($json['project_id']) ? $json : null;
    }

    public static function enabled(): bool
    {
        return self::credentials() !== null;
    }

    /** The FCM topic phones with this app subscribe to. */
    public static function topic(string $appId): string
    {
        return 'app_'.$appId;
    }

    /** A Google access token for the service account (signed JWT exchanged at Google), cached for 50 minutes. */
    private static function token(): string
    {
        $key = self::credentials() ?? throw new \RuntimeException('Firebase is not set up.');

        return Cache::remember('firebase-token:'.md5($key['client_email']), 3000, function () use ($key) {
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $head = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/firebase https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $key['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign("$head.$claims", $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign the Firebase token request.');
            }
            $res = Http::asForm()->timeout(15)->post($key['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "$head.$claims.".$b64($signature),
            ]);
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new \RuntimeException('Google refused the Firebase token: '.$res->status().' '.substr($res->body(), 0, 200));
            }

            return $res->json('access_token');
        });
    }

    private static function api()
    {
        return Http::withToken(self::token())->acceptJson()->timeout(20);
    }

    /**
     * The google-services.json for an Android app with this package name, registering the app in the Firebase
     * project first if it isn't there yet.
     */
    public static function androidConfig(string $package, string $name): string
    {
        $project = 'projects/'.self::credentials()['project_id'];
        $appId = null;
        $page = null;
        do {
            $res = self::api()->get(self::API.$project.'/androidApps', array_filter(['pageSize' => 100, 'pageToken' => $page]))->throw();
            foreach ($res->json('apps') ?? [] as $app) {
                if (($app['packageName'] ?? '') === $package) {
                    $appId = $app['appId'];
                }
            }
            $page = $res->json('nextPageToken');
        } while (! $appId && $page);

        if (! $appId) {
            $op = self::api()->post(self::API.$project.'/androidApps', ['packageName' => $package, 'displayName' => mb_substr($name, 0, 100)])->throw()->json();
            // Registering takes a few seconds: wait for the long-running operation
            for ($i = 0; $i < 15 && empty($op['done']); $i++) {
                usleep(1_500_000);
                $op = self::api()->get(self::API.$op['name'])->throw()->json();
            }
            if (! empty($op['error']) || empty($op['response']['appId'])) {
                throw new \RuntimeException('Firebase couldn’t register '.$package.': '.json_encode($op['error'] ?? 'timed out'));
            }
            $appId = $op['response']['appId'];
        }

        $config = self::api()->get(self::API.$project.'/androidApps/'.$appId.'/config')->throw()->json('configFileContents');
        $json = base64_decode((string) $config, true);
        if (! $json || ! str_contains($json, $package)) {
            throw new \RuntimeException('Firebase sent an unexpected config for '.$package.'.');
        }

        return $json;
    }

    /**
     * Sends a notification to everyone with the app.
     *
     * @param  array<string, string>  $data  extra values for the app (e.g. the tab to open)
     */
    public static function send(string $topic, string $title, string $body, array $data = []): void
    {
        $project = self::credentials()['project_id'];
        self::api()->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", ['message' => [
            'topic' => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => (object) $data,
            'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
        ]])->throw();
    }
}
