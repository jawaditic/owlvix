<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a page from a visitor-supplied URL without letting it reach internal addresses (SSRF):
 * http/https on ports 80/443 only, every host (also after each redirect) must resolve to public IPs,
 * the connection is pinned to the checked IP, and the body is capped in size and time.
 */
class SafeFetch
{
    private const MAX_BYTES = 3_000_000;

    private const MAX_REDIRECTS = 4;

    /**
     * @return array{ok: bool, error?: string, url?: string, status?: int, headers?: array, body?: string, ms?: int, redirects?: int}
     */
    public static function get(string $url, int $timeout = 15): array
    {
        $start = microtime(true);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parts = parse_url($url);
            $scheme = strtolower($parts['scheme'] ?? '');
            $host = strtolower($parts['host'] ?? '');
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! in_array($port, [80, 443], true)) {
                return ['ok' => false, 'error' => 'That address is not a public website.'];
            }
            $ip = self::publicIp($host);
            if (! $ip) {
                return ['ok' => false, 'error' => 'We could not find that website. Check the address and try again.'];
            }

            // Time left for this hop: the timeout covers the whole fetch, redirects included
            $left = max(1, (int) ceil($timeout - (microtime(true) - $start)));
            try {
                $res = Http::withOptions(self::options("$host:$port:$ip"))->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; OwlvixSEOChecker/1.0; +https://owlvix.com/seo-checker)',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                ])->timeout($left)->connectTimeout(min(8, $left))->get($url);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => self::errorMessage($e)];
            }

            if ($res->status() >= 300 && $res->status() < 400 && $res->header('Location')) {
                $url = self::absolute($res->header('Location'), $url);
                continue;
            }

            $body = self::body($res);
            if ($body === null) {
                return ['ok' => false, 'error' => 'That page is too large to check (over 3 MB).'];
            }

            return [
                'ok' => true, 'url' => $url, 'status' => $res->status(), 'headers' => array_change_key_case($res->headers()),
                'body' => $body, 'ms' => (int) round((microtime(true) - $start) * 1000), 'redirects' => $hop,
            ];
        }

        return ['ok' => false, 'error' => 'That address redirects too many times.'];
    }

    /**
     * Request options for a fetch pinned to one checked IP ("host:port:ip"). Requests must go through curl (never
     * Guzzle's "stream" mode, which ignores the pin and the total timeout). The download is capped in size, and the
     * body is left compressed so body() can unpack it with a size limit (no zip bombs).
     */
    public static function options(string $resolve, int $maxBytes = self::MAX_BYTES): array
    {
        return [
            'allow_redirects' => false,
            'decode_content' => false,
            'curl' => [CURLOPT_RESOLVE => [$resolve], CURLOPT_MAXFILESIZE_LARGE => $maxBytes],
        ];
    }

    /** The response body, unpacked if compressed, or null if it is bigger than $max. */
    public static function body(\Illuminate\Http\Client\Response $res, int $max = self::MAX_BYTES): ?string
    {
        $raw = $res->body();
        $encoding = strtolower(trim($res->header('Content-Encoding')));
        if ($encoding === '' || $encoding === 'identity') {
            return strlen($raw) > $max ? null : $raw;
        }
        if (! in_array($encoding, ['gzip', 'x-gzip', 'deflate'], true)) {
            return null;
        }
        $out = @zlib_decode($raw, $max);
        if ($out === false && $encoding === 'deflate') {
            $out = @gzinflate($raw, $max); // some servers send raw deflate
        }

        return $out === false ? null : $out;
    }

    public static function errorMessage(\Throwable $e): string
    {
        $m = strtolower($e->getMessage());

        return match (true) {
            str_contains($m, 'maximum file size') || str_contains($m, 'filesize') => 'That page is too large to check (over 3 MB).',
            str_contains($m, 'ssl') || str_contains($m, 'certificate') => 'That website has an SSL certificate problem, so we could not open it securely.',
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'That website did not respond in time. Check that it is online and try again.',
            default => 'We could not connect to that website. Check that it is online and try again.',
        };
    }

    /** First public IP the host resolves to, or null if it doesn't resolve or any address is private/reserved. */
    public static function publicIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null; // bare IP addresses are not accepted
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
        if (! $ips) {
            return null;
        }
        foreach ($ips as $ip) {
            // GLOBAL_RANGE also rules out carrier-grade NAT, benchmarking, documentation and other non-public ranges
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)
                || preg_match('/^(?:64:ff9b:|::ffff:|2002:)/i', $ip)) {
                return null;
            }
        }

        return $ips[0];
    }

    public static function absolute(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        if (str_starts_with($href, '?')) {
            return $origin.($b['path'] ?? '/').$href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');

        return $origin.$dir.$href;
    }
}
