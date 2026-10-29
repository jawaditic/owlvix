<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * IndexNow: tells Bing (and Yandex, Seznam, Naver) right away that a page changed, instead of waiting
 * for their next crawl. Google doesn't use it. Off unless INDEXNOW_KEY is set in .env.
 * The key is public by design; search engines check it at https://owlvix.com/<key>.txt (routes/web.php).
 */
class IndexNow
{
    public static function key(): ?string
    {
        $key = config('services.indexnow.key');

        return is_string($key) && preg_match('/^[a-zA-Z0-9-]{8,128}$/', $key) ? $key : null;
    }

    /**
     * The homepage changed (portfolio, reviews or rating). Sent after the response, at most once per
     * 10 minutes, so editing several things in a row sends one ping.
     */
    public static function homepageChanged(): void
    {
        if (! self::key() || ! Cache::add('indexnow:home', true, now()->addMinutes(10))) {
            return;
        }
        app()->terminating(fn () => self::submit(self::pages()));
    }

    /** Pages that show the portfolio, reviews or rating: the homepage and the services page. */
    public static function pages(): array
    {
        return [self::homepage(), self::homepage().'/services'];
    }

    /** The live site's address from APP_URL (https://owlvix.com), the same from web requests and the command line. */
    public static function homepage(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** Send URLs now. Returns the HTTP status (200/202 = accepted), or null if off or unreachable. */
    public static function submit(array $urls): ?int
    {
        if (! $key = self::key()) {
            return null;
        }

        try {
            return Http::timeout(5)->acceptJson()->post('https://api.indexnow.org/indexnow', [
                'host' => parse_url(self::homepage(), PHP_URL_HOST),
                'key' => $key,
                'keyLocation' => self::homepage().'/'.$key.'.txt',
                'urlList' => array_values($urls),
            ])->status();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
