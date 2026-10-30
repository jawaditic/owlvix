<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free SEO checker (/seo-checker). Fetches a page (SafeFetch) plus its robots.txt and sitemap, runs on-page
 * and technical checks, and returns a score with a plain-English tip for every problem.
 * Results are cached for an hour per URL.
 */
class SeoCheck
{
    /** Category weights in the score. */
    private const WEIGHT = ['fail' => 0, 'warn' => 0.5, 'pass' => 1];

    public static function cached(string $url): ?array
    {
        return Cache::get('seocheck:'.sha1($url));
    }

    /** @return array{ok: bool, report?: array, error?: string} */
    public static function run(string $url): array
    {
        if ($c = self::cached($url)) {
            return ['ok' => true, 'report' => $c + ['cached' => true]];
        }
        @set_time_limit(90);

        $page = SafeFetch::get($url);
        if (! $page['ok']) {
            return ['ok' => false, 'error' => $page['error']];
        }
        if ($page['status'] >= 400) {
            return ['ok' => false, 'error' => "That page answered with an error (HTTP {$page['status']}). Check the address and try again."];
        }
        $type = (string) ($page['headers']['content-type'][0] ?? '');
        if ($type && ! str_contains($type, 'html')) {
            return ['ok' => false, 'error' => 'That address is not a web page (it returned '.strtok($type, ';').').'];
        }

        $origin = preg_replace('#^(https?://[^/]+).*#i', '$1', $page['url']);
        $robots = SafeFetch::get($origin.'/robots.txt', 8);
        $report = self::analyse($url, $page, $robots, $origin);
        Cache::put('seocheck:'.sha1($url), $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    /** All checks. Public so tests can feed fixed HTML. */
    public static function analyse(string $url, array $page, array $robots, string $origin): array
    {
        $html = $page['body'];
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $q = fn (string $path) => $xp->query($path);
        $attr = function (string $path, string $name) use ($q) {
            $n = $q($path)->item(0);

            return $n ? trim((string) $n->getAttribute($name)) : null;
        };
        $text = fn (?\DOMNode $n) => $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : '';

        $checks = [];
        $add = function (string $cat, string $title, string $status, string $found, string $tip = '') use (&$checks) {
            $checks[] = compact('cat', 'title', 'status', 'found', 'tip');
        };
        $final = $page['url'];
        $headers = $page['headers'];

        // ---------- Content ----------
        $title = $text($q('//head/title')->item(0) ?: $q('//title')->item(0));
        $tl = mb_strlen($title);
        if ($tl === 0) {
            $add('Content', 'Page title', 'fail', 'No <title> tag found.', 'Add a unique title of 50–60 characters that starts with your main keyword, e.g. "Web App Development Services | Your Brand".');
        } elseif ($tl < 30 || $tl > 60) {
            $add('Content', 'Page title', 'warn', "\"$title\" ($tl characters).", $tl < 30 ? 'Your title is short. Use 50–60 characters and include your main keyword and brand.' : 'Google cuts titles after about 60 characters. Shorten it and put the most important words first.');
        } else {
            $add('Content', 'Page title', 'pass', "\"$title\" ($tl characters).");
        }

        $desc = (string) $attr('//meta[translate(@name,"DESCRIPTION","description")="description"]', 'content');
        $dl = mb_strlen($desc);
        if ($dl === 0) {
            $add('Content', 'Meta description', 'fail', 'No meta description.', 'Write a 140–160 character summary of the page with a clear benefit and call to action. Google often shows it under your title.');
        } elseif ($dl < 70 || $dl > 165) {
            $add('Content', 'Meta description', 'warn', "$dl characters.", $dl < 70 ? 'Your description is short. Aim for 140–160 characters that explain what the visitor gets.' : 'Google cuts descriptions after about 160 characters. Shorten it so the key message is not lost.');
        } else {
            $add('Content', 'Meta description', 'pass', "$dl characters.");
        }

        $h1s = $q('//h1');
        $h1Text = $text($h1s->item(0));
        if ($h1s->length === 0) {
            $add('Content', 'H1 heading', 'fail', 'No H1 heading.', 'Add one H1 that says what the page is about, using your main keyword naturally.');
        } elseif ($h1s->length > 1) {
            $add('Content', 'H1 heading', 'warn', "{$h1s->length} H1 headings.", 'Use exactly one H1 per page; turn the others into H2 subheadings.');
        } else {
            $add('Content', 'H1 heading', 'pass', '"'.mb_strimwidth($h1Text, 0, 80, '…').'"');
        }

        $h2 = $q('//h2')->length;
        $add('Content', 'Subheadings (H2)', $h2 > 0 ? 'pass' : 'warn', "$h2 H2 headings.", $h2 ? '' : 'Break the page into sections with H2 subheadings. They help readers scan and help Google understand your topics.');

        $bodyNode = $q('//body')->item(0);
        $clone = $bodyNode ? $bodyNode->cloneNode(true) : null;
        if ($clone) {
            foreach (['script', 'style', 'noscript', 'svg'] as $tag) {
                foreach (iterator_to_array($clone->getElementsByTagName($tag)) as $n) {
                    $n->parentNode->removeChild($n);
                }
            }
        }
        $words = $clone ? str_word_count(strip_tags($text($clone))) : 0;
        $add('Content', 'Amount of text', $words >= 300 ? 'pass' : ($words >= 150 ? 'warn' : 'fail'), number_format($words).' words.',
            $words >= 300 ? '' : 'Pages with very little text rarely rank. Explain your service in at least 300–600 words: what it is, who it is for, how it works, prices and FAQs.');

        $imgs = $q('//img');
        $noAlt = 0;
        foreach ($imgs as $img) {
            if (trim((string) $img->getAttribute('alt')) === '' && $img->getAttribute('role') !== 'presentation') {
                $noAlt++;
            }
        }
        $add('Content', 'Image alt text', $imgs->length === 0 || $noAlt === 0 ? 'pass' : ($noAlt / max(1, $imgs->length) > .3 ? 'fail' : 'warn'),
            $imgs->length ? "$noAlt of {$imgs->length} images have no alt text." : 'No images on the page.',
            $noAlt ? 'Describe each meaningful image in its alt attribute (e.g. alt="Dentist chair in our London clinic"). It helps image search and screen readers.' : '');

        $internal = $external = 0;
        $host = parse_url($final, PHP_URL_HOST);
        foreach ($q('//a[@href]') as $a) {
            $h = trim($a->getAttribute('href'));
            if ($h === '' || str_starts_with($h, '#') || preg_match('#^(mailto|tel|javascript):#i', $h)) {
                continue;
            }
            $lh = parse_url($h, PHP_URL_HOST);
            (! $lh || $lh === $host || preg_replace('/^www\./', '', $lh) === preg_replace('/^www\./', '', (string) $host)) ? $internal++ : $external++;
        }
        $add('Content', 'Internal links', $internal >= 3 ? 'pass' : 'warn', "$internal internal, $external external links.",
            $internal >= 3 ? '' : 'Link to your other important pages (services, about, contact) with descriptive link text. It spreads ranking power and helps Google find pages.');

        // ---------- Technical ----------
        $https = str_starts_with($final, 'https://');
        $add('Technical', 'HTTPS', $https ? 'pass' : 'fail', $https ? 'The page is served securely.' : 'The page is not on HTTPS.', $https ? '' : 'Install an SSL certificate (free with most hosts or Let\'s Encrypt) and redirect all http:// traffic to https://.');

        $robotsMeta = strtolower((string) $attr('//meta[translate(@name,"ROBTS","robts")="robots"]', 'content'));
        $xRobots = strtolower(implode(',', $headers['x-robots-tag'] ?? []));
        $noindex = str_contains($robotsMeta, 'noindex') || str_contains($xRobots, 'noindex');
        $add('Technical', 'Indexable by Google', $noindex ? 'fail' : 'pass', $noindex ? 'The page tells search engines not to index it (noindex).' : 'No noindex tag found.',
            $noindex ? 'Remove the "noindex" robots tag (or X-Robots-Tag header) if you want this page to appear in Google. In WordPress check Settings → Reading → "Discourage search engines".' : '');

        $canonical = $attr('//link[translate(@rel,"CANOICL","canoicl")="canonical"]', 'href');
        $add('Technical', 'Canonical tag', $canonical ? 'pass' : 'warn', $canonical ? 'Points to '.mb_strimwidth($canonical, 0, 70, '…') : 'No canonical tag.',
            $canonical ? '' : 'Add <link rel="canonical" href="…"> with the page\'s preferred address, so versions with ?tracking=… or www/non-www are not seen as duplicates.');

        $viewport = $attr('//meta[@name="viewport"]', 'content');
        $add('Technical', 'Mobile friendly (viewport)', $viewport ? 'pass' : 'fail', $viewport ? 'Viewport tag present.' : 'No viewport tag.',
            $viewport ? '' : 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> so the page fits phones. Google ranks the mobile version of your site.');

        $lang = $attr('//html', 'lang');
        $add('Technical', 'Language', $lang ? 'pass' : 'warn', $lang ? "lang=\"$lang\"" : 'No lang attribute on <html>.', $lang ? '' : 'Add the page language, e.g. <html lang="en">. It helps search engines and screen readers.');

        $hasSchema = $q('//script[@type="application/ld+json"]')->length > 0 || $q('//*[@itemtype]')->length > 0;
        $add('Technical', 'Structured data (schema)', $hasSchema ? 'pass' : 'warn', $hasSchema ? 'Structured data found.' : 'No structured data found.',
            $hasSchema ? '' : 'Add JSON-LD schema (Organization or LocalBusiness, Product, FAQ, Article) so Google and AI assistants understand your business and can show richer results.');

        $robotsOk = $robots['ok'] && $robots['status'] === 200 && ! str_contains(strtolower(substr($robots['body'] ?? '', 0, 200)), '<html');
        $blockAll = $robotsOk && preg_match('/user-agent:\s*\*[^#]*?\n\s*disallow:\s*\/\s*(\n|$)/i', $robots['body']);
        $add('Technical', 'robots.txt', $blockAll ? 'fail' : ($robotsOk ? 'pass' : 'warn'),
            $blockAll ? 'robots.txt blocks the whole site.' : ($robotsOk ? 'Found.' : 'No robots.txt file.'),
            $blockAll ? 'Your robots.txt says "Disallow: /" for all bots, which stops search engines crawling your site. Remove that line.' : ($robotsOk ? '' : 'Add a robots.txt file at the root of your site that allows crawling and links to your sitemap.'));

        $sitemapUrl = null;
        if ($robotsOk && preg_match('/^\s*sitemap:\s*(\S+)/im', $robots['body'], $m)) {
            $sitemapUrl = $m[1];
        }
        $sitemap = SafeFetch::get($sitemapUrl ?: $origin.'/sitemap.xml', 8);
        $sitemapOk = $sitemap['ok'] && $sitemap['status'] === 200 && preg_match('/<(urlset|sitemapindex)/i', substr($sitemap['body'] ?? '', 0, 2000));
        $add('Technical', 'XML sitemap', $sitemapOk ? 'pass' : 'warn', $sitemapOk ? 'Found'.($sitemapUrl ? ' (listed in robots.txt).' : '.') : 'No sitemap found.',
            $sitemapOk ? '' : 'Create an XML sitemap listing your pages (most CMSs and SEO plugins do this), add it to robots.txt, and submit it in Google Search Console.');

        $kb = (int) round(strlen($html) / 1024);
        $ms = $page['ms'];
        $add('Technical', 'Server response', $ms < 800 ? 'pass' : ($ms < 1800 ? 'warn' : 'fail'), "Page downloaded in {$ms} ms ({$kb} KB of HTML).",
            $ms < 800 ? '' : 'Your server is slow to respond. Use page caching, a faster host or a CDN. Run our free speed test for a full breakdown.');

        // Guzzle unpacks gzip/br itself and moves the original header to x-encoded-content-encoding
        $enc = strtolower(implode(',', $headers['content-encoding'] ?? $headers['x-encoded-content-encoding'] ?? []));
        $add('Technical', 'Compression', $enc ? 'pass' : 'warn', $enc ? "Compressed ($enc)." : 'Not compressed.', $enc ? '' : 'Turn on gzip or Brotli compression on your server to make pages up to 70% smaller.');

        $add('Technical', 'Redirects', $page['redirects'] <= 1 ? 'pass' : 'warn', $page['redirects'] ? "{$page['redirects']} redirect(s) before the page loaded." : 'No redirects.',
            $page['redirects'] <= 1 ? '' : 'Each redirect adds delay. Link straight to the final address and redirect http/www variants in a single step.');

        // ---------- Social & sharing ----------
        $og = ['og:title' => $attr('//meta[@property="og:title"]', 'content'), 'og:description' => $attr('//meta[@property="og:description"]', 'content'), 'og:image' => $attr('//meta[@property="og:image"]', 'content')];
        $missing = array_keys(array_filter($og, fn ($v) => ! $v));
        $add('Social', 'Open Graph tags', $missing ? (count($missing) === 3 ? 'fail' : 'warn') : 'pass', $missing ? 'Missing: '.implode(', ', $missing).'.' : 'Title, description and image set.',
            $missing ? 'Add og:title, og:description and og:image so links to your site look good on WhatsApp, Facebook and LinkedIn.' : '');
        $tw = $attr('//meta[@name="twitter:card"]', 'content');
        $add('Social', 'Twitter / X card', $tw ? 'pass' : 'warn', $tw ? "twitter:card = $tw" : 'No twitter:card tag.', $tw ? '' : 'Add <meta name="twitter:card" content="summary_large_image"> for a large preview on X.');
        $icon = $q('//link[contains(translate(@rel,"ICON","icon"),"icon")]')->length > 0;
        $add('Social', 'Favicon', $icon ? 'pass' : 'warn', $icon ? 'Favicon found.' : 'No favicon link.', $icon ? '' : 'Add a favicon. Google shows it next to your result on mobile, and it makes tabs and bookmarks recognisable.');

        // ---------- Score ----------
        $points = array_sum(array_map(fn ($c) => self::WEIGHT[$c['status']], $checks));
        $score = (int) round(100 * $points / count($checks));
        $order = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        usort($checks, fn ($a, $b) => $order[$a['status']] <=> $order[$b['status']]);
        $counts = array_count_values(array_column($checks, 'status')) + ['fail' => 0, 'warn' => 0, 'pass' => 0];

        return [
            'url' => $url,
            'final_url' => $final,
            'score' => $score,
            'rating' => $score >= 85 ? 'good' : ($score >= 60 ? 'average' : 'poor'),
            'summary' => match (true) {
                $score >= 85 => 'Strong foundations. Fix the remaining items below and focus on great content and links.',
                $score >= 60 => 'A decent start, but several issues are holding this page back in search. The tips below show what to fix first.',
                default => 'This page has important SEO problems that make it hard for Google to understand and rank. Start with the red items below.',
            },
            'title' => $title,
            'description' => $desc,
            'counts' => $counts,
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
