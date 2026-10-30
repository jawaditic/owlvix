<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free website security & SSL checker (/security-checker). Passive checks only — it reads what the site
 * sends to every visitor (certificate, headers, cookies, versions); it never probes for hidden files.
 */
class SecurityCheck
{
    public static function run(string $url): array
    {
        if ($c = Cache::get('seccheck:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        $host = parse_url($url, PHP_URL_HOST);
        $https = SafeFetch::get('https://'.$host.'/');
        $http = SafeFetch::get('http://'.$host.'/', 10);
        if (! $https['ok'] && ! $http['ok']) {
            return ['ok' => false, 'error' => $https['error']];
        }
        $cert = self::certificate($host);
        $report = self::analyse($url, $host, $https, $http, $cert);
        Cache::put('seccheck:'.sha1($url), $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    /** Certificate details from a direct TLS connection to the host's public IP. */
    public static function certificate(string $host): array
    {
        $ip = SafeFetch::publicIp($host);
        if (! $ip) {
            return ['ok' => false, 'error' => 'Could not resolve the host.'];
        }
        $target = str_contains($ip, ':') ? "[$ip]" : $ip;
        foreach ([true, false] as $verify) {
            $ctx = stream_context_create(['ssl' => [
                'capture_peer_cert' => true, 'peer_name' => $host, 'SNI_enabled' => true,
                'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => ! $verify,
            ]]);
            $sock = @stream_socket_client("ssl://$target:443", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
            if (! $sock) {
                continue;
            }
            $params = stream_context_get_params($sock);
            $meta = stream_get_meta_data($sock);
            fclose($sock);
            $info = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? null) ?: [];
            if (! $info) {
                continue;
            }
            $sans = array_map(fn ($s) => strtolower(trim(substr(trim($s), 4))), array_filter(explode(',', $info['extensions']['subjectAltName'] ?? ''), fn ($s) => str_starts_with(trim($s), 'DNS:')));

            return [
                'ok' => true, 'trusted' => $verify,
                'issuer' => $info['issuer']['O'] ?? $info['issuer']['CN'] ?? 'Unknown',
                'expires' => $info['validTo_time_t'] ?? null,
                'days_left' => isset($info['validTo_time_t']) ? (int) floor(($info['validTo_time_t'] - time()) / 86400) : null,
                'names' => $sans,
                'protocol' => $meta['crypto']['protocol'] ?? null,
            ];
        }

        return ['ok' => false, 'error' => 'No HTTPS connection on port 443.'];
    }

    public static function analyse(string $url, string $host, array $https, array $http, array $cert): array
    {
        $checks = [];
        $add = function (string $cat, string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = compact('cat', 'title', 'status', 'found', 'tip');
        };
        $page = $https['ok'] ? $https : $http;
        $h = $page['headers'] ?? [];
        $get = fn ($name) => strtolower(trim((string) ($h[$name][0] ?? '')));

        // ---------- HTTPS & certificate ----------
        $hasHttps = $https['ok'] && str_starts_with($https['url'], 'https://');
        $add('HTTPS & certificate', 'HTTPS', $hasHttps ? 'pass' : 'fail', $hasHttps ? 'The site loads over HTTPS.' : 'The site does not load over HTTPS.',
            $hasHttps ? '' : 'Install an SSL certificate (free from Let\'s Encrypt or your host) so the connection is encrypted and browsers stop showing "Not secure".');

        $redirects = $http['ok'] && str_starts_with($http['url'], 'https://');
        $add('HTTPS & certificate', 'HTTP → HTTPS redirect', $redirects ? 'pass' : ($hasHttps ? 'warn' : 'fail'),
            $redirects ? 'http:// visitors are sent to https://.' : 'http:// visitors stay on the insecure version.',
            $redirects ? '' : 'Add a permanent (301) redirect from http:// to https:// on your server or hosting panel.');

        if ($cert['ok']) {
            $days = $cert['days_left'];
            $nameOk = (bool) array_filter($cert['names'], fn ($n) => $n === strtolower($host) || (str_starts_with($n, '*.') && str_ends_with(strtolower($host), substr($n, 1)) && substr_count($host, '.') === substr_count($n, '.')));
            $add('HTTPS & certificate', 'Certificate valid', $cert['trusted'] && $nameOk ? 'pass' : 'fail',
                $cert['trusted'] && $nameOk ? 'Trusted certificate from '.$cert['issuer'].'.' : ($nameOk ? 'The certificate is not trusted by browsers (self-signed or incomplete chain).' : 'The certificate does not cover '.$host.'.'),
                $cert['trusted'] && $nameOk ? '' : 'Visitors see a security warning. Reissue the certificate for this exact domain (and www) and install the full certificate chain.');
            if ($days !== null) {
                $add('HTTPS & certificate', 'Certificate expiry', $days < 0 ? 'fail' : ($days < 21 ? 'warn' : 'pass'),
                    $days < 0 ? 'Expired '.abs($days).' days ago.' : "Expires in $days days (".date('j M Y', $cert['expires']).').',
                    $days < 21 ? 'Renew the certificate now and turn on auto-renewal, so the site never shows an expired-certificate warning.' : '');
            }
            if ($cert['protocol']) {
                $modern = in_array($cert['protocol'], ['TLSv1.3', 'TLSv1.2'], true);
                $add('HTTPS & certificate', 'TLS version', $modern ? 'pass' : 'fail', 'Connected with '.$cert['protocol'].'.', $modern ? '' : 'Turn off TLS 1.0/1.1 and use TLS 1.2 or 1.3 — older versions are insecure and blocked by browsers.');
            }
        } elseif ($hasHttps) {
            $add('HTTPS & certificate', 'Certificate valid', 'warn', 'Could not read the certificate details.', '');
        }

        // ---------- Security headers ----------
        $hsts = $get('strict-transport-security');
        $add('Security headers', 'HSTS', $hsts ? 'pass' : 'warn', $hsts ? 'Strict-Transport-Security is set.' : 'No Strict-Transport-Security header.',
            $hsts ? '' : 'Add "Strict-Transport-Security: max-age=31536000" so browsers always use HTTPS, even if someone types http://.');
        $csp = $get('content-security-policy');
        $add('Security headers', 'Content Security Policy', $csp ? 'pass' : 'warn', $csp ? 'A Content-Security-Policy is set.' : 'No Content-Security-Policy header.',
            $csp ? '' : 'A Content-Security-Policy limits which scripts can run on your pages, which blocks most cross-site scripting attacks. Start in report-only mode.');
        $xfo = $get('x-frame-options');
        $frame = $xfo || str_contains($csp, 'frame-ancestors');
        $add('Security headers', 'Clickjacking protection', $frame ? 'pass' : 'warn', $frame ? 'Pages cannot be framed by other sites.' : 'No X-Frame-Options or frame-ancestors rule.',
            $frame ? '' : 'Add "X-Frame-Options: SAMEORIGIN" so other sites cannot load your pages in a hidden frame to trick visitors into clicking.');
        $nosniff = $get('x-content-type-options') === 'nosniff';
        $add('Security headers', 'MIME sniffing protection', $nosniff ? 'pass' : 'warn', $nosniff ? 'X-Content-Type-Options: nosniff.' : 'No X-Content-Type-Options header.',
            $nosniff ? '' : 'Add "X-Content-Type-Options: nosniff" so browsers don\'t treat uploaded files as scripts.');
        $ref = $get('referrer-policy');
        $add('Security headers', 'Referrer policy', $ref ? 'pass' : 'warn', $ref ? 'Referrer-Policy: '.$ref.'.' : 'No Referrer-Policy header.',
            $ref ? '' : 'Add "Referrer-Policy: strict-origin-when-cross-origin" so full page addresses aren\'t leaked to other sites.');
        $perm = $get('permissions-policy');
        $add('Security headers', 'Permissions policy', $perm ? 'pass' : 'warn', $perm ? 'Permissions-Policy is set.' : 'No Permissions-Policy header.',
            $perm ? '' : 'Add a Permissions-Policy (e.g. "camera=(), microphone=(), geolocation=()") to switch off browser features your site doesn\'t use.');

        // ---------- Cookies ----------
        $cookies = (array) ($h['set-cookie'] ?? []);
        if ($cookies) {
            $weak = array_filter($cookies, fn ($c) => ! preg_match('/;\s*secure/i', $c) || ! preg_match('/;\s*samesite=/i', $c));
            $names = implode(', ', array_slice(array_map(fn ($c) => trim(strtok($c, '=')), $weak), 0, 4));
            $add('Cookies', 'Cookie flags', $weak ? 'warn' : 'pass', $weak ? count($weak).' of '.count($cookies).' cookies miss Secure or SameSite ('.$names.').' : 'All cookies use Secure and SameSite.',
                $weak ? 'Mark cookies "Secure; SameSite=Lax" (and "HttpOnly" for session cookies) so they are only sent over HTTPS and not to other sites.' : '');
        } else {
            $add('Cookies', 'Cookie flags', 'pass', 'No cookies set on the homepage.');
        }

        // ---------- Information leaks ----------
        $server = $h['server'][0] ?? '';
        $powered = $h['x-powered-by'][0] ?? '';
        $leak = preg_match('#\d+\.\d+#', $server.' '.$powered);
        $add('Information leaks', 'Server version', $leak ? 'warn' : 'pass', $leak ? 'Reveals: '.trim($server.' '.$powered).'.' : 'No software versions in the headers.',
            $leak ? 'Hide version numbers (e.g. "ServerTokens Prod" in Apache, "expose_php = Off" in PHP). Attackers use them to look up known holes.' : '');
        $gen = preg_match('#<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']*\d[^"\']*)#i', $page['body'] ?? '', $m) ? $m[1] : null;
        $add('Information leaks', 'CMS version', $gen ? 'warn' : 'pass', $gen ? 'The page shows "'.$gen.'".' : 'No CMS version shown in the page.',
            $gen ? 'Remove the generator tag and keep the CMS, theme and plugins updated — outdated versions are the most common way sites get hacked.' : '');

        // ---------- Content ----------
        if ($hasHttps) {
            $insecure = preg_match_all('#<(?:img|script|iframe|source)[^>]+src=["\']http://#i', $https['body'] ?? '') + preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']http://#i', $https['body'] ?? '');
            $add('Content', 'Mixed content', $insecure ? 'fail' : 'pass', $insecure ? "$insecure file(s) load over insecure http://." : 'All page files load over HTTPS.',
                $insecure ? 'Change those http:// links to https:// — browsers block or warn about insecure files on secure pages.' : '');
        }

        $weights = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];
        $score = (int) round(100 * array_sum(array_map(fn ($c) => $weights[$c['status']], $checks)) / max(1, count($checks)));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return [
            'url' => $url,
            'final_url' => $page['url'] ?? $url,
            'score' => $score,
            'rating' => $score >= 85 ? 'good' : ($score >= 60 ? 'average' : 'poor'),
            'summary' => match (true) {
                $score >= 85 => 'Good protection. A few small hardening steps below would make it even stronger.',
                $score >= 60 => 'The basics are there, but some missing protections leave visitors and data exposed. Start with the red items.',
                default => 'This site is missing important protections. Fix the red items first — they affect every visitor.',
            },
            'cert' => $cert['ok'] ? ['issuer' => $cert['issuer'], 'days_left' => $cert['days_left'], 'protocol' => $cert['protocol']] : null,
            'counts' => array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0],
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
