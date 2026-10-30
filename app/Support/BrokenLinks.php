<?php

namespace App\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Free broken link checker (/broken-link-checker): reads the homepage and a few pages it links to, collects
 * every link and image, and checks each one (several at a time). Every host must resolve to a public IP
 * and the connection is pinned to it, the same protection SafeFetch uses.
 */
class BrokenLinks
{
    public const MAX_PAGES = 8;

    public const MAX_LINKS = 150;

    private const UA = 'Mozilla/5.0 (compatible; OwlvixLinkChecker/1.0; +https://owlvix.com/broken-link-checker)';

    public static function run(string $url): array
    {
        if ($c = Cache::get('links:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        $first = SafeFetch::get($url);
        if (! $first['ok']) {
            return $first;
        }
        $pages = [$first['url'] => $first];
        $more = [];
        foreach (self::extract($first['body'], $first['url']) as $l) {
            if ($l['type'] === 'link' && self::isPage($l['url'], $first['url']) && $l['url'] !== $first['url'] && count($more) < self::MAX_PAGES - 1) {
                $more[$l['url']] = true;
            }
        }
        $pages += self::fetch(array_keys($more), get: true, timeout: 15);

        $links = self::collect($pages);
        $status = [];
        foreach ($pages as $u => $p) {
            $status[$u] = array_intersect_key($p, ['code' => 1, 'location' => 1, 'error' => 1]) + ['code' => $p['status'] ?? 0];
        }
        $status += self::check(array_diff(array_keys($links), array_keys($status)));
        $report = self::analyse($url, $first['url'], array_keys(array_filter($pages, fn ($p) => $p['ok'])), $links, $status);
        Cache::put('links:'.sha1($url), $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    /** Links and images on a page: [['url', 'type' => link|image, 'text']]. */
    public static function extract(string $html, string $base): array
    {
        if (trim($html) === '') {
            return [];
        }
        $doc = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $out = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            if ($u = self::resolve($a->getAttribute('href'), $base)) {
                $text = trim(preg_replace('/\s+/', ' ', $a->textContent)) ?: trim($a->getAttribute('aria-label') ?: $a->getAttribute('title'));
                $out[] = ['url' => $u, 'type' => 'link', 'text' => mb_substr($text ?: '(image or icon link)', 0, 80)];
            }
        }
        foreach ($doc->getElementsByTagName('img') as $img) {
            if ($u = self::resolve($img->getAttribute('src'), $base)) {
                $out[] = ['url' => $u, 'type' => 'image', 'text' => mb_substr(trim($img->getAttribute('alt')) ?: '(image without alt text)', 0, 80)];
            }
        }

        return $out;
    }

    /** Absolute http(s) URL without the #fragment, or null for mailto:, tel:, javascript:, data: and empty links. */
    public static function resolve(string $href, string $base): ?string
    {
        $href = trim(html_entity_decode($href));
        // Skip non-web links, Cloudflare's helper paths (e.g. email protection) and "share this" buttons, which always redirect
        if ($href === '' || $href[0] === '#' || preg_match('#^(?!https?:)[a-z][a-z0-9+.-]*:#i', $href) || str_contains($href, '/cdn-cgi/')
            || preg_match('#(facebook\.com/sharer|(twitter|x)\.com/(intent|share)|linkedin\.com/(shareArticle|sharing)|pinterest\.[a-z.]+/pin/create|wa\.me/\?text|api\.whatsapp\.com/send|t\.me/share|reddit\.com/submit|addtoany\.com)#i', $href)) {
            return null;
        }
        if ($href[0] === '?') {
            $href = (parse_url($base, PHP_URL_PATH) ?: '/').$href;
        }
        $abs = preg_replace('/#.*$/', '', SafeFetch::absolute($href, $base));
        $p = parse_url($abs);
        if (! $p || empty($p['host'])) {
            return null;
        }
        // Tidy ./ and ../ in the path
        $segs = [];
        foreach (explode('/', $p['path'] ?? '/') as $s) {
            if ($s === '..') {
                array_pop($segs);
            } elseif ($s !== '.') {
                $segs[] = $s;
            }
        }
        $path = '/'.ltrim(implode('/', $segs), '/');

        return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '').$path.(isset($p['query']) ? '?'.$p['query'] : '');
    }

    private static function isPage(string $url, string $base): bool
    {
        $host = fn ($u) => preg_replace('/^www\./', '', (string) parse_url($u, PHP_URL_HOST));

        return $host($url) === $host($base) && ! preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|mp4|mp3|docx?|xlsx?|css|js|xml)$/i', parse_url($url, PHP_URL_PATH) ?? '');
    }

    /** Every unique link across the pages: url => [type, text, pages[]], capped at MAX_LINKS. */
    private static function collect(array $pages): array
    {
        $links = [];
        foreach ($pages as $pageUrl => $p) {
            if (! $p['ok']) {
                continue;
            }
            foreach (self::extract($p['body'], $p['url']) as $l) {
                if (! isset($links[$l['url']])) {
                    if (count($links) >= self::MAX_LINKS) {
                        continue;
                    }
                    $links[$l['url']] = ['type' => $l['type'], 'text' => $l['text'], 'pages' => []];
                }
                if (! in_array($p['url'], $links[$l['url']]['pages'], true) && count($links[$l['url']]['pages']) < 5) {
                    $links[$l['url']]['pages'][] = $p['url'];
                }
            }
        }

        return $links;
    }

    /** Status of each URL: [url => ['code' => int, 'location'?, 'error'?]]. HEAD first, then GET for anything that failed (some servers refuse HEAD). */
    public static function check(array $urls): array
    {
        $result = self::fetch($urls);
        // Errors and timeouts get one more try with a normal GET and a longer wait
        $retry = array_keys(array_filter($result, fn ($r) => $r['code'] >= 400 || ($r['timeout'] ?? false)));
        foreach (self::fetch($retry, get: true, timeout: 15) as $u => $r) {
            $result[$u] = array_diff_key($r, ['body' => 1, 'ok' => 1, 'url' => 1, 'status' => 1, 'timeout' => 1]);
        }

        return array_map(fn ($r) => array_diff_key($r, ['body' => 1, 'ok' => 1, 'url' => 1, 'status' => 1, 'timeout' => 1]), $result);
    }

    /**
     * Requests many URLs, 10 at a time, without following redirects. Each host must resolve to a public IP and the
     * connection is pinned to it. GET also returns the page (SafeFetch shape: ok, url, status, body).
     */
    private static function fetch(array $urls, bool $get = false, int $timeout = 6): array
    {
        $ips = $targets = $result = [];
        foreach ($urls as $u) {
            $p = parse_url($u);
            $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
            if (! in_array($port, [80, 443], true)) {
                continue;
            }
            if (! array_key_exists($p['host'], $ips)) {
                $ips[$p['host']] = SafeFetch::publicIp($p['host']) ?? (gethostbynamel($p['host']) ? false : null);
            }
            if ($ips[$p['host']] === null) {
                $result[$u] = ['ok' => false, 'code' => 0, 'error' => 'The domain does not exist'];
            } elseif ($ips[$p['host']]) {
                $targets[$u] = "{$p['host']}:$port:{$ips[$p['host']]}";
            } // false = private address: skipped
        }
        if (! $targets) {
            return $result;
        }
        $responses = Http::pool(fn (Pool $pool) => array_map(fn ($u) => $pool->as($u)
            ->withOptions(SafeFetch::options($targets[$u]))
            ->withHeaders(['User-Agent' => self::UA, 'Accept' => $get ? 'text/html,*/*;q=0.8' : '*/*'])
            ->timeout($timeout)->connectTimeout(5)
            ->{$get ? 'get' : 'head'}($u), array_keys($targets)), 10);

        foreach ($responses as $u => $res) {
            if (! $res instanceof \Illuminate\Http\Client\Response) {
                $timedOut = $res instanceof \Throwable && str_contains(strtolower($res->getMessage()), 'timed out');
                // A big file (video, PDF) hit the download cap: it's there, so the link works
                if ($res instanceof \Throwable && str_contains(strtolower($res->getMessage()), 'maximum file size')) {
                    $result[$u] = ['ok' => true, 'code' => 200, 'status' => 200, 'url' => $u] + ($get ? ['body' => ''] : []);

                    continue;
                }
                $result[$u] = ['ok' => false, 'code' => 0, 'timeout' => $timedOut, 'error' => $timedOut ? "Did not respond within {$timeout} seconds" : 'Could not connect'];

                continue;
            }
            $code = $res->status();
            $result[$u] = ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'status' => $code, 'url' => $u]
                + ($res->header('Location') ? ['location' => SafeFetch::absolute($res->header('Location'), $u)] : [])
                + ($get ? ['body' => (string) SafeFetch::body($res)] : []);
        }

        return $result;
    }

