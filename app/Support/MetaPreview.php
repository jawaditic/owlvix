<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free meta tag & social preview tool (/meta-tag-preview): reads a page's title, description,
 * Open Graph and Twitter tags so the page can show Google / WhatsApp / Facebook / LinkedIn / X previews
 * and generate corrected tags.
 */
class MetaPreview
{
    public static function run(string $url): array
    {
        if ($c = Cache::get('metaprev:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        $page = SafeFetch::get($url);
        if (! $page['ok']) {
            return ['ok' => false, 'error' => $page['error']];
        }
        if ($page['status'] >= 400) {
            return ['ok' => false, 'error' => "That page answered with an error (HTTP {$page['status']})."];
        }
        $report = self::extract($url, $page);
        Cache::put('metaprev:'.sha1($url), $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    public static function extract(string $url, array $page): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$page['body'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $meta = function (string $attr, string $value) use ($xp): string {
            $n = $xp->query('//meta[translate(@'.$attr.',"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="'.$value.'"]')->item(0);

            return $n ? trim(preg_replace('/\s+/u', ' ', $n->getAttribute('content'))) : '';
        };
        $abs = fn (string $u) => $u === '' ? '' : SafeFetch::absolute($u, $page['url']);
        $titleNode = $xp->query('//title')->item(0);
        $icon = $xp->query('//link[contains(translate(@rel,"ICON","icon"),"icon")]/@href')->item(0);
        $canonical = $xp->query('//link[translate(@rel,"CANOICL","canoicl")="canonical"]/@href')->item(0);

        $tags = [
            'title' => $titleNode ? trim(preg_replace('/\s+/u', ' ', $titleNode->textContent)) : '',
            'description' => $meta('name', 'description'),
            'og:title' => $meta('property', 'og:title'),
            'og:description' => $meta('property', 'og:description'),
            'og:image' => $abs($meta('property', 'og:image')),
            'og:url' => $meta('property', 'og:url'),
            'og:type' => $meta('property', 'og:type'),
            'og:site_name' => $meta('property', 'og:site_name'),
            'twitter:card' => $meta('name', 'twitter:card'),
            'twitter:title' => $meta('name', 'twitter:title'),
            'twitter:description' => $meta('name', 'twitter:description'),
            'twitter:image' => $abs($meta('name', 'twitter:image')),
            'canonical' => $canonical ? $abs($canonical->value) : '',
            'favicon' => $icon ? $abs($icon->value) : $abs('/favicon.ico'),
        ];

        $issues = [];
        $tl = mb_strlen($tags['title']);
        $dl = mb_strlen($tags['description']);
        if (! $tl) {
            $issues[] = ['fail', 'No page title.'];
        } elseif ($tl > 60) {
            $issues[] = ['warn', "Title is $tl characters — Google cuts it after about 60."];
        }
        if (! $dl) {
            $issues[] = ['fail', 'No meta description — Google will pick random text from the page.'];
        } elseif ($dl > 160) {
            $issues[] = ['warn', "Description is $dl characters — Google cuts it after about 160."];
        }
        if (! $tags['og:image']) {
            $issues[] = ['fail', 'No og:image — shared links show no picture on WhatsApp, Facebook and LinkedIn.'];
        }
        if (! $tags['og:title']) {
            $issues[] = ['warn', 'No og:title — social sites fall back to the page title.'];
        }
        if (! $tags['og:description']) {
            $issues[] = ['warn', 'No og:description — social sites fall back to the meta description or nothing.'];
        }
        if (! $tags['twitter:card']) {
            $issues[] = ['warn', 'No twitter:card — links on X show a small preview without a large image.'];
        }
        if (! $tags['canonical']) {
            $issues[] = ['warn', 'No canonical tag.'];
        }

        return [
            'url' => $url,
            'final_url' => $page['url'],
            'host' => parse_url($page['url'], PHP_URL_HOST),
            'tags' => $tags,
            'issues' => $issues,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
