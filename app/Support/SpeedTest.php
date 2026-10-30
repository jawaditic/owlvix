<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Free website speed test (/website-speed-test). Runs Google PageSpeed Insights (Lighthouse) for a URL
 * and turns the large response into a small report: score, key timings, real-user data, top fixes.
 * Results are cached for an hour per URL + device, so repeated tests are instant and cheap.
 */
class SpeedTest
{
    private const API = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** Lab metrics shown in the report: audit id => [label, what it means, unit]. */
    private const ABBR = [
        'largest-contentful-paint' => 'LCP', 'first-contentful-paint' => 'FCP', 'total-blocking-time' => 'TBT',
        'cumulative-layout-shift' => 'CLS', 'speed-index' => 'SI', 'server-response-time' => 'TTFB',
    ];

    private const METRICS = [
        'largest-contentful-paint' => ['Largest Contentful Paint', 'When the main content (usually the biggest image or heading) has appeared.', 'ms'],
        'first-contentful-paint' => ['First Contentful Paint', 'When the first text or image appears on screen.', 'ms'],
        'total-blocking-time' => ['Total Blocking Time', 'How long the page is frozen by scripts and cannot respond to taps or clicks.', 'ms'],
        'cumulative-layout-shift' => ['Cumulative Layout Shift', 'How much the page jumps around while it loads. Lower is better.', ''],
        'speed-index' => ['Speed Index', 'How quickly the visible part of the page fills in.', 'ms'],
        'server-response-time' => ['Server response time', 'How long the server takes to start sending the page.', 'ms'],
    ];

    /** Google's thresholds: [good up to, poor from]. */
    private const THRESHOLDS = [
        'largest-contentful-paint' => [2500, 4000],
        'first-contentful-paint' => [1800, 3000],
        'total-blocking-time' => [200, 600],
        'cumulative-layout-shift' => [0.1, 0.25],
        'speed-index' => [3400, 5800],
        'server-response-time' => [800, 1800],
    ];

    /** Make the address a full https URL, or return null if it isn't a usable public web address. */
    public static function normalize(string $input): ?string
    {
        $url = trim($input);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return null;
        }

