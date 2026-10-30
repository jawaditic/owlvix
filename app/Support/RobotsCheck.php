<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free robots.txt & AI crawler checker (/robots-txt-checker): reads robots.txt and the sitemap, tests whether the
 * page can be crawled and indexed, and shows which search and AI bots are allowed. Rules are matched the way
 * Google does: the most specific user-agent group, then the longest matching rule (allow wins a tie), * and $.
 */
class RobotsCheck
{
    /** [user-agent token, who, what it does, kind: search|ai-search|ai-training] */
    public const BOTS = [
        ['Googlebot', 'Google', 'Google Search', 'search'],
        ['Bingbot', 'Microsoft', 'Bing and Copilot', 'search'],
        ['OAI-SearchBot', 'OpenAI', 'ChatGPT search results', 'ai-search'],
        ['ChatGPT-User', 'OpenAI', 'ChatGPT opening a link for a user', 'ai-search'],
        ['Claude-SearchBot', 'Anthropic', 'Claude search results', 'ai-search'],
        ['Claude-User', 'Anthropic', 'Claude opening a link for a user', 'ai-search'],
        ['PerplexityBot', 'Perplexity', 'Perplexity answers', 'ai-search'],
        ['GPTBot', 'OpenAI', 'Training OpenAI models', 'ai-training'],
        ['ClaudeBot', 'Anthropic', 'Training Claude models', 'ai-training'],
        ['Google-Extended', 'Google', 'Training Gemini models', 'ai-training'],
        ['Applebot-Extended', 'Apple', 'Training Apple AI', 'ai-training'],
        ['meta-externalagent', 'Meta', 'Training Meta AI', 'ai-training'],
        ['CCBot', 'Common Crawl', 'Open dataset used to train many AI models', 'ai-training'],
        ['Bytespider', 'ByteDance', 'Training TikTok / Doubao AI', 'ai-training'],
    ];

    public static function run(string $url): array
    {
        if ($c = Cache::get('robots:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        $page = SafeFetch::get($url);
        if (! $page['ok']) {
            return $page;
        }
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $page['url']);
        $robots = SafeFetch::get($origin.'/robots.txt', 10);
        $txt = $robots['ok'] && $robots['status'] === 200 && ! str_contains(strtolower(implode(',', $robots['headers']['content-type'] ?? [])), 'html') ? $robots['body'] : null;
        $groups = self::parse($txt ?? '');
        $sitemapUrl = $groups['sitemaps'][0] ?? $origin.'/sitemap.xml';
        $sitemap = SafeFetch::get($sitemapUrl, 10);
        $report = self::analyse($page['url'], $page, $robots['ok'] ? $robots['status'] : 0, $txt, $groups, $sitemapUrl, $sitemap);
        Cache::put('robots:'.sha1($url), $report, now()->addMinutes(30));

        return ['ok' => true, 'report' => $report];
    }

    /** ['groups' => [['agents' => [...], 'rules' => [[allow bool, path]]]], 'sitemaps' => [...]] */
    public static function parse(string $txt): array
    {
        $groups = [];
        $sitemaps = [];
        $current = null;
        $lastWasAgent = false;
        foreach (preg_split('/\r\n|\r|\n/', $txt) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if (! preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $line, $m)) {
                continue;
            }
            [$key, $val] = [strtolower($m[1]), trim($m[2])];
            if ($key === 'user-agent') {
                if (! $lastWasAgent) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = count($groups) - 1;
                }
                $groups[$current]['agents'][] = strtolower($val);
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;
            if ($key === 'sitemap' && $val !== '') {
                $sitemaps[] = $val;
            } elseif (($key === 'allow' || $key === 'disallow') && $current !== null && $val !== '') {
                $groups[$current]['rules'][] = [$key === 'allow', $val];
            }
        }

        return ['groups' => $groups, 'sitemaps' => array_values(array_unique($sitemaps))];
    }

    /** Can this bot fetch this path? Returns [allowed bool, matching rule or null]. */
    public static function allowed(array $parsed, string $bot, string $path): array
    {
        $bot = strtolower($bot);
        $rules = null;
        foreach ($parsed['groups'] as $g) {
            if (in_array($bot, $g['agents'], true)) {
                $rules = array_merge($rules ?? [], $g['rules']);
            }
        }
        if ($rules === null) {
            foreach ($parsed['groups'] as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $rules = array_merge($rules ?? [], $g['rules']);
                }
            }
        }
        $best = null;
        foreach ($rules ?? [] as [$allow, $pattern]) {
            $re = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';
            if (preg_match($re, $path)) {
                $len = strlen($pattern);
                if (! $best || $len > $best[2] || ($len === $best[2] && $allow)) {
                    $best = [$allow, $pattern, $len];
                }
            }
        }

