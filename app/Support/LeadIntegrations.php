<?php

namespace App\Support;

use App\Models\BuilderForm;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Funnel lead forms can pass each new lead on to the owner's email tool: Mailchimp, Brevo and/or a webhook.
 * Settings are stored encrypted on the form ("integrations"); API keys are never sent back to the browser.
 * Sending happens after the visitor's response, with short timeouts, and failures are only logged.
 */
class LeadIntegrations
{
    public const TIMEOUT = 3; // short: these run after the response but still hold a PHP worker

    /** Resolves a host to a public IP (or null). Tests may swap this; by default SafeFetch::publicIp. */
    public static ?\Closure $resolver = null;

    public static function publicIp(string $host): ?string
    {
        return self::$resolver ? (self::$resolver)($host) : SafeFetch::publicIp($host);
    }

    /** What the builder may see: lists and URLs, plus the last 4 characters of each key. */
    public static function forApi(?array $saved): array
    {
        $saved ??= [];

        return [
            'mailchimp' => isset($saved['mailchimp']) ? ['list' => $saved['mailchimp']['list'], 'key_hint' => self::hint(preg_replace('/-us\d+$/', '', $saved['mailchimp']['key']))] : null,
            'brevo' => isset($saved['brevo']) ? ['list' => $saved['brevo']['list'], 'key_hint' => self::hint($saved['brevo']['key'])] : null,
            'webhook' => isset($saved['webhook']) ? ['url' => $saved['webhook']['url']] : null,
        ];
    }

    private static function hint(string $key): string
    {
        return '…'.substr($key, -4);
    }

    /**
     * New settings from the builder, merged with the saved ones. A missing section is left as it is; null or an
     * empty list/url removes it; an empty key keeps the saved key.
     *
     * @return array|string the settings to save, or an error message
     */
    public static function merge(?array $saved, array $input): array|string
    {
        $saved ??= [];
        $out = $saved;

        if (array_key_exists('mailchimp', $input)) {
            $in = $input['mailchimp'];
            if (! is_array($in) || trim((string) ($in['list'] ?? '')) === '') {
                unset($out['mailchimp']);
            } else {
                $list = trim((string) $in['list']);
                $key = trim((string) ($in['key'] ?? ''));
                if ($key === '') {
                    $key = $saved['mailchimp']['key'] ?? '';
                    if ($key === '') {
                        return 'Please enter your Mailchimp API key.';
                    }
                }
                if (! preg_match('/^[0-9a-f]{32}-(us\d{1,3})$/', $key, $m)) {
                    return 'That Mailchimp API key doesn’t look right. It looks like 32 letters and numbers, a dash and “us” with a number (for example …-us21).';
                }
                if (! preg_match('/^[0-9a-z]{3,20}$/', $list)) {
                    return 'That Mailchimp audience ID doesn’t look right. Find it under Audience → Settings → “Audience name and defaults”.';
                }
                $out['mailchimp'] = ['key' => $key, 'list' => $list, 'dc' => $m[1]];
            }
        }

        if (array_key_exists('brevo', $input)) {
            $in = $input['brevo'];
            if (! is_array($in) || trim((string) ($in['list'] ?? '')) === '') {
                unset($out['brevo']);
            } else {
                $list = trim((string) $in['list']);
                $key = trim((string) ($in['key'] ?? ''));
                if ($key === '') {
                    $key = $saved['brevo']['key'] ?? '';
                    if ($key === '') {
                        return 'Please enter your Brevo API key.';
                    }
                }
                if (strlen($key) > 200 || preg_match('/\s/', $key)) {
                    return 'That Brevo API key doesn’t look right.';
                }
                if (! preg_match('/^\d{1,10}$/', $list)) {
                    return 'The Brevo list ID should be a number (Contacts → Lists).';
                }
                $out['brevo'] = ['key' => $key, 'list' => $list];
            }
        }

        if (array_key_exists('webhook', $input)) {
            $in = $input['webhook'];
            $url = is_array($in) ? trim((string) ($in['url'] ?? '')) : '';
            if ($url === '') {
                unset($out['webhook']);
            } else {
                if ($error = self::webhookProblem($url)) {
                    return $error;
                }
                $out['webhook'] = ['url' => $url];
            }
        }

        return $out;
    }

    public static function webhookProblem(string $url): ?string
    {
        $parts = parse_url($url);
        if (strlen($url) > 500 || ! $parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'The webhook must be a public https:// address.';
        }
        if (! self::publicIp(strtolower($parts['host']))) {
            return 'That webhook address can’t be reached. It must be a public website (not a local or private address).';
        }

        return null;
    }

