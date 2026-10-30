<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free email & DNS checker (/email-dns-checker): reads a domain's public DNS records (MX, SPF, DKIM, DMARC,
 * name servers) and explains why its email might land in spam. DNS lookups only — nothing is sent.
 */
class EmailCheck
{
    /** DKIM selectors used by the common email providers and senders (a selector is not public, so we try these). */
    private const DKIM_SELECTORS = ['google', 'selector1', 'selector2', 'default', 'k1', 'k2', 's1', 's2', 'dkim', 'mail',
        'zoho', 'zmail', 'protonmail', 'fm1', 'mandrill', 'mailjet', 'smtp', 'titan1', 'hostinger'];

    /** Email provider from the MX host => [name, SPF include to suggest]. */
    private const PROVIDERS = [
        'google.com' => ['Google Workspace', 'include:_spf.google.com'],
        'googlemail.com' => ['Google Workspace', 'include:_spf.google.com'],
        'outlook.com' => ['Microsoft 365', 'include:spf.protection.outlook.com'],
        'zoho.' => ['Zoho Mail', 'include:zohomail.com'],
        'privateemail.com' => ['Namecheap Private Email', 'include:spf.privateemail.com'],
        'registrar-servers.com' => ['Namecheap email forwarding', 'include:spf.efwd.registrar-servers.com'],
        'jellyfish.systems' => ['Namecheap hosting email (cPanel)', 'include:spf.web-hosting.com'],
        'titan.email' => ['Titan Mail', 'include:spf.titan.email'],
        'secureserver.net' => ['GoDaddy email', 'include:secureserver.net'],
        'hostinger.com' => ['Hostinger email', 'include:_spf.mail.hostinger.com'],
        'protonmail.ch' => ['Proton Mail', 'include:_spf.protonmail.ch'],
        'icloud.com' => ['iCloud Mail', 'include:icloud.com'],
        'yandex.' => ['Yandex Mail', 'include:_spf.yandex.net'],
        'mimecast.com' => ['Mimecast', 'include:_netblocks.mimecast.com'],
        'pphosted.com' => ['Proofpoint', ''],
        'mxroute.com' => ['MXroute', 'include:mxroute.com'],
        'messagingengine.com' => ['Fastmail', 'include:spf.messagingengine.com'],
    ];

    /** The domain from whatever the visitor typed (domain, URL or email address), or null if it isn't one. */
    public static function domain(string $input): ?string
    {
        $s = strtolower(trim($input));
        if (str_contains($s, '@') && ! str_contains($s, '/')) {
            $s = substr(strrchr($s, '@'), 1);
        }
        $s = preg_match('#^[a-z]+://#', $s) ? (string) parse_url($s, PHP_URL_HOST) : explode('/', $s)[0];
        $s = preg_replace(['/:\d+$/', '/^www\./'], '', rtrim($s, '.'));
        if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/', $s)
            || preg_match('/\.(local|test|localhost|internal|lan|home|example|invalid)$/', $s)) {
            return null;
        }