        return $best ? [$best[0], ($best[0] ? 'Allow: ' : 'Disallow: ').$best[1]] : [true, null];
    }

    public static function analyse(string $url, array $page, int $robotsStatus, ?string $txt, array $parsed, string $sitemapUrl, array $sitemap): array
    {
        $checks = [];
        $add = function (string $cat, string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = compact('cat', 'title', 'status', 'found', 'tip');
        };
        $p = parse_url($url);
        $path = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');

        // ---------- robots.txt ----------
        if ($robotsStatus >= 500 || $robotsStatus === 0) {
            $add('robots.txt', 'robots.txt file', 'fail', $robotsStatus ? "robots.txt returns a server error ($robotsStatus) — Google pauses crawling the whole site when this happens." : 'robots.txt did not respond.',
                'Make sure /robots.txt loads (even an empty file is fine).');
        } elseif ($txt === null) {
            $add('robots.txt', 'robots.txt file', 'warn', 'No robots.txt file. Everything can be crawled, but you cannot point bots to your sitemap.',
                'Add a robots.txt with "User-agent: *", "Allow: /" and a "Sitemap:" line.');
        } else {
            $add('robots.txt', 'robots.txt file', 'pass', 'robots.txt found ('.count($parsed['groups']).' rule group(s)).');
        }
        [$siteOk] = self::allowed($parsed, 'Googlebot', '/');
        if (! $siteOk) {
            $add('robots.txt', 'Whole site', 'fail', 'Google is blocked from your whole site ("Disallow: /").', 'Remove "Disallow: /" — this is common after a site moves from staging to live.');
        }
        [$pageOk, $rule] = self::allowed($parsed, 'Googlebot', $path);
        $add('robots.txt', 'This page', $pageOk ? 'pass' : 'fail', $pageOk ? "Google may crawl $path".($rule ? " ($rule)." : '.') : "Google is blocked from $path by \"$rule\".",
            $pageOk ? '' : 'Remove or narrow that rule if this page should appear in Google.');

        // ---------- Indexing ----------
        $body = $page['body'] ?? '';
        $metaNoindex = preg_match('#<meta[^>]+name=["\'](robots|googlebot)["\'][^>]+content=["\'][^"\']*noindex#i', $body) || preg_match('#<meta[^>]+content=["\'][^"\']*noindex[^"\']*["\'][^>]+name=["\'](robots|googlebot)["\']#i', $body);
        $headerNoindex = str_contains(strtolower(implode(',', $page['headers']['x-robots-tag'] ?? [])), 'noindex');
        $add('Indexing', 'Can appear in Google', ($metaNoindex || $headerNoindex) ? 'fail' : 'pass',
            $metaNoindex ? 'The page has a "noindex" robots meta tag — Google will drop it from search.' : ($headerNoindex ? 'The server sends "X-Robots-Tag: noindex" — Google will drop this page.' : 'No "noindex" found — the page can be indexed.'),
            ($metaNoindex || $headerNoindex) ? 'Remove noindex if this page should be found on Google (check your SEO plugin\'s settings).' : '');
        if ($metaNoindex && ! $pageOk) {
            $add('Indexing', 'Blocked + noindex', 'warn', 'The page is blocked in robots.txt, so Google cannot even see its noindex tag.', 'To remove a page from Google, allow crawling and keep noindex.');
        }

        // ---------- Sitemap ----------
        $listed = (bool) $parsed['sitemaps'];
        $sm = $sitemap['ok'] && $sitemap['status'] === 200 ? $sitemap['body'] : '';
        $count = preg_match_all('#<url>#i', $sm);
        $index = preg_match_all('#<sitemap>#i', $sm);
        if ($count || $index) {
            $add('Sitemap', 'XML sitemap', 'pass', ($index ? "Sitemap index with $index sitemaps" : "Sitemap with $count pages").' at '.$sitemapUrl.'.');
        } else {
            $add('Sitemap', 'XML sitemap', 'fail', 'No working XML sitemap found at '.$sitemapUrl.'.', 'Create a sitemap.xml (most CMSs and SEO plugins make one) and submit it in Google Search Console.');
        }
        $add('Sitemap', 'Sitemap in robots.txt', $listed ? 'pass' : 'warn', $listed ? 'robots.txt points bots to your sitemap.' : 'robots.txt does not mention your sitemap.',
            $listed ? '' : 'Add a line: Sitemap: '.preg_replace('#^(https?://[^/]+).*$#', '$1', $url).'/sitemap.xml');

        // ---------- AI & search bots ----------
        $bots = array_map(function ($b) use ($parsed) {
            [$ok, $rule] = self::allowed($parsed, $b[0], '/');

            return ['bot' => $b[0], 'owner' => $b[1], 'purpose' => $b[2], 'kind' => $b[3], 'allowed' => $ok, 'rule' => $rule,
                'named' => (bool) array_filter($parsed['groups'], fn ($g) => in_array(strtolower($b[0]), $g['agents'], true))];
        }, self::BOTS);
        $blockedSearch = array_filter($bots, fn ($b) => $b['kind'] === 'ai-search' && ! $b['allowed']);
        $add('AI search', 'AI answer engines', $blockedSearch ? 'warn' : 'pass',
            $blockedSearch ? 'Blocked: '.implode(', ', array_column($blockedSearch, 'bot')).' — your site cannot be shown or cited in those AI answers.' : 'ChatGPT, Claude and Perplexity search bots can read your site, so it can be cited in AI answers.',
            $blockedSearch ? 'Allow these search bots if you want traffic from AI assistants. Blocking training bots (GPTBot, ClaudeBot…) is a separate choice.' : '');

        $status = array_column($checks, 'status');
        $rating = in_array('fail', $status, true) ? 'poor' : (in_array('warn', $status, true) ? 'average' : 'good'); // headline matches the worst finding
        $weights = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];
        $score = (int) round(100 * array_sum(array_map(fn ($c) => $weights[$c['status']], $checks)) / max(1, count($checks)));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);

        return [
            'url' => $url,
            'final_url' => $url,
            'robots' => $txt === null ? null : mb_substr($txt, 0, 6000),
            'bots' => $bots,
            'score' => $score,
            'rating' => $rating,
            'summary' => match ($rating) {
                'good' => 'Search engines and AI assistants can find and read this page.',
                'average' => 'Mostly fine, but some settings make it harder for Google or AI assistants to find your pages.',
                default => 'Something is stopping Google from crawling or indexing this page. Fix the red items first.',
            },
            'counts' => array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0],
            'checks' => $checks,
        ];
    }
}
