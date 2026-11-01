<?php

namespace App\Support\Stats;

use App\Models\PageView;
use App\Support\FreeTools;
use App\Support\Guides;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Growth: how visitors reach each free builder and how far they get. site.js sends one message per builder,
 * event and browser session; we keep daily counts per builder, event and where the visitor came from.
 */
class BuilderStats
{
    public const EVENTS = ['open', 'design', 'save', 'publish', 'export'];

    private const SEARCH = '/(^|\.)(google|bing|duckduckgo|yahoo|ecosia|yandex|baidu|brave|startpage)\./';

    /** Turn what the browser sends (a path on this site, "ext:host", "design:key" or "direct") into a source. */
    public static function source(string $from): string
    {
        if (preg_match('/^design:([a-z0-9]{1,30})$/', $from, $m)) {
            return 'design:'.$m[1];
        }
        if (str_starts_with($from, 'ext:')) {
            return preg_match(self::SEARCH, strtolower(substr($from, 4))) ? 'search' : 'other';
        }
        if ($from === '' || $from === 'direct') {
            return 'direct';
        }
        $path = '/'.trim(strtok($from, '?#'), '/');
        foreach (Guides::ALL as $route => $g) {
            if ($g[0] === $path) {
                return 'guide:'.$route;
            }
        }

        return match ($path) {
            '/examples' => 'examples',
            '/guides' => 'guides',
            '/free-tools' => 'free-tools',
            '/' => 'home',
            default => 'site',
        };
    }

    public static function record(string $builder, string $event, string $source): void
    {
        DB::table('builder_steps')->upsert(
            [['day' => now()->toDateString(), 'builder' => $builder, 'event' => $event, 'source' => $source, 'hits' => 1]],
            ['day', 'builder', 'event', 'source'],
            ['hits' => DB::raw('hits + 1')],
        );

        if (random_int(1, 500) === 1) {
            DB::table('builder_steps')->where('day', '<', now()->subMonths(13)->toDateString())->delete();
        }
    }

    /** Everything the Growth page shows, for the last $days days. */
    public static function build(int $days): array
    {
        $since = now()->subDays($days - 1)->toDateString();
        $rows = DB::table('builder_steps')->where('day', '>=', $since)
            ->selectRaw('builder, event, source, SUM(hits) as n')->groupBy('builder', 'event', 'source')->get();

        $empty = array_fill_keys(self::EVENTS, 0);
        $names = array_column(FreeTools::builders(), 'name', 'route');

        // Where people came from (guides and gallery designs first, as those are what we're measuring)
        $bySource = [];
        foreach ($rows as $r) {
            $bySource[$r->source] ??= ['source' => $r->source, 'label' => self::label($r->source), 'url' => self::url($r->source), 'visitors' => null] + $empty;
            $bySource[$r->source][$r->event] += (int) $r->n;
        }
        foreach (Guides::ALL as $route => $g) { // every guide shows, even before anyone has opened a builder from it
            $bySource['guide:'.$route] ??= ['source' => 'guide:'.$route, 'label' => self::label('guide:'.$route), 'url' => route($route), 'visitors' => null] + $empty;
        }
        $paths = [];
        foreach ($bySource as $key => $s) {
            if (str_starts_with($key, 'guide:')) {
                $paths[$key] = Guides::ALL[substr($key, 6)][0] ?? null;
            } elseif ($key === 'examples' || $key === 'guides') {
                $paths[$key] = '/'.$key;
            }
        }
        $visits = PageView::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())->whereIn('path', array_filter($paths))
            ->selectRaw('path, COUNT(DISTINCT visitor) as n')->groupBy('path')->pluck('n', 'path');
        foreach ($paths as $key => $path) {
            $bySource[$key]['visitors'] = (int) ($visits[$path] ?? 0);
        }
        $order = fn ($s) => match (true) { str_starts_with($s['source'], 'guide:') => 0, str_starts_with($s['source'], 'design:') => 2, $s['source'] === 'examples' => 1, default => 3 };
        uasort($bySource, fn ($a, $b) => [$order($a), -($a['visitors'] ?? 0), -$a['open'], $a['label']] <=> [$order($b), -($b['visitors'] ?? 0), -$b['open'], $b['label']]);

        // Each builder
        $byBuilder = [];
        foreach ($rows as $r) {
            $byBuilder[$r->builder] ??= ['builder' => $r->builder, 'label' => $names[$r->builder] ?? $r->builder, 'url' => self::routeUrl($r->builder)] + $empty;
            $byBuilder[$r->builder][$r->event] += (int) $r->n;
        }
        uasort($byBuilder, fn ($a, $b) => $b['open'] <=> $a['open']);

        $totals = $empty;
        foreach ($rows as $r) {
            $totals[$r->event] += (int) $r->n;
        }
        $fromContent = collect($bySource)->filter(fn ($s) => preg_match('/^(guide:|design:|examples$|guides$)/', $s['source']))->sum('open');

        return ['days' => $days, 'totals' => $totals, 'fromContent' => $fromContent, 'bySource' => array_values($bySource), 'byBuilder' => array_values($byBuilder)];
    }

    public static function label(string $source): string
    {
        if (str_starts_with($source, 'guide:')) {
            return 'Guide: '.(Guides::ALL[substr($source, 6)][2] ?? substr($source, 6));
        }
        if (str_starts_with($source, 'design:')) {
            return 'Gallery design: '.ucfirst(substr($source, 7));
        }

        return [
            'examples' => 'Example designs page', 'guides' => 'Guides page', 'free-tools' => 'Free tools page', 'home' => 'Home page',
            'site' => 'Other pages on owlvix.com', 'search' => 'Search engines', 'other' => 'Other websites', 'direct' => 'Direct (typed or bookmarked)',
        ][$source] ?? $source;
    }

    private static function url(string $source): ?string
    {
        if (str_starts_with($source, 'guide:')) {
            return self::routeUrl(substr($source, 6));
        }

        return in_array($source, ['examples', 'guides', 'free-tools'], true) ? self::routeUrl($source) : null;
    }

    private static function routeUrl(string $name): ?string
    {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name) : null;
    }

    /** 7, 30 or 90 days. */
    public static function days(mixed $days): int
    {
        return in_array((int) $days, [7, 30, 90], true) ? (int) $days : 30;
    }
}
