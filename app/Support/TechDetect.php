<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Free tech stack detector (/tech-stack-detector): "what is this website built with?".
 * Matches the page's HTML, headers and cookies against known fingerprints (no third-party API).
 */
class TechDetect
{
    /** name => [category, [regexes on HTML], [header patterns "name: regex"], [cookie name regexes]] */
    private const RULES = [
        // CMS & website builders
        'WordPress' => ['CMS', ['#/wp-(content|includes)/#i', '#<meta[^>]+generator[^>]+WordPress#i'], ['link: wp\.me|api\.w\.org'], []],
        'Wix' => ['Website builder', ['#static\.wixstatic\.com|wix-bolt#i'], ['x-wix-request-id: .'], []],
        'Squarespace' => ['Website builder', ['#static1\.squarespace\.com|Squarespace\.#'], [], []],
        'Webflow' => ['Website builder', ['#data-wf-page|webflow\.js|website-files\.com#i'], [], []],
        'Joomla' => ['CMS', ['#<meta[^>]+generator[^>]+Joomla#i', '#/media/jui/#i'], [], []],
        'Drupal' => ['CMS', ['#drupal-settings-json|/sites/default/files/#i', '#<meta[^>]+generator[^>]+Drupal#i'], ['x-generator: Drupal'], []],
        'Ghost' => ['CMS', ['#<meta[^>]+generator[^>]+Ghost#i'], [], []],
        'Framer' => ['Website builder', ['#framerusercontent\.com|framer\.com/m/#i'], [], []],
        // E-commerce
        'Shopify' => ['E-commerce', ['#cdn\.shopify\.com|Shopify\.theme#i'], ['x-shopify-stage: .', 'x-shopid: .'], []],
        'WooCommerce' => ['E-commerce', ['#woocommerce#i'], [], ['#^woocommerce_#']],
        'Magento' => ['E-commerce', ['#Mage\.Cookies|/static/version\d+/|mage/cookies#i'], [], ['#^(mage-|X-Magento)#i']],
        'BigCommerce' => ['E-commerce', ['#cdn\d+\.bigcommerce\.com#i'], [], []],
        'PrestaShop' => ['E-commerce', ['#prestashop#i'], [], ['#^PrestaShop-#']],
        // Backend frameworks & languages
        'Laravel' => ['Backend', [], [], ['#^laravel_session$#', '#[-_]session$#']],
        'Livewire' => ['Backend', ['#wire:(model|click|id)|livewire(\.min)?\.js#i'], [], []],
        'Inertia.js' => ['Backend', ['#<div[^>]+id="app"[^>]+data-page=#i'], [], []],
        'Django' => ['Backend', ['#csrfmiddlewaretoken#'], [], ['#^csrftoken$#']],
        'Ruby on Rails' => ['Backend', ['#<meta name="csrf-param" content="authenticity_token"#i'], [], ['#^_[a-z0-9_]+_session$#']],
        'ASP.NET' => ['Backend', ['#__VIEWSTATE|__doPostBack#'], ['x-aspnet-version: .', 'x-powered-by: ASP\.NET'], ['#^ASP\.NET_SessionId$#']],
        'PHP' => ['Backend', [], ['x-powered-by: PHP'], ['#^PHPSESSID$#']],
        'Express (Node.js)' => ['Backend', [], ['x-powered-by: Express'], []],
        // Frontend
        'Next.js' => ['Frontend', ['#__NEXT_DATA__|/_next/static/#'], ['x-powered-by: Next\.js'], []],
        'Nuxt' => ['Frontend', ['#__NUXT__|/_nuxt/#'], [], []],
        'React' => ['Frontend', ['#data-reactroot|react-dom(\.production)?(\.min)?\.js|__NEXT_DATA__|data-react-helmet#i'], [], []],
        'Vue.js' => ['Frontend', ['#\sdata-v-[0-9a-f]{6,}|vue(\.runtime)?(\.global)?(\.prod)?(\.min)?\.js|__NUXT__#i'], [], []],
        'Angular' => ['Frontend', ['#\sng-version="#'], [], []],
        'Svelte' => ['Frontend', ['#__sveltekit|svelte-[a-z0-9]{6}#'], [], []],
        'Gatsby' => ['Frontend', ['#___gatsby#'], [], []],
        'Astro' => ['Frontend', ['#<astro-island|astro-[a-z0-9]{8}#i'], [], []],
        'Alpine.js' => ['Frontend', ['#\sx-data[=\s>]|alpine(js)?(\.min)?\.js#i'], [], []],
        'jQuery' => ['Frontend', ['#jquery(-\d[\d.]*)?(\.min)?\.js#i'], [], []],
        'Bootstrap' => ['UI framework', ['#bootstrap(\.bundle)?(\.min)?\.(css|js)#i'], [], []],
        'Tailwind CSS' => ['UI framework', ['#tailwind(css)?#i', '#class="[^"]*\b(?:flex|grid)\b[^"]*\b(?:px|py|mt|mb)-\d+[^"]*\btext-(?:sm|lg|xl|gray-\d{3})#'], [], []],
        // Hosting, servers & CDN
        'Cloudflare' => ['Hosting & CDN', [], ['cf-ray: .', 'server: cloudflare'], []],
        'Vercel' => ['Hosting & CDN', [], ['x-vercel-id: .', 'server: Vercel'], []],
        'Netlify' => ['Hosting & CDN', [], ['x-nf-request-id: .', 'server: Netlify'], []],
        'Amazon CloudFront' => ['Hosting & CDN', [], ['x-amz-cf-id: .', 'via: .*cloudfront'], []],
        'Fastly' => ['Hosting & CDN', [], ['x-served-by: cache-', 'fastly-debug-digest: .'], []],
        'GitHub Pages' => ['Hosting & CDN', [], ['server: GitHub\.com'], []],
        'Firebase Hosting' => ['Hosting & CDN', [], ['x-firebase-hosting: .|server: Google Frontend'], []],
        'Nginx' => ['Web server', [], ['server: nginx'], []],
        'Apache' => ['Web server', [], ['server: Apache'], []],
        'LiteSpeed' => ['Web server', [], ['server: LiteSpeed', 'x-litespeed-cache: .'], []],
        'Microsoft IIS' => ['Web server', [], ['server: Microsoft-IIS'], []],
        // Analytics & marketing
        'Google Analytics' => ['Analytics', ['#googletagmanager\.com/gtag/js|google-analytics\.com/(analytics|ga)\.js#i'], [], []],
        'Google Tag Manager' => ['Analytics', ['#googletagmanager\.com/gtm\.js|GTM-[A-Z0-9]{5,}#'], [], []],
        'Microsoft Clarity' => ['Analytics', ['#clarity\.ms/tag#i'], [], []],
        'Hotjar' => ['Analytics', ['#static\.hotjar\.com|hotjar\.com/c/hotjar#i'], [], []],
        'Meta Pixel' => ['Marketing', ['#connect\.facebook\.net/[^"\']+/fbevents\.js|fbq\(\s*[\'"]init#i'], [], []],
        'TikTok Pixel' => ['Marketing', ['#analytics\.tiktok\.com#i'], [], []],
        'LinkedIn Insight' => ['Marketing', ['#snap\.licdn\.com/li\.lms-analytics#i'], [], []],
        'HubSpot' => ['Marketing', ['#js\.hs-scripts\.com|js\.hsforms\.net#i'], [], []],
        'Mailchimp' => ['Marketing', ['#chimpstatic\.com|list-manage\.com#i'], [], []],
        // Payments, chat & services
        'Stripe' => ['Payments', ['#js\.stripe\.com#i'], [], []],
        'PayPal' => ['Payments', ['#paypal\.com/sdk/js|paypalobjects\.com#i'], [], []],
        'Intercom' => ['Live chat', ['#widget\.intercom\.io|intercomSettings#i'], [], []],
        'Tawk.to' => ['Live chat', ['#embed\.tawk\.to#i'], [], []],
        'Crisp' => ['Live chat', ['#client\.crisp\.chat#i'], [], []],
        'Zendesk' => ['Live chat', ['#static\.zdassets\.com|zopim#i'], [], []],
        'WhatsApp chat' => ['Live chat', ['#wa\.me/\d|api\.whatsapp\.com/send#i'], [], []],
        'Google reCAPTCHA' => ['Security', ['#google\.com/recaptcha|grecaptcha#i'], [], []],
        'Cloudflare Turnstile' => ['Security', ['#challenges\.cloudflare\.com/turnstile#i'], [], []],
        'Google Fonts' => ['Fonts', ['#fonts\.googleapis\.com#i'], [], []],
        'Font Awesome' => ['Fonts', ['#font-?awesome|kit\.fontawesome\.com#i'], [], []],
    ];

