<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Receives one small "page viewed" message from site.js and saves it for Admin → Traffic.
 * Privacy: no cookies and no IP addresses are stored. The visitor id is a hash of
 * (IP + browser + today's date + the app's secret), so it can't be reversed and changes daily.
 */
class TrackController extends Controller
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime/i';

    public function store(Request $request)
    {
        $ua = (string) $request->userAgent();

        // Skip bots, link-preview fetchers, speed tests and your own visits while logged in to the admin
        if ($ua === '' || preg_match(self::BOTS, $ua) || $request->session()->get('is_admin')) {
            return response()->noContent();
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:191'],
            'ref' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'new' => ['nullable', 'boolean'],
            'utm' => ['nullable', 'string', 'max:60'],
        ]);

        $refHost = $this->host($data['ref'] ?? null);
        if ($refHost === $request->getHost()) {
            $refHost = null; // moving around the site, not a new source
        }

        PageView::create([
            'visitor' => substr(hash('sha256', config('app.key').'|'.now()->toDateString().'|'.$request->ip().'|'.$ua), 0, 16),
            'path' => '/'.ltrim(Str::before($data['path'], '?'), '/'),
            'country' => isset($data['country']) ? strtolower($data['country']) : null,
            'source' => $this->source($refHost, $data['utm'] ?? null),
            'referrer_host' => $refHost,
            'device' => $this->device($ua),
            'browser' => $this->browser($ua),
            'is_new' => (bool) ($data['new'] ?? true),
        ]);

        // Keep about 13 months of data; tidy up now and then
        if (random_int(1, 200) === 1) {
            PageView::where('created_at', '<', now()->subMonths(13))->delete();
        }

        return response()->noContent();
    }

    private function host(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? Str::limit(preg_replace('/^www\./', '', strtolower($host)), 120, '') : null;
    }

    /** Turn the referring site (or ?utm_source=) into a friendly source name. */
    private function source(?string $host, ?string $utm): string
    {
        $key = strtolower($utm ?: (string) $host);
        $map = [
            'google' => 'Google', 'bing' => 'Bing', 'yahoo' => 'Yahoo', 'duckduckgo' => 'DuckDuckGo',
            'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'facebook' => 'Facebook', 'fb.' => 'Facebook',
            'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn',
            't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'x.com' => 'X (Twitter)',
            'fiverr' => 'Fiverr', 'upwork' => 'Upwork', 'youtube' => 'YouTube', 'reddit' => 'Reddit',
            'chatgpt' => 'ChatGPT', 'openai' => 'ChatGPT', 'perplexity' => 'Perplexity', 'claude.ai' => 'Claude',
            'mail' => 'Email', 'outlook' => 'Email', 'email' => 'Email', 'anjtec' => 'anjtec.net',
        ];
        if ($key === '') {
            return 'Direct';
        }
        foreach ($map as $needle => $name) {
            if (str_contains($key, $needle)) {
                return $name;
            }
        }

        return Str::limit($utm ?: $host, 40, '');
    }

    private function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) {
            return 'tablet';
        }

        return preg_match('/Mobi|iPhone|Android/i', $ua) ? 'mobile' : 'desktop';
    }

    private function browser(string $ua): ?string
    {
        foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Firefox/' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                return $name;
            }
        }

        return 'Other';
    }
}
