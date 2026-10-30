<?php

namespace App\Support;

use App\Models\BuilderSite;

/**
 * Published builder pages: the safety check run before a page is stored, and the "Made with Owlvix" bar
 * added when it's served. The builder never produces scripts or the other things rejected here.
 */
class BuilderHtml
{
    public const MAX_BYTES = 2621440; // 2.5 MB

    /** The only iframes allowed: YouTube (privacy mode) and Google Maps embeds. */
    private const IFRAME_PREFIXES = ['https://www.youtube-nocookie.com/embed/', 'https://www.google.com/maps'];

    private const BLOCKED_TAGS = ['script', 'object', 'embed', 'applet', 'base', 'frame', 'frameset', 'portal'];

    /** The one script tag a page may contain: owlvix's page runtime (sliders, popups, counters, animations). */
    public static function runtimeTag(): string
    {
        return '<script src="'.self::runtimeUrl().'" defer></script>';
    }

    public static function runtimeUrl(): string
    {
        return self::appOrigin().'/js/builder-runtime.js';
    }

    /**
     * The main app's origin, for the CSP, the runtime script tag and form actions. Always the configured APP_URL in
     * production — never the Host of the request, which would let one odd request rewrite every site's policy.
     * (Locally the dev server's own address is used, so pages work on any port.)
     */
    public static function appOrigin(): string
    {
        if (app()->environment('production') && config('app.url')) {
            return rtrim(config('app.url'), '/');
        }
        $domain = config('builder.domain');
        if ($domain && strcasecmp(request()->getHost(), $domain) === 0 && config('app.url')) {
            return rtrim(config('app.url'), '/');
        }

        return rtrim(url('/'), '/');
    }

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'data', 'background', 'srcset', 'cite', 'ping'];

    /** Form fields the builder creates. Anything else (e.g. a "password" box) could be used to phish. */
    private const FIELDS = ['name', 'email', 'phone', 'message', '_hp', '_back', '_next', '_site', '_step', '_v'];

    private const PHISHY = '/pass|pwd|otp|pin\b|card|cvv|cvc|ssn|iban|login|username|secret|token/i';

    /**
     * Served on every published page. "sandbox" without allow-same-origin gives the page an opaque origin, so even
     * with scripts on it can never read owlvix.com cookies or storage; script-src allows only the runtime file.
     */
    public static function csp(): string
    {
        $origin = self::appOrigin();
        // Lead forms post to owlvix, which redirects to the funnel's next step on the sites domain; browsers check
        // that redirect against form-action too, so the sites domain is allowed as well (it only serves static pages).
        $sites = rtrim(BuilderSite::base(), '/');
        $sitesOrigin = config('builder.domain') ? preg_replace('#^(https?://[^/]+).*$#', '$1', $sites) : null;
        $formAction = $origin.($sitesOrigin && $sitesOrigin !== $origin ? ' '.$sitesOrigin : '');

        return 'sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation; '
            ."default-src 'none'; script-src ".self::runtimeUrl().'; '
            ."connect-src {$origin}/fs; "
            ."img-src data: https:; style-src 'unsafe-inline' https://fonts.googleapis.com; "
            .'font-src https://fonts.gstatic.com; frame-src https://www.youtube-nocookie.com/embed/ https://www.google.com/maps; '
            ."frame-ancestors 'none'; "
            ."form-action {$formAction}; base-uri 'none'";
    }

    /**
     * Why this page can't be published, in plain English, or null if it's fine.
     *
     * @param  string[]  $formIds  the form ids the publishing account owns
     */
    public static function problem(string $html, array $formIds): ?string
    {
        // The runtime tag is the one script allowed; check everything else without it
        $html = str_replace(self::runtimeTag(), '', $html);
        // Quick text checks first (they also catch things a parser might tidy away)
        if (preg_match('/<\s*('.implode('|', self::BLOCKED_TAGS).')\b/i', $html, $m)) {
            return self::tagMessage(strtolower($m[1]));
        }
        if (preg_match('/<[^>]*\son[a-z]+\s*=/i', $html)) {
            return 'Pages can’t contain JavaScript event handlers (like onclick).';
        }
        // javascript: as a URL (in href/src/action…) — not merely mentioned in text like "Learn JavaScript: …"
        if (preg_match('/<[^>]*\s(?:href|src|action|formaction|xlink:href|poster|data|background|srcset)\s*=\s*["\']?\s*(?:java|vb)script\s*:/i', $html)) {
            return 'Pages can’t contain javascript: links.';
        }

        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return 'This page’s HTML couldn’t be read. Please try publishing again.';
        }

        foreach ($doc->getElementsByTagName('*') as $el) {
            $tag = strtolower($el->nodeName);
            if (in_array($tag, self::BLOCKED_TAGS, true)) {
                return self::tagMessage($tag);
            }

            foreach ($el->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                // Browsers ignore spaces and control characters inside a URL scheme
                $value = strtolower(preg_replace('/[\x00-\x20]+/', '', $attr->nodeValue ?? ''));
                if (str_starts_with($name, 'on')) {
                    return 'Pages can’t contain JavaScript event handlers (like onclick).';
                }
                if (in_array($name, self::URL_ATTRIBUTES, true) && preg_match('/^(?:java|vb)script:/', $value)) {
                    return 'Pages can’t contain javascript: links.';
                }
                if (in_array($name, ['formaction', 'srcdoc'], true)) {
                    return 'Pages can’t contain the "'.$name.'" attribute.';
                }
            }

            // The builder never writes http-equiv metas; a hidden refresh would redirect visitors (CSP can't stop it)
            if ($tag === 'meta' && $el->hasAttribute('http-equiv')) {
                return 'Pages can’t redirect visitors automatically (meta refresh).';
            }
            if (in_array($tag, ['input', 'textarea', 'select'], true)) {
                $type = strtolower(trim($el->getAttribute('type')));
                $field = $el->getAttribute('name');
                $auto = strtolower($el->getAttribute('autocomplete'));
                $builderField = in_array($field, self::FIELDS, true)
                    || ($type === 'radio' && preg_match('/^t[a-z0-9]{3,12}$/', $field))   // tabs widget
                    || ($type === 'range' && $field === '');                               // before/after slider
                if ($type === 'password' || str_contains($auto, 'password') || preg_match(self::PHISHY, $field.' '.$el->getAttribute('placeholder').' '.$el->getAttribute('aria-label')) || ! $builderField) {
                    return 'Pages can’t contain password or payment fields, or form fields the builder didn’t create.';
                }
            }
            if ($tag === 'iframe') {
                $src = trim($el->getAttribute('src'));
                $ok = false;
                foreach (self::IFRAME_PREFIXES as $prefix) {
                    $ok = $ok || str_starts_with($src, $prefix);
                }
                if (! $ok) {
                    return 'Only YouTube videos and Google Maps can be embedded.';
                }
            }
            if ($tag === 'form') {
                $action = trim($el->getAttribute('action'));
                if (! preg_match('#^'.preg_quote(self::appOrigin(), '#').'/f/([a-z0-9]{16})$#', $action, $m)) {
                    return 'Forms must use an Owlvix contact form.';
                }
                if (! in_array($m[1], $formIds, true)) {
                    return 'This page uses a contact form from another account. Please add the contact form again.';
                }
            }
        }

        return null;
    }

    /**
     * .htaccess for the subdomain folder when sites are served as static files (config builder.public_path):
     * the same security headers the app sends, no folder listings, and the bare subdomain sends people to the builder.
     */
    public static function htaccess(): string
    {
        $csp = str_replace('"', "'", self::csp());

        return "# Written by owlvix.com (App\\Support\\BuilderHtml::htaccess) — pages made with the free page builder.\n"
            ."# Rewritten on every publish: edit the code, not this file.\n"
            ."Options -Indexes\nDirectoryIndex index.html\n\n"
            ."<IfModule mod_headers.c>\n"
            ."    Header always set Content-Security-Policy \"{$csp}\"\n"
            ."    Header always set X-Robots-Tag \"noindex\"\n"
            ."    Header always set X-Content-Type-Options \"nosniff\"\n"
            ."    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n"
            ."    Header always unset X-Powered-By\n"
            ."</IfModule>\n\n"
            ."<IfModule mod_rewrite.c>\n"
            ."    RewriteEngine On\n"
            ."    RewriteRule ^$ ".route('landing-builder')." [R=302,L]\n"
            ."    # Hide our marker files\n"
            ."    RewriteRule (^|/)\\.owlvix-site$ - [R=404,L]\n"
            ."    RewriteRule ^\\.(tmp|old)- - [R=404,L]\n"
            ."</IfModule>\n";
    }

    /** The page with the small "Made with Owlvix" bar added before </body>. */
    public static function withBar(string $html, BuilderSite $site): string
    {
        $builder = 'https://owlvix.com/landing-page-builder';
        $report = e(route('builder.report', $site->slug));
        $bar = '<div style="position:fixed;left:50%;bottom:10px;transform:translateX(-50%);z-index:2147483647;'
            .'font:500 12px/1.4 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;'
            .'background:rgba(7,11,20,.88);color:#e2e8f0;padding:6px 12px;border-radius:999px;white-space:nowrap;'
            .'box-shadow:0 4px 14px rgba(0,0,0,.25);">'
            .'<a href="'.$builder.'" target="_blank" rel="noopener" style="color:#e2e8f0;text-decoration:none;">'
            .'Made with <b style="color:#1ec773;">Owlvix</b> free page builder</a>'
            .' <span style="opacity:.5;">·</span> '
            .'<a href="'.$report.'" target="_blank" rel="noopener nofollow" style="color:#94a3b8;text-decoration:none;">Report</a>'
            .'</div>';

        $pos = strripos($html, '</body>');

        return $pos === false ? $html.$bar : substr_replace($html, $bar, $pos, 0);
    }

    private static function tagMessage(string $tag): string
    {
        return $tag === 'script'
            ? 'Pages can’t contain scripts.'
            : 'Pages can’t contain <'.$tag.'> elements.';
    }
}