    /** Short note per category shown under the group. */
    private const CATEGORY_ORDER = ['CMS', 'Website builder', 'E-commerce', 'Backend', 'Frontend', 'UI framework', 'Hosting & CDN', 'Web server', 'Analytics', 'Marketing', 'Payments', 'Live chat', 'Security', 'Fonts'];

    public static function run(string $url): array
    {
        if ($c = Cache::get('techdetect:'.sha1($url))) {
            return ['ok' => true, 'report' => $c];
        }
        $page = SafeFetch::get($url);
        if (! $page['ok']) {
            return ['ok' => false, 'error' => $page['error']];
        }
        if ($page['status'] >= 400) {
            return ['ok' => false, 'error' => "That page answered with an error (HTTP {$page['status']})."];
        }
        $report = self::detect($url, $page);
        Cache::put('techdetect:'.sha1($url), $report, now()->addHour());

        return ['ok' => true, 'report' => $report];
    }

    public static function detect(string $url, array $page): array
    {
        $html = $page['body'];
        $headers = $page['headers'];
        $headerLines = [];
        foreach ($headers as $name => $values) {
            foreach ((array) $values as $v) {
                $headerLines[] = strtolower($name).': '.$v;
            }
        }
        $cookies = [];
        foreach ((array) ($headers['set-cookie'] ?? []) as $c) {
            $cookies[] = trim(strtok($c, '='));
        }

        $found = [];
        foreach (self::RULES as $name => [$cat, $htmlRx, $headerRx, $cookieRx]) {
            $hit = false;
            foreach ($htmlRx as $rx) {
                if (preg_match($rx, $html)) {
                    $hit = true;
                    break;
                }
            }
            foreach ($hit ? [] : $headerRx as $hrx) {
                [$h, $rx] = explode(': ', $hrx, 2);
                foreach ($headerLines as $line) {
                    if (str_starts_with($line, $h.': ') && preg_match('#'.$rx.'#i', substr($line, strlen($h) + 2))) {
                        $hit = true;
                        break 2;
                    }
                }
            }
            foreach ($hit ? [] : $cookieRx as $crx) {
                foreach ($cookies as $c) {
                    if (preg_match($crx, $c)) {
                        $hit = true;
                        break 2;
                    }
                }
            }
            if ($hit) {
                $found[$name] = $cat;
            }
        }

        // Laravel: its "_session" cookie name rule is broad, so only keep it with Laravel's XSRF-TOKEN cookie
        if (isset($found['Laravel']) && ! in_array('laravel_session', $cookies, true) && ! in_array('XSRF-TOKEN', $cookies, true)) {
            unset($found['Laravel']);
        }
        // A site on a website builder doesn't also need "PHP"/"React" noise from the builder itself
        if (isset($found['Shopify']) || isset($found['Wix']) || isset($found['Squarespace'])) {
            unset($found['React'], $found['jQuery']);
        }
        if (isset($found['Next.js'])) {
            $found['React'] = 'Frontend';
        }

        $versions = [];
        if (preg_match('#<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)#i', $html, $m)) {
            $versions['generator'] = $m[1];
        }
        $server = $headers['server'][0] ?? null;

        $groups = [];
        foreach (self::CATEGORY_ORDER as $cat) {
            $names = array_keys(array_filter($found, fn ($c) => $c === $cat));
            if ($names) {
                $groups[] = ['category' => $cat, 'items' => $names];
            }
        }

        $platform = collect(['Shopify', 'WordPress', 'Wix', 'Squarespace', 'Webflow', 'Framer', 'Joomla', 'Drupal', 'Ghost', 'Magento', 'BigCommerce', 'PrestaShop', 'Next.js', 'Nuxt', 'Laravel', 'Django', 'Ruby on Rails', 'ASP.NET', 'Gatsby', 'Astro'])
            ->first(fn ($n) => isset($found[$n]));

        return [
            'url' => $url,
            'final_url' => $page['url'],
            'platform' => $platform,
            'count' => count($found),
            'groups' => $groups,
            'generator' => $versions['generator'] ?? null,
            'server' => $server,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