    /**
     * Send one lead to every integration on the form. Never throws.
     *
     * @param  array{email: string, name: ?string, phone: ?string, funnel: ?string, step: ?string, variant: ?string, created_at: string}  $lead
     */
    public static function send(BuilderForm $form, array $lead): void
    {
        try {
            $settings = $form->integrations ?? [];
        } catch (\Throwable $e) {
            Log::warning('Builder lead integrations could not be read', ['form' => $form->id, 'error' => $e->getMessage()]);

            return;
        }

        foreach (['mailchimp', 'brevo', 'webhook'] as $tool) {
            if (empty($settings[$tool])) {
                continue;
            }
            try {
                $res = match ($tool) {
                    'mailchimp' => self::mailchimp($settings[$tool], $lead),
                    'brevo' => self::brevo($settings[$tool], $lead),
                    'webhook' => self::webhook($settings[$tool], $lead),
                };
                if ($res !== null && ! $res->successful()) {
                    Log::warning("Builder lead not accepted by {$tool}", ['form' => $form->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
                }
            } catch (\Throwable $e) {
                Log::warning("Builder lead could not be sent to {$tool}", ['form' => $form->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** A phone number the email tools accept (international format, e.g. +44 20 1234 5678), or null. */
    private static function phone(?string $phone): ?string
    {
        $p = preg_replace('/[\s().-]/', '', (string) $phone);

        return preg_match('/^\+[1-9]\d{7,14}$/', $p) ? $p : null;
    }

    /**
     * Mailchimp: the contact is added as "pending", so Mailchimp asks them to confirm (double opt-in) — anyone can
     * type an address into a public form, and unconfirmed sign-ups would get the owner's account flagged for spam.
     * If the audience rejects a merge field (e.g. it has no PHONE field), retry with the email and name only.
     */
    private static function mailchimp(array $cfg, array $lead)
    {
        $url = 'https://'.$cfg['dc'].'.api.mailchimp.com/3.0/lists/'.$cfg['list'].'/members/'.md5(strtolower($lead['email']));
        $send = fn (array $merge) => Http::withBasicAuth('owlvix', $cfg['key'])->acceptJson()
            ->timeout(self::TIMEOUT)->connectTimeout(2)
            ->put($url, ['email_address' => $lead['email'], 'status_if_new' => 'pending'] + ($merge ? ['merge_fields' => $merge] : []));
        $merge = array_filter(['FNAME' => $lead['name'] ?? null, 'PHONE' => self::phone($lead['phone'] ?? null)], fn ($v) => filled($v));
        $res = $send($merge);

        return $res->status() === 400 && $merge ? $send(array_filter(['FNAME' => $merge['FNAME'] ?? null])) : $res;
    }

    /** Brevo: SMS only in international format; if Brevo still rejects the contact, retry without it. */
    private static function brevo(array $cfg, array $lead)
    {
        $send = fn (array $attributes) => Http::withHeaders(['api-key' => $cfg['key']])->acceptJson()
            ->timeout(self::TIMEOUT)->connectTimeout(2)
            ->post('https://api.brevo.com/v3/contacts', [
                'email' => $lead['email'],
                'attributes' => (object) $attributes,
                'listIds' => [(int) $cfg['list']],
                'updateEnabled' => true,
            ]);
        $attributes = array_filter(['FIRSTNAME' => $lead['name'] ?? null, 'SMS' => self::phone($lead['phone'] ?? null)], fn ($v) => filled($v));
        $res = $send($attributes);

        return $res->status() === 400 && isset($attributes['SMS']) ? $send(array_filter(['FIRSTNAME' => $attributes['FIRSTNAME'] ?? null])) : $res;
    }

    private static function webhook(array $cfg, array $lead)
    {
        $url = $cfg['url'];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Checked again now (DNS may have changed since it was saved), and the connection is pinned to that IP
        $ip = self::publicIp($host);
        if (! $ip) {
            Log::warning('Builder lead webhook skipped: host is not public', ['host' => $host]);

            return null;
        }

        return Http::withOptions([
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$host}:443:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
        ])->withHeaders([
            'X-Owlvix-Event' => 'lead',
            'User-Agent' => 'Owlvix-Webhook/1.0 (+https://owlvix.com/landing-page-builder)',
        ])->timeout(self::TIMEOUT)->connectTimeout(2)->post($url, [
            'email' => $lead['email'],
            'name' => $lead['name'] ?? null,
            'phone' => $lead['phone'] ?? null,
            'funnel' => $lead['funnel'] ?? null,
            'step' => $lead['step'] ?? null,
            'variant' => $lead['variant'] ?? null,
            'created_at' => $lead['created_at'],
        ]);
    }
}
