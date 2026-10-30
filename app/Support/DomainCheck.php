<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Free domain age & expiry checker (/domain-age-checker). Asks the domain's registry over RDAP (the official
 * replacement for WHOIS, server list from IANA); for endings without RDAP (.pk, .io, .co…) it falls back to WHOIS.
 */
class DomainCheck
{
    /** Two-level endings that sit under a country code, e.g. example.co.uk, example.com.pk. */
    private const SECOND_LEVEL = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'biz', 'gob', 'or', 'ne', 'nic', 'web', 'info', 'ltd', 'plc', 'me', 'sch'];

    /** The registered domain (no subdomains) from whatever the visitor typed, or null. */
    public static function domain(string $input): ?string
    {
        $host = EmailCheck::domain($input);
        if (! $host) {
            return null;
        }
        $parts = explode('.', $host);
        $keep = count($parts) >= 3 && strlen(end($parts)) === 2 && in_array($parts[count($parts) - 2], self::SECOND_LEVEL, true) ? 3 : 2;

        return implode('.', array_slice($parts, -$keep));
    }

    public static function run(string $domain): array
    {
        if ($c = Cache::get('domaincheck:'.$domain)) {
            return ['ok' => true, 'report' => $c];
        }
        $data = self::rdap($domain) ?? self::whois($domain);
        if (! $data) {
            return ['ok' => false, 'error' => 'We could not find registration details for that domain. It may not be registered, or its registry does not share them.'];
        }
        $report = self::analyse($domain, $data);
        Cache::put('domaincheck:'.$domain, $report, now()->addHours(6));

        return ['ok' => true, 'report' => $report];
    }

    /** Registration data over RDAP, or null if the ending has no RDAP server or the lookup failed. */
    public static function rdap(string $domain): ?array
    {
        $servers = Cache::remember('rdap-bootstrap', now()->addDay(), function () {
            try {
                $map = [];
                foreach (Http::timeout(10)->get('https://data.iana.org/rdap/dns.json')->json('services') ?? [] as [$tlds, $urls]) {
                    foreach ($tlds as $t) {
                        $map[$t] = rtrim($urls[0], '/').'/';
                    }
                }

                return $map;
            } catch (\Throwable) {
                return [];
            }
        });
        $base = $servers[substr(strrchr($domain, '.'), 1)] ?? null;
        if (! $base) {
            return null;
        }
        try {
            $res = Http::timeout(10)->accept('application/rdap+json')->get($base.'domain/'.$domain);
        } catch (\Throwable) {
            return null;
        }
        if (! $res->ok()) {
            return $res->status() === 404 ? ['registered' => false] : null;
        }

        return self::parseRdap($res->json() ?? []);
    }

    public static function parseRdap(array $j): array
    {
        $events = [];
        foreach ($j['events'] ?? [] as $e) {
            $events[$e['eventAction'] ?? ''] = $e['eventDate'] ?? null;
        }
        $registrar = null;
        foreach ($j['entities'] ?? [] as $ent) {
            if (in_array('registrar', $ent['roles'] ?? [], true)) {
                foreach ($ent['vcardArray'][1] ?? [] as $v) {
                    if (($v[0] ?? '') === 'fn') {
                        $registrar = $v[3];
                    }
                }
            }
        }

        return [
            'registered' => true,
            'registrar' => $registrar,
            'created' => $events['registration'] ?? null,
            'expires' => $events['expiration'] ?? null,
            'updated' => $events['last changed'] ?? null,
            'status' => array_values($j['status'] ?? []),
            'nameservers' => array_map(fn ($n) => strtolower($n['ldhName'] ?? ''), $j['nameservers'] ?? []),
            'dnssec' => $j['secureDNS']['delegationSigned'] ?? null,
            'source' => 'RDAP',
        ];
    }

    /** WHOIS fallback: ask IANA which server handles the ending, then ask that server. */
    public static function whois(string $domain): ?array
    {
        $tld = substr(strrchr($domain, '.'), 1);
        $server = Cache::remember("whois-server:$tld", now()->addDay(), fn () => preg_match('/^whois:\s*(\S+)/mi', self::whoisQuery('whois.iana.org', $tld), $m) ? $m[1] : '');
        if (! $server) {
            return null;
        }
        $raw = self::whoisQuery($server, $domain);

        return $raw === '' ? null : self::parseWhois($raw);
    }

    private static function whoisQuery(string $server, string $query): string
    {
        $s = @fsockopen($server, 43, $errno, $err, 6);
        if (! $s) {
            return '';
        }
        stream_set_timeout($s, 6);
        fwrite($s, $query."\r\n");
        $out = '';
        while (! feof($s) && strlen($out) < 100_000) {
            $chunk = fread($s, 8192);
            if ($chunk === false || ($chunk === '' && stream_get_meta_data($s)['timed_out'])) {
                break;
            }
            $out .= $chunk;
        }
        fclose($s);

        return $out;
    }

    public static function parseWhois(string $raw): array
    {
        if (preg_match('/^(no match|not found|no data found|domain not found|no entries found|status:\s*(free|available))/mi', $raw)) {
            return ['registered' => false];
        }
        $field = function (array $labels) use ($raw) {
            foreach ($labels as $l) {
                if (preg_match('/^\s*'.$l.'\s*:\s*(.+)$/mi', $raw, $m) && trim($m[1]) !== '') {
                    return trim($m[1]);
                }
            }

            return null;
        };
        $date = function (?string $v) {
            if (! $v) {
                return null;
            }
            $ts = strtotime(preg_replace('/\s*\(.*\)$/', '', $v));

            return $ts ? gmdate('c', $ts) : null;
        };
        preg_match_all('/^\s*(?:name server|nserver|nameservers?)\s*:\s*(\S+)/mi', $raw, $ns);
        preg_match_all('/^\s*(?:domain )?status\s*:\s*(\S+)/mi', $raw, $st);

        return [
            'registered' => true,
            'registrar' => $field(['Registrar', 'Sponsoring Registrar', 'Registrar Name']),
            'created' => $date($field(['Creation Date', 'Created On', 'Created', 'Registered On', 'Registration Date', 'Domain Registration Date', 'Create Date'])),
            'expires' => $date($field(['Registry Expiry Date', 'Registrar Registration Expiration Date', 'Expiration Date', 'Expiry Date', 'Expires On', 'Expires', 'Domain Expiration Date', 'paid-till'])),
            'updated' => $date($field(['Updated Date', 'Last Updated On', 'Last Modified', 'Changed'])),
            'status' => array_values(array_unique(array_map(fn ($s) => strtolower(preg_replace('/https?:.*$/', '', $s)), $st[1]))),
            'nameservers' => array_values(array_unique(array_map(fn ($n) => strtolower(rtrim($n, '.')), $ns[1]))),
            'dnssec' => preg_match('/^\s*dnssec\s*:\s*signed/mi', $raw) ? true : (preg_match('/^\s*dnssec\s*:/mi', $raw) ? false : null),
            'source' => 'WHOIS',
        ];
    }

    public static function analyse(string $domain, array $d, ?int $now = null): array
    {
        $now ??= time();
        if (! ($d['registered'] ?? true)) {
            return ['url' => $domain, 'domain' => $domain, 'registered' => false, 'rating' => 'average', 'score' => 0, 'checks' => [], 'counts' => ['fail' => 0, 'warn' => 0, 'pass' => 0],
                'summary' => "$domain does not appear to be registered — it may be available to buy."];
        }
        $checks = [];
        $add = function (string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = ['cat' => 'Domain', 'title' => $title, 'status' => $status, 'found' => $found, 'tip' => $tip];
        };
        $expires = $d['expires'] ? strtotime($d['expires']) : null;
        $created = $d['created'] ? strtotime($d['created']) : null;
        $days = $expires ? (int) floor(($expires - $now) / 86400) : null;
        $age = $created ? self::age($created, $now) : null;

        if ($days === null) {
            $add('Expiry date', 'warn', 'The registry does not share the expiry date for this domain.', 'Check the renewal date in your registrar account and turn on auto-renew.');
        } elseif ($days < -60 && $d['nameservers']) {
            $add('Expiry date', 'warn', 'The registry lists an expiry date in the past ('.gmdate('j M Y', $expires).'), but the domain still works — the registry record looks out of date.', 'Check the real renewal date in your registrar account.');
        } elseif ($days < 0) {
            $add('Expiry date', 'fail', 'This domain expired '.abs($days).' days ago.', 'Renew it now at your registrar — expired domains take websites and email offline and can be bought by others.');
        } elseif ($days < 30) {
            $add('Expiry date', 'fail', "Expires in $days days.", 'Renew now and turn on auto-renew so your website and email never go down.');
        } elseif ($days < 90) {
            $add('Expiry date', 'warn', "Expires in $days days.", 'Renew soon, or check that auto-renew is on and your payment card is up to date.');
        } else {
            $add('Expiry date', 'pass', "Expires in $days days (".gmdate('j M Y', $expires).').');
        }
        $locked = (bool) array_filter($d['status'], fn ($s) => str_contains(strtolower(str_replace(' ', '', $s)), 'transferprohibited'));
        $add('Transfer lock', $locked ? 'pass' : 'warn', $locked ? 'The domain is locked against unauthorised transfers.' : 'No transfer lock found.',
            $locked ? '' : 'Turn on "registrar lock" / "transfer lock" in your registrar account so nobody can move the domain away.');
        if ($d['dnssec'] !== null) {
            $add('DNSSEC', $d['dnssec'] ? 'pass' : 'warn', $d['dnssec'] ? 'DNSSEC is on — DNS answers for this domain are signed.' : 'DNSSEC is off.',
                $d['dnssec'] ? '' : 'Optional extra protection: turn on DNSSEC at your DNS provider (Cloudflare and most registrars make it one click).');
        }
        $ns = count($d['nameservers']);
        $add('Name servers', $ns >= 2 ? 'pass' : 'warn', $ns ? "$ns name servers: ".implode(', ', array_slice($d['nameservers'], 0, 4)).'.' : 'No name servers listed.',
            $ns >= 2 ? '' : 'Use at least two name servers.');

        $weights = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];
        $score = (int) round(100 * array_sum(array_map(fn ($c) => $weights[$c['status']], $checks)) / max(1, count($checks)));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return [
            'url' => $domain,
            'domain' => $domain,
            'registered' => true,
            'registrar' => $d['registrar'],
            'created' => $created ? gmdate('j M Y', $created) : null,
            'expires' => $expires ? gmdate('j M Y', $expires) : null,
            'updated' => $d['updated'] ? gmdate('j M Y', strtotime($d['updated'])) : null,
            'age' => $age,
            'days_left' => $days,
            'status' => $d['status'],
            'nameservers' => $d['nameservers'],
            'source' => $d['source'],
            'score' => $score,
            'rating' => $days !== null && $days < 30 && ! ($days < -60 && $d['nameservers']) ? 'poor' : ($score >= 85 ? 'good' : 'average'),
            'summary' => match (true) {
                $days !== null && $days < 30 && ! ($days < -60 && $d['nameservers']) => 'This domain needs renewing now — if it lapses, the website and email stop working.',
                $score >= 85 => 'This domain is in good shape: renewed well ahead and protected.',
                default => 'The domain works, but a few settings would protect it better.',
            },
            'counts' => array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0],
            'checks' => $checks,
        ];
    }

    /** "12 years, 3 months" */
    public static function age(int $created, int $now): string
    {
        $diff = (new \DateTime('@'.$created))->diff(new \DateTime('@'.$now));
        $parts = array_filter([$diff->y ? $diff->y.' year'.($diff->y > 1 ? 's' : '') : '', $diff->m ? $diff->m.' month'.($diff->m > 1 ? 's' : '') : '']);

        return $parts ? implode(', ', $parts) : $diff->days.' days';
    }
}