        return $url;
    }

    /** The cached report for this URL + device, if it was tested in the last hour. */
    public static function cached(string $url, string $strategy): ?array
    {
        return Cache::get('speedtest:'.sha1(($strategy === 'desktop' ? 'desktop' : 'mobile').'|'.$url));
    }

    /**
     * Run (or reuse a cached) test. Returns ['ok' => true, 'report' => [...]] or ['ok' => false, 'error' => '...'].
     */
    public static function run(string $url, string $strategy): array
    {
        $strategy = $strategy === 'desktop' ? 'desktop' : 'mobile';
        $cacheKey = 'speedtest:'.sha1($strategy.'|'.$url);
        if ($cached = Cache::get($cacheKey)) {
            return ['ok' => true, 'report' => $cached + ['cached' => true]];
        }

        $query = ['url' => $url, 'strategy' => $strategy, 'category' => 'performance'];
        if ($key = config('services.pagespeed.key')) {
            $query['key'] = $key;
        }

        try {
            @set_time_limit(150);
            $res = Http::timeout(120)->acceptJson()->get(self::API, $query);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'The test took too long. Please try again in a minute.'];
        }

        if ($res->status() === 429) {
            return ['ok' => false, 'error' => 'Too many tests are running right now. Please try again in a few minutes.'];
        }
        if (! $res->successful()) {
            $msg = (string) data_get($res->json(), 'error.message', '');

            return ['ok' => false, 'error' => str_contains($msg, 'FAILED_DOCUMENT_REQUEST') || str_contains($msg, 'ERRORED_DOCUMENT_REQUEST') || str_contains($msg, 'DNS')
                ? 'We could not load that website. Check the address and that the site is online.'
                : 'Google could not test that page right now. Please check the address and try again.'];
        }

        $report = self::parse($res->json(), $url, $strategy);
        Cache::put($cacheKey, $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    /** Turn the PageSpeed Insights JSON into the report shown on the page. */
    public static function parse(array $json, string $url, string $strategy): array
    {
        $lh = $json['lighthouseResult'] ?? [];
        $audits = $lh['audits'] ?? [];
        $score = (int) round(100 * (float) data_get($lh, 'categories.performance.score', 0));

        $metrics = [];
        foreach (self::METRICS as $id => [$label, $help, $unit]) {
            if (! isset($audits[$id]['numericValue'])) {
                continue;
            }
            $value = (float) $audits[$id]['numericValue'];
            [$good, $poor] = self::THRESHOLDS[$id];
            $metrics[] = [
                'id' => $id, 'abbr' => self::ABBR[$id], 'label' => $label, 'help' => $help,
                'value' => $unit === 'ms' ? self::seconds($value) : number_format($value, 3),
                'rating' => $value <= $good ? 'good' : ($value < $poor ? 'average' : 'poor'),
                'position' => self::position($value, $good, $poor),
            ];
        }

        // Real-user data from Chrome (CrUX), when Google has enough visits for this page
        $field = [];
        $fieldMap = [
            'LARGEST_CONTENTFUL_PAINT_MS' => ['LCP', 'Largest Contentful Paint', fn ($v) => self::seconds($v), 2500, 4000, 1],
            'INTERACTION_TO_NEXT_PAINT' => ['INP', 'Interaction to Next Paint', fn ($v) => round($v).' ms', 200, 500, 1],
            'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['CLS', 'Cumulative Layout Shift', fn ($v) => number_format($v / 100, 2), 0.1, 0.25, 100],
        ];
        foreach ($fieldMap as $key => [$abbr, $label, $fmt, $good, $poor, $div]) {
            $m = data_get($json, "loadingExperience.metrics.$key");
            if ($m && isset($m['percentile'])) {
                $field[] = ['abbr' => $abbr, 'label' => $label, 'value' => $fmt($m['percentile']),
                    'rating' => ['FAST' => 'good', 'AVERAGE' => 'average', 'SLOW' => 'poor'][$m['category'] ?? ''] ?? 'average',
                    'position' => self::position($m['percentile'] / $div, $good, $poor)];
            }
        }
        $fieldOverall = data_get($json, 'loadingExperience.overall_category');

        // Biggest wins: audits that failed and would save the most time
        $fixes = [];
        foreach ($audits as $id => $a) {
            $score_ = $a['score'] ?? null;
            if ($score_ === null || $score_ >= 0.9 || ($a['scoreDisplayMode'] ?? '') === 'informative') {
                continue;
            }
            $savings = (float) (data_get($a, 'details.overallSavingsMs') ?? max(array_values($a['metricSavings'] ?? [0]) ?: [0]));
            if ($savings < 50) {
                continue;
            }
            $fixes[] = [
                'title' => $a['title'] ?? $id,
                'description' => self::plain($a['description'] ?? ''),
                'savings' => self::seconds($savings),
                'savings_ms' => $savings,
            ];
        }
        usort($fixes, fn ($x, $y) => $y['savings_ms'] <=> $x['savings_ms']);
        $max = $fixes[0]['savings_ms'] ?? 1;
        foreach ($fixes as &$f) {
            $f['impact'] = $f['savings_ms'] >= 1000 ? 'high' : ($f['savings_ms'] >= 300 ? 'medium' : 'low');
            $f['share'] = max(6, (int) round(100 * $f['savings_ms'] / $max));
        }
        unset($f);

        return [
            'url' => $url,
            'final_url' => data_get($lh, 'finalDisplayedUrl', data_get($lh, 'finalUrl', $url)),
            'strategy' => $strategy,
            'score' => $score,
            'rating' => $score >= 90 ? 'good' : ($score >= 50 ? 'average' : 'poor'),
            'summary' => self::summary($score),
            'metrics' => $metrics,
            'field' => $field,
            'field_overall' => $fieldOverall,
            'fixes' => array_slice(array_map(fn ($f) => array_diff_key($f, ['savings_ms' => 1]), $fixes), 0, 6),
            'screenshot' => data_get($audits, 'final-screenshot.details.data'),
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /** Where a value sits on Google's scale, 0–100: good zone 0–33, needs improvement 33–66, poor 66–100. */
    private static function position(float $v, float $good, float $poor): int
    {
        $p = match (true) {
            $v <= $good => 33 * $v / max($good, 1e-9),
            $v < $poor => 33 + 33 * ($v - $good) / ($poor - $good),
            default => 66 + 34 * min(1, ($v - $poor) / $poor),
        };

        return (int) round(max(2, min(98, $p)));
    }

    private static function seconds(float $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 1).' s' : round($ms).' ms';
    }

    /** Lighthouse descriptions contain markdown links: keep the text, drop the URLs. */
    private static function plain(string $md): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $md);

        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    private static function summary(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Great — this page is fast. Small tweaks can still help, but visitors and Google are happy.',
            $score >= 50 => 'This page loads, but it is slower than it should be. Visitors on phones may leave before it finishes, and it can hold back your Google rankings.',
            default => 'This page is slow. Many visitors will leave before it loads, and Google ranks slow pages lower. The fixes below would make a big difference.',
        };
    }
}
