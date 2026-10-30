<?php

namespace App\Support;

/** The free tools, in menu order — used by the "Free tools" menu, the /free-tools hub and the sitemap. */
class FreeTools
{
    public static function all(): array
    {
        return [
            ['route' => 'cost-calculator', 'group' => 'plan', 'name' => 'Cost calculator', 'desc' => 'Instant price & timeline for your app or website', 'icon' => 'calendar', 'tone' => ''],
            ['route' => 'ads-roi', 'group' => 'plan', 'name' => 'Ads budget & ROI calculator', 'desc' => 'Clicks, leads and return from your ad spend', 'icon' => 'chart', 'tone' => 'is-blue'],
            ['route' => 'seo-checker', 'group' => 'check', 'name' => 'SEO checker', 'desc' => 'Score + tips to rank higher on Google', 'icon' => 'search', 'tone' => 'is-blue'],
            ['route' => 'speed-test', 'group' => 'check', 'name' => 'Website speed test', 'desc' => 'Core Web Vitals + PDF report', 'icon' => 'pulse', 'tone' => ''],
            ['route' => 'security-checker', 'group' => 'check', 'name' => 'Security & SSL checker', 'desc' => 'SSL, HTTPS and security headers', 'icon' => 'shield', 'tone' => 'is-blue'],
            ['route' => 'broken-links', 'group' => 'check', 'name' => 'Broken link checker', 'desc' => 'Find dead links and missing images', 'icon' => 'link', 'tone' => ''],
            ['route' => 'email-check', 'group' => 'check', 'name' => 'Email & DNS checker', 'desc' => 'SPF, DKIM, DMARC — why email lands in spam', 'icon' => 'mail', 'tone' => 'is-blue'],
            ['route' => 'redirect-check', 'group' => 'check', 'name' => 'Redirect & header checker', 'desc' => 'Follow every redirect step and see the headers', 'icon' => 'refresh', 'tone' => ''],
            ['route' => 'robots-check', 'group' => 'check', 'name' => 'Robots.txt & AI crawler checker', 'desc' => 'Can Google, ChatGPT and Claude read your site?', 'icon' => 'book', 'tone' => 'is-blue'],
            ['route' => 'domain-check', 'group' => 'check', 'name' => 'Domain age & expiry checker', 'desc' => 'Registrar, age and renewal date', 'icon' => 'globe', 'tone' => ''],
            ['route' => 'landing-builder', 'group' => 'build', 'name' => 'Landing page builder', 'desc' => 'Build & download your own page in minutes', 'icon' => 'pen', 'tone' => 'is-blue'],
            ['route' => 'tech-stack', 'group' => 'build', 'name' => 'Tech stack detector', 'desc' => 'What is a website built with?', 'icon' => 'code', 'tone' => ''],
            ['route' => 'meta-preview', 'group' => 'build', 'name' => 'Meta tag & social preview', 'desc' => 'Google, WhatsApp & social previews', 'icon' => 'eye', 'tone' => 'is-blue'],
            ['route' => 'schema-generator', 'group' => 'build', 'name' => 'Schema markup generator', 'desc' => 'Ready-to-paste JSON-LD for Google', 'icon' => 'clipboard', 'tone' => ''],
            ['route' => 'image-compressor', 'group' => 'build', 'name' => 'Image compressor (WebP)', 'desc' => 'Shrink images in your browser — no upload', 'icon' => 'rocket', 'tone' => 'is-blue'],
        ];
    }

    /** Hub page sections, in order. */
    public const GROUPS = [
        'plan' => ['Plan your project', 'Know the cost and the return before you spend.'],
        'check' => ['Check your website', 'Find what is slowing you down, hurting rankings or sending email to spam.'],
        'build' => ['Build it right', 'Make your own page, see how sites are made, and get the code Google and social apps need.'],
    ];

    public static function routes(): array
    {
        return array_merge(array_column(self::all(), 'route'), ['seo-audit', 'free-tools']);
    }
}
