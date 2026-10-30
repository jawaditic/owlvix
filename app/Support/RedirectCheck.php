<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Free redirect & HTTP header checker (/redirect-checker): follows a URL one step at a time and shows each
 * status code, time and the final headers. Also checks that http/https and www/non-www all end up at one address.
 * Same protection as SafeFetch: public IPs only, connection pinned to the checked IP, ports 80/443.
 */
class RedirectCheck
{
    public const MAX_HOPS = 10;

    /** Hops already fetched during this check, so the 4 address versions don't load the same page again. */
    private static array $seen = [];

    public static function run(string $url): array
    {
        if ($c = Cache::get('redirects:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        self::$seen = [];
        $chain = self::follow($url);
        if (! $chain[0]['code'] && ! empty($chain[0]['error']) && count($chain) === 1) {
            return ['ok' => false, 'error' => $chain[0]['error']];
        }
        $p = parse_url($url);
        $bare = preg_replace('/^www\./', '', $p['host']);
        $path = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
        $variants = [];
        // www / non-www only matters for the main domain (example.com, example.co.uk); blog.example.com just gets http/https
        $labels = explode('.', $bare);
        $root = count($labels) <= 2 || (count($labels) === 3 && strlen($labels[1]) <= 3 && strlen($labels[2]) === 2);
        $hosts = $root ? [$bare, "www.$bare"] : [$p['host']];
        $versions = [];
        foreach ($hosts as $h) {
            $versions[] = "http://$h";
            $versions[] = "https://$h";
        }
        foreach ($versions as $v) {
            $c = $v.$path === $url ? $chain : self::follow($v.$path);
            $variants[] = ['url' => $v.$path, 'final' => end($c)['url'], 'code' => end($c)['code'], 'hops' => count($c) - 1, 'error' => end($c)['error'] ?? null];
        }
        $report = self::analyse($url, $chain, $variants);
        Cache::put('redirects:'.sha1($url), $report, now()->addMinutes(30));

        return ['ok' => true, 'report' => $report];
    }

    /** Each step: url, code, ms, location?, headers (final step), error? */
    public static function follow(string $url): array
    {
        $chain = [];
        $seen = [];
        for ($i = 0; $i <= self::MAX_HOPS; $i++) {
            $step = self::$seen[$url] ??= self::hop($url);
            $loop = isset($seen[$url]);
            $seen[$url] = true;
            $chain[] = $step + ($loop ? ['loop' => true] : []);
            if ($loop || empty($step['location']) || $step['code'] < 300 || $step['code'] >= 400) {
                break;
            }
            $url = $step['location'];
        }

        return $chain;
    }

    private static function hop(string $url): array
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($scheme, ['http', 'https'], true) || ! $host || ! in_array($port, [80, 443], true)) {
            return ['url' => $url, 'code' => 0, 'ms' => 0, 'error' => 'Not a public web address'];
        }
        $ip = SafeFetch::publicIp($host);
        if (! $ip) {
            return ['url' => $url, 'code' => 0, 'ms' => 0, 'error' => 'This address does not exist (no DNS record)'];
        }
        $start = microtime(true);
        try {
            $res = Http::withOptions(SafeFetch::options("$host:$port:$ip"))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; OwlvixRedirectChecker/1.0; +https://owlvix.com/redirect-checker)', 'Accept' => 'text/html,*/*;q=0.8'])
                ->timeout(10)->connectTimeout(6)->get($url);
        } catch (\Throwable $e) {
            return ['url' => $url, 'code' => 0, 'ms' => (int) round((microtime(true) - $start) * 1000),
                'error' => str_contains(strtolower($e->getMessage()), 'ssl') || str_contains(strtolower($e->getMessage()), 'certificate') ? 'SSL certificate problem' : 'No response'];
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        $headers = [];
        foreach ($res->headers() as $k => $v) {
            $headers[strtolower($k)] = implode(', ', $v);
        }

        return ['url' => $url, 'code' => $res->status(), 'ms' => $ms, 'headers' => $headers]
            + ($res->header('Location') ? ['location' => SafeFetch::absolute($res->header('Location'), $url)] : []);
    }

    public static function analyse(string $url, array $chain, array $variants): array
    {
        $checks = [];
        $add = function (string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = ['cat' => 'Redirects', 'title' => $title, 'status' => $status, 'found' => $found, 'tip' => $tip];
        };
        $last = end($chain);
        $hops = count($chain) - 1;
        $loop = ! empty($last['loop']);

        if ($loop) {
            $add('Redirect loop', 'fail', 'This address redirects in a circle and never loads.', 'Check your .htaccess / server rules and any redirect plugin — two rules are sending visitors back and forth.');
        } elseif ($hops > self::MAX_HOPS - 1) {
            $add('Redirect chain', 'fail', "More than $hops redirects — browsers give up.", 'Point the first address straight to the final page.');
        } elseif ($hops > 1) {
            $add('Redirect chain', 'warn', "$hops redirects in a row before the page loads.", 'Redirect straight to the final address in one step — each extra hop slows visitors and wastes Google\'s crawl time.');
        } else {
            $add('Redirect chain', 'pass', $hops ? 'One redirect, then the page loads.' : 'No redirects — the page loads straight away.');
        }

        if ($last['code'] >= 200 && $last['code'] < 300) {
            $add('Final page', 'pass', "Ends on a working page ({$last['code']}).");
        } elseif (! $loop) {
            $add('Final page', 'fail', $last['code'] ? "Ends with error {$last['code']}." : 'Ends with: '.($last['error'] ?? 'no response').'.', 'Fix the final address or redirect to a page that exists.');
        }

        $temp = array_filter(array_slice($chain, 0, -1), fn ($s) => in_array($s['code'], [302, 303, 307], true));
        if ($temp) {
            $add('Redirect type', 'warn', count($temp).' temporary redirect(s) (302/307).', 'If the move is permanent (like http → https), use 301 or 308 so Google passes the ranking to the new address.');
        } elseif ($hops) {
            $add('Redirect type', 'pass', 'Redirects are permanent (301/308).');
        }

        $finals = array_unique(array_map(fn ($v) => rtrim($v['final'], '/'), array_filter($variants, fn ($v) => $v['code'] >= 200 && $v['code'] < 300)));
        $dead = array_filter($variants, fn ($v) => $v['code'] < 200 || $v['code'] >= 300);
        if (count($finals) > 1) {
            $add('One main address', 'fail', 'Different versions of your address end on different pages: '.implode(', ', $finals).'.',
                'Pick one version (e.g. https://www.…) and 301-redirect the others to it, so Google does not split your rankings.');
        } elseif ($dead) {
            $add('One main address', 'warn', count($dead).' of the '.count($variants).' versions ('.(count($variants) > 2 ? 'http/https, with/without www' : 'http/https').') do not load.', 'Make every version redirect to your main address — some visitors type it differently.');
        } else {
            $add('One main address', 'pass', 'http, https, www and non-www all end on the same address.');
        }
        $insecure = collect($variants)->first(fn ($v) => str_starts_with($v['url'], 'http://') && str_starts_with($v['final'], 'http://') && $v['code'] >= 200 && $v['code'] < 300);
        if ($insecure) {
            $add('HTTPS redirect', 'fail', $insecure['url'].' loads without moving visitors to https://.', 'Add a 301 redirect from http:// to https:// (your host or Cloudflare can do it in one click).');
        }
        $total = array_sum(array_column($chain, 'ms'));
        $status = array_column($checks, 'status');
        $rating = in_array('fail', $status, true) ? 'poor' : (in_array('warn', $status, true) ? 'average' : 'good'); // headline matches the worst finding
        $weights = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];
        $score = (int) round(100 * array_sum(array_map(fn ($c) => $weights[$c['status']], $checks)) / max(1, count($checks)));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return [
            'url' => $url,
            'final_url' => $last['url'],
            'hops' => $hops,
            'total_ms' => $total,
            'chain' => array_map(fn ($s) => array_diff_key($s, ['headers' => 1]), $chain),
            'headers' => $last['headers'] ?? [],
            'variants' => $variants,
            'score' => $score,
            'rating' => $rating,
            'summary' => match ($rating) {
                'good' => 'Clean redirects: visitors and Google reach the page quickly.',
                'average' => 'It works, but extra or temporary redirects slow visitors and can split your rankings.',
                default => 'Redirect problems are stopping or slowing visitors. Fix the red items first.',
            },
            'counts' => array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0],
            'checks' => $checks,
        ];
    }
}
