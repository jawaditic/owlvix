<?php

namespace App\Support;

/** The free tools, in menu order — used by the "Free tools" menu, the /free-tools hub and the sitemap. */
class FreeTools
{
    public static function all(): array
    {
        return [
            ['route' => 'cost-calculator', 'name' => 'Cost calculator', 'desc' => 'Instant price & timeline for your app or website', 'icon' => 'calendar', 'tone' => ''],
            ['route' => 'seo-checker', 'name' => 'SEO checker', 'desc' => 'Score + tips to rank higher on Google', 'icon' => 'search', 'tone' => 'is-blue'],
            ['route' => 'speed-test', 'name' => 'Website speed test', 'desc' => 'Core Web Vitals + PDF report', 'icon' => 'pulse', 'tone' => ''],
            ['route' => 'security-checker', 'name' => 'Security & SSL checker', 'desc' => 'SSL, HTTPS and security headers', 'icon' => 'shield', 'tone' => 'is-blue'],
            ['route' => 'tech-stack', 'name' => 'Tech stack detector', 'desc' => 'What is a website built with?', 'icon' => 'code', 'tone' => ''],
            ['route' => 'meta-preview', 'name' => 'Meta tag & social preview', 'desc' => 'Google, WhatsApp & social previews', 'icon' => 'eye', 'tone' => 'is-blue'],
        ];
    }

    public static function routes(): array
    {
        return array_merge(array_column(self::all(), 'route'), ['seo-audit', 'free-tools']);
    }
}