    public static function analyse(string $url, string $final, array $pages, array $links, array $status): array
    {
        $broken = $redirects = $blocked = [];
        $ok = 0;
        foreach ($links as $u => $l) {
            $s = $status[$u] ?? null;
            if (! $s) {
                continue; // skipped (private address or odd port)
            }
            $item = ['url' => $u, 'type' => $l['type'], 'text' => $l['text'], 'pages' => $l['pages'], 'code' => $s['code']];
            $code = $s['code'];
            if ($code >= 200 && $code < 300) {
                $ok++;
            } elseif ($code >= 300 && $code < 400) {
                $redirects[] = $item + ['location' => $s['location'] ?? ''];
            } elseif (in_array($code, [401, 403, 405, 429, 999], true)) {
                $blocked[] = $item + ['reason' => $code === 429 ? 'Too many requests' : 'The site blocks automated checks'];
            } else {
                $broken[] = $item + ['reason' => $s['error'] ?? match (true) {
                    $code === 404 => 'Page not found',
                    $code === 410 => 'Page removed',
                    $code >= 500 => 'Server error',
                    default => "Error $code",
                }];
            }
        }
        $internal = fn ($i) => preg_replace('/^www\./', '', (string) parse_url($i['url'], PHP_URL_HOST)) === preg_replace('/^www\./', '', (string) parse_url($final, PHP_URL_HOST));
        usort($broken, fn ($a, $b) => [! $internal($a), $a['type']] <=> [! $internal($b), $b['type']]);
        usort($redirects, fn ($a, $b) => ! $internal($a) <=> ! $internal($b));
        $n = count($broken);

        return [
            'url' => $url,
            'final_url' => $final,
            'pages' => $pages,
            'counts' => ['checked' => $ok + $n + count($redirects) + count($blocked), 'ok' => $ok, 'broken' => $n, 'redirects' => count($redirects), 'blocked' => count($blocked)],
            'rating' => $n === 0 ? 'good' : ($n <= 3 ? 'average' : 'poor'),
            'summary' => match (true) {
                $n === 0 => 'No broken links found. Visitors and Google can reach everything we checked.',
                $n <= 3 => 'A few broken links. Fix them so visitors don\'t hit dead ends and Google doesn\'t waste time on them.',
                default => 'Many broken links. They frustrate visitors and make your site look unmaintained to Google — fix the ones on your own site first.',
            },
            'broken' => array_map(fn ($i) => $i + ['internal' => $internal($i)], $broken),
            'redirects' => array_slice($redirects, 0, 40),
            'blocked' => array_slice($blocked, 0, 40),
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
