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

            try {
                $res = Http::withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'curl' => [CURLOPT_RESOLVE => ["$host:$port:$ip"]],
                ])->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; OwlvixSEOChecker/1.0; +https://owlvix.com/seo-checker)',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                ])->timeout($timeout)->connectTimeout(8)->get($url);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'That website did not respond in time. Check that it is online and try again.'];
            }

            if ($res->status() >= 300 && $res->status() < 400 && $res->header('Location')) {
                $url = self::absolute($res->header('Location'), $url);
                continue;
            }

            $stream = $res->toPsrResponse()->getBody();
            $body = '';
            while (! $stream->eof() && strlen($body) < self::MAX_BYTES) {
                $body .= $stream->read(65536);
            }

            return [
                'ok' => true, 'url' => $url, 'status' => $res->status(), 'headers' => array_change_key_case($res->headers()),
                'body' => $body, 'ms' => (int) round((microtime(true) - $start) * 1000), 'redirects' => $hop,
            ];
        }

        return ['ok' => false, 'error' => 'That address redirects too many times.'];
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
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');

        return $origin.$dir.$href;
    }
}