        return $s;
    }

    public static function run(string $domain): array
    {
        if ($c = Cache::get('emailcheck:'.$domain)) {
            return ['ok' => true, 'report' => $c];
        }
        $records = self::lookup($domain);
        if (! $records['ns'] && ! $records['mx'] && ! $records['a']) {
            return ['ok' => false, 'error' => 'We could not find that domain. Check the spelling and try again.'];
        }
        $report = self::analyse($domain, $records);
        Cache::put('emailcheck:'.$domain, $report, now()->addMinutes(30));

        return ['ok' => true, 'report' => $report];
    }

    /** All the DNS records the checks need. */
    public static function lookup(string $d): array
    {
        $mx = array_map(fn ($r) => ['host' => strtolower(rtrim($r['target'] ?? '', '.')), 'pri' => (int) ($r['pri'] ?? 0)], @dns_get_record($d, DNS_MX) ?: []);
        usort($mx, fn ($a, $b) => $a['pri'] <=> $b['pri']);
        $dkim = [];
        foreach (self::DKIM_SELECTORS as $sel) {
            $rec = collect(self::txt("$sel._domainkey.$d"))->first(fn ($t) => str_contains($t, 'p=') && (str_contains($t, 'v=DKIM1') || str_contains($t, 'k=')));
            if ($rec) {
                $dkim[$sel] = $rec;
            }
        }
        $spf = array_values(array_filter(self::txt($d), fn ($t) => preg_match('/^v=spf1\b/i', $t)));

        return [
            'mx' => $mx,
            'spf' => $spf,
            'spf_lookups' => $spf ? self::spfLookups($spf[0]) : 0,
            'dmarc' => array_values(array_filter(self::txt("_dmarc.$d"), fn ($t) => preg_match('/^v=DMARC1/i', $t))),
            'dkim' => $dkim,
            'ns' => array_map(fn ($r) => strtolower(rtrim($r['target'] ?? '', '.')), @dns_get_record($d, DNS_NS) ?: []),
            'a' => array_column(@dns_get_record($d, DNS_A) ?: [], 'ip'),
        ];
    }

    private static function txt(string $name): array
    {
        return array_map(fn ($r) => isset($r['entries']) ? implode('', $r['entries']) : ($r['txt'] ?? ''), @dns_get_record($name, DNS_TXT) ?: []);
    }

    /** How many DNS lookups an SPF record needs (the limit is 10), following include: and redirect=. */
    private static function spfLookups(string $spf, int $depth = 0, int &$budget = 20): int
    {
        $count = 0;
        foreach (preg_split('/\s+/', strtolower($spf)) as $term) {
            $term = ltrim($term, '+-~?');
            if (preg_match('/^(include:|redirect=)(.+)$/', $term, $m)) {
                $count++;
                if ($depth < 4 && $budget-- > 0) {
                    $inner = collect(self::txt($m[2]))->first(fn ($t) => preg_match('/^v=spf1\b/i', $t));
                    $count += $inner ? self::spfLookups($inner, $depth + 1, $budget) : 0;
                }
            } elseif (preg_match('/^(a|mx|ptr|exists)([:\/]|$)/', $term)) {
                $count++;
            }
        }

        return $count;
    }

    public static function provider(array $mx, string $domain): ?array
    {
        foreach ($mx as $r) {
            foreach (self::PROVIDERS as $needle => $p) {
                if (str_contains($r['host'], $needle)) {
                    return $p;
                }
            }
        }
        foreach ($mx as $r) {
            if ($r['host'] === $domain || str_ends_with($r['host'], '.'.$domain)) {
                return ['Your own mail server / web hosting', 'mx'];
            }
        }

        return $mx ? [$mx[0]['host'], ''] : null;
    }

    public static function analyse(string $domain, array $r): array
    {
        $checks = [];
        $add = function (string $cat, string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = compact('cat', 'title', 'status', 'found', 'tip');
        };
        $nullMx = count($r['mx']) === 1 && in_array($r['mx'][0]['host'], ['', '.'], true);
        $provider = $nullMx ? null : self::provider($r['mx'], $domain);
        $include = $provider[1] ?? '';
        $suggestSpf = 'v=spf1 '.($include ? "$include " : '').'~all';

        // ---------- Receiving email ----------
        if ($nullMx) {
            $add('Receiving email', 'Mail servers (MX)', 'pass', 'This domain says it does not use email (null MX).');
        } elseif ($r['mx']) {
            $add('Receiving email', 'Mail servers (MX)', 'pass', count($r['mx']).' mail server(s) found'.($provider ? " — {$provider[0]}." : '.'));
        } else {
            $add('Receiving email', 'Mail servers (MX)', 'fail', 'No mail servers (MX records) found — this domain cannot receive email.',
                'Add the MX records from your email provider (Google Workspace, Microsoft 365, Zoho, your hosting…) in your DNS settings.');
        }

        // ---------- Sender authentication ----------
        $spf = $r['spf'];
        if (! $spf) {
            $add('Spam protection', 'SPF record', 'fail', 'No SPF record — anyone can send email pretending to be you, and inboxes trust your real emails less.',
                "Add a TXT record on $domain: $suggestSpf (add an include: for each service that sends email for you, like your newsletter tool).");
        } elseif (count($spf) > 1) {
            $add('Spam protection', 'SPF record', 'fail', count($spf).' SPF records found — having more than one makes SPF fail completely.',
                'Merge them into one TXT record that starts with v=spf1 and lists every include:.');
        } else {
            $all = preg_match('/([+\-~?]?)all\b/i', $spf[0], $m) ? ($m[1] ?: '+') : null;
            [$status, $found, $tip] = match ($all) {
                '-' => ['pass', 'SPF found, strict (-all): servers you have not listed are rejected.', ''],
                '~' => ['pass', 'SPF found (~all): emails from unlisted servers are marked as suspicious.', ''],
                '+' => ['fail', 'SPF ends in +all — that allows the whole internet to send as you.', 'Change +all to ~all (or -all once you are sure every sender is listed).'],
                '?' => ['warn', 'SPF ends in ?all, which gives no protection.', 'Change ?all to ~all.'],
                default => ['warn', 'SPF has no "all" rule at the end, so it gives little protection.', 'End the record with ~all.'],
            };
            $add('Spam protection', 'SPF record', $status, $found, $tip);
            if ($r['spf_lookups'] > 10) {
                $add('Spam protection', 'SPF lookups', 'fail', "Your SPF needs {$r['spf_lookups']} DNS lookups — the limit is 10, so receivers treat it as broken.",
                    'Remove services you no longer use, or replace include: entries with the IP addresses they send from.');
            } else {
                $add('Spam protection', 'SPF lookups', 'pass', "{$r['spf_lookups']} of the 10 allowed DNS lookups used.");
            }
        }

        if ($r['dkim']) {
            $add('Spam protection', 'DKIM signature', 'pass', 'DKIM key found ('.implode(', ', array_keys($r['dkim'])).'): your emails can be signed so receivers know they are genuine.');
        } else {
            $add('Spam protection', 'DKIM signature', 'warn', 'We did not find a DKIM key under the common names. Your provider may use its own name — but if DKIM is off, Gmail and Outlook trust your emails less.',
                'Turn on DKIM in your email provider\'s admin panel ('.($provider[0] ?? 'Google Workspace, Microsoft 365, cPanel…').') and add the TXT record it gives you.');
        }

        $dmarc = $r['dmarc'][0] ?? null;
        if (! $dmarc) {
            $add('Spam protection', 'DMARC policy', 'fail', 'No DMARC record. Gmail and Yahoo require DMARC for bulk senders, and without it spoofed emails are not stopped.',
                "Add a TXT record on _dmarc.$domain: v=DMARC1; p=none; rua=mailto:dmarc@$domain — then move to p=quarantine once reports look clean.");
        } else {
            $tag = fn ($k) => preg_match('/\b'.$k.'\s*=\s*([^;\s]+)/i', $dmarc, $m) ? strtolower($m[1]) : null;
            $p = $tag('p');
            $pct = $tag('pct');
            if ($p === 'reject' || $p === 'quarantine') {
                $partial = $pct !== null && (int) $pct < 100;
                $add('Spam protection', 'DMARC policy', $partial ? 'warn' : 'pass', "DMARC is set to $p".($partial ? " for only $pct% of email." : ' — spoofed emails are blocked or sent to spam.'),
                    $partial ? 'Raise pct to 100 once you are happy with the reports.' : '');
            } else {
                $add('Spam protection', 'DMARC policy', 'warn', 'DMARC is set to p=none — it only monitors, so fake emails using your domain still get through.',
                    'When your reports show only your real senders, change p=none to p=quarantine, then p=reject.');
            }
            $add('Spam protection', 'DMARC reports', $tag('rua') ? 'pass' : 'warn', $tag('rua') ? 'Reports are sent to '.str_replace('mailto:', '', $tag('rua')).'.' : 'DMARC reports are not turned on, so you cannot see who sends email as you.',
                $tag('rua') ? '' : "Add rua=mailto:dmarc@$domain to the DMARC record.");
        }

        // ---------- Domain ----------
        $ns = count($r['ns']);
        $add('Domain', 'Name servers', $ns >= 2 ? 'pass' : ($ns ? 'warn' : 'fail'), $ns ? "$ns name server(s): ".implode(', ', array_slice($r['ns'], 0, 4)).'.' : 'No name servers found.',
            $ns >= 2 ? '' : 'Use at least two name servers so your domain keeps working if one is down.');
        $add('Domain', 'Website address', $r['a'] ? 'pass' : 'warn', $r['a'] ? "$domain points to ".implode(', ', array_slice($r['a'], 0, 3)).'.' : "$domain does not point to a web server (no A record).",
            $r['a'] ? '' : 'Add an A record so people who type your domain reach your website.');

        $weights = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];
        $score = (int) round(100 * array_sum(array_map(fn ($c) => $weights[$c['status']], $checks)) / max(1, count($checks)));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return [
            'url' => $domain,
            'domain' => $domain,
            'provider' => $provider[0] ?? null,
            'records' => [
                'MX' => array_map(fn ($m) => "{$m['pri']} {$m['host']}", $r['mx']),
                'SPF' => $spf,
                'DMARC' => $r['dmarc'],
                'DKIM' => array_map(fn ($sel, $v) => "$sel: ".(strlen($v) > 90 ? substr($v, 0, 90).'…' : $v), array_keys($r['dkim']), $r['dkim']),
                'NS' => $r['ns'],
            ],
            'suggest_spf' => $spf ? null : $suggestSpf,
            'score' => $score,
            'rating' => $score >= 85 ? 'good' : ($score >= 60 ? 'average' : 'poor'),
            'summary' => match (true) {
                $score >= 85 => 'Your email is well set up. Inboxes can tell your emails are genuine.',
                $score >= 60 => 'Some email protections are missing — your emails may land in spam and others can pretend to be you. Start with the red items.',
                default => 'Important email records are missing. Expect emails to land in spam until the red items are fixed.',
            },
            'counts' => array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0],
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
