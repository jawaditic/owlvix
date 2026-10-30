<?php

namespace App\Support;

/** The free tools, in menu order — used by the "Free builders" and "Free tools" menus, the /free-tools hub and the sitemap.
 *  Tools in the 'builder' group go in the Free builders menu; the rest go in Free tools. */
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
            ['route' => 'funnel-builder', 'group' => 'builder', 'name' => 'Funnel builder', 'desc' => 'Opt-ins, upsells, leads & A/B tests', 'icon' => 'store', 'tone' => ''],
            ['route' => 'landing-builder', 'group' => 'builder', 'name' => 'Landing page builder', 'desc' => 'Build & download your own page in minutes', 'icon' => 'pen', 'tone' => 'is-blue'],
            ['route' => 'website-builder', 'group' => 'builder', 'name' => 'Website builder', 'desc' => 'Multi-page business site with menu & contact form', 'icon' => 'globe', 'tone' => ''],
            ['route' => 'portfolio-builder', 'group' => 'builder', 'name' => 'Portfolio builder', 'desc' => 'Show your work — photographers, designers, freelancers', 'icon' => 'eye', 'tone' => 'is-blue'],
            ['route' => 'link-in-bio-builder', 'group' => 'builder', 'name' => 'Link in bio builder', 'desc' => 'One link for Instagram & TikTok — free Linktree alternative', 'icon' => 'link', 'tone' => ''],
            ['route' => 'coming-soon-builder', 'group' => 'builder', 'name' => 'Coming soon page builder', 'desc' => 'Countdown, waitlist form and socials', 'icon' => 'rocket', 'tone' => 'is-blue'],
            ['route' => 'event-website-builder', 'group' => 'builder', 'name' => 'Wedding & event website builder', 'desc' => 'Invitation, countdown & RSVP list', 'icon' => 'calendar', 'tone' => ''],
            ['route' => 'menu-builder', 'group' => 'builder', 'name' => 'Restaurant menu & QR builder', 'desc' => 'Digital menu + QR table cards', 'icon' => 'cart', 'tone' => 'is-blue'],
            ['route' => 'email-builder', 'group' => 'builder', 'name' => 'Email & newsletter builder', 'desc' => 'Mailchimp-ready HTML emails', 'icon' => 'mail', 'tone' => ''],
            ['route' => 'form-builder', 'group' => 'builder', 'name' => 'Form & survey builder', 'desc' => 'Surveys, RSVPs, order forms — answers by email', 'icon' => 'clipboard', 'tone' => 'is-blue'],
            ['route' => 'email-signature', 'group' => 'build', 'name' => 'Email signature generator', 'desc' => 'Gmail & Outlook signatures — 6 designs, copy & paste', 'icon' => 'mail', 'tone' => ''],
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
        'builder' => ['Free builders', 'Drag-and-drop builders for pages, websites, funnels and forms — free, no sign-up to start. Publish or download.'],
        'build' => ['Build it right', 'See how sites are made, and get the signatures, code and images Google and social apps need.'],
    ];

    /** Free builders menu items. */
    public static function builders(): array
    {
        return array_values(array_filter(self::all(), fn ($t) => $t['group'] === 'builder'));
    }

    /** Free tools menu items (everything except the builders). */
    public static function tools(): array
    {
        return array_values(array_filter(self::all(), fn ($t) => $t['group'] !== 'builder'));
    }

    /** Routes that mark the "Free tools" menu as the current page. */
    public static function routes(): array
    {
        return array_merge(array_column(self::tools(), 'route'), ['seo-audit', 'free-tools']);
    }

    /** Routes that mark the "Free builders" menu as the current page. */
    public static function builderRoutes(): array
    {
        return array_column(self::builders(), 'route');
    }
}
