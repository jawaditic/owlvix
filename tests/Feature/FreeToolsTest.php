<?php

namespace Tests\Feature;

use App\Support\BrokenLinks;
use App\Support\DomainCheck;
use App\Support\EmailCheck;
use App\Support\FreeTools;
use App\Support\MetaPreview;
use App\Support\RedirectCheck;
use App\Support\RobotsCheck;
use App\Support\SecurityCheck;
use App\Support\TechDetect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Free tools hub, cost calculator, tech stack detector, security checker, meta preview. */
class FreeToolsTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $html, array $headers = [], string $url = 'https://example.com/'): array
    {
        return ['ok' => true, 'url' => $url, 'status' => 200, 'headers' => $headers + ['content-type' => ['text/html']], 'body' => $html, 'ms' => 200, 'redirects' => 0];
    }

    public function test_every_tool_page_loads_and_is_in_the_menu_and_sitemap(): void
    {
        $home = $this->get('/')->getContent();
        $sitemap = $this->get('/sitemap.xml')->getContent();
        foreach (FreeTools::all() as $t) {
            $this->get(route($t['route']))->assertOk();
            $this->assertStringContainsString('href="'.route($t['route']).'"', $home, $t['route']);
        }
        $this->get('/free-tools')->assertOk()->assertSee('Cost calculator');
        foreach (array_merge(['free-tools'], array_column(FreeTools::all(), 'route')) as $r) {
            $this->assertStringContainsString('<loc>'.route($r).'</loc>', $sitemap, $r);
        }
    }

    public function test_each_extra_builder_starts_the_editor_in_its_own_mode(): void
    {
        foreach (\App\Support\BuilderKits::ROUTES as $kit => $route) {
            $k = \App\Support\BuilderKits::get($kit);
            $this->get(route($route))->assertOk()
                ->assertSee('data-mode="'.$kit.'"', false)
                ->assertSee('<title>'.e($k['title']), false)
                ->assertSee('js/landing-builder', false);
        }
        $this->get(route('landing-builder'))->assertOk()->assertDontSee('data-mode=', false);
    }

    public function test_made_with_credit_links_match_the_builder_routes(): void
    {
        // builder-engine.js links each published page back to its builder (KIT_PATH)
        $engine = file_get_contents(public_path('js/builder-engine.js'));
        preg_match('/const KIT_PATH = (\{[^}]*\})/', $engine, $m);
        $this->assertNotEmpty($m, 'KIT_PATH not found');
        preg_match_all("/(\\w+): '([\\w-]+)'/", $m[1], $pairs, PREG_SET_ORDER);
        $paths = array_column($pairs, 2, 1);
        foreach (\App\Support\BuilderKits::ROUTES + ['funnel' => 'funnel-builder'] as $kit => $route) {
            $this->assertSame(ltrim(parse_url(route($route), PHP_URL_PATH), '/'), $paths[$kit] ?? null, $kit);
        }
    }

    public function test_cost_calculator_settings_are_complete(): void
    {
        $c = config('cost_calculator');
        $services = \App\Support\Enquiries::SERVICES;
        foreach ($c['types'] as $key => $t) {
            $this->assertLessThan($t['max'], $t['min'], $key);
            $this->assertContains($t['service'], $services, "$key preselects a real quote option");
            $this->assertContains($t['icon'], \App\Support\Icons::names(), $key);
        }
        foreach ($c['features'] as $key => $f) {
            $this->assertCount(5, $f, $key);
            $this->assertTrue($f[4] === '*' || ! array_diff($f[4], array_keys($c['types'])), "$key lists real project types");
        }
        $this->get('/project-cost-calculator')->assertSee('name="type" value="webapp"', false);
    }

    public function test_tech_stack_is_detected_from_html_headers_and_cookies(): void
    {
        $wp = TechDetect::detect('https://a.example', $this->page('<link href="/wp-content/themes/x/style.css"><script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><script src="/wp-includes/js/jquery/jquery.min.js"></script>', ['server' => ['cloudflare'], 'cf-ray' => ['abc']]));
        $this->assertSame('WordPress', $wp['platform']);
        $flat = collect($wp['groups'])->pluck('items')->flatten()->all();
        $this->assertEqualsCanonicalizing(['WordPress', 'jQuery', 'Cloudflare', 'Google Analytics'], $flat);

        $laravel = TechDetect::detect('https://b.example', $this->page('<html></html>', ['set-cookie' => ['XSRF-TOKEN=1; path=/', 'app-session=2; path=/'], 'x-powered-by' => ['PHP/8.3']]));
        $this->assertSame('Laravel', $laravel['platform']);

        $next = TechDetect::detect('https://c.example', $this->page('<script id="__NEXT_DATA__">{}</script>', ['x-vercel-id' => ['x']]));
        $this->assertEqualsCanonicalizing(['Next.js', 'React', 'Vercel'], collect($next['groups'])->pluck('items')->flatten()->all());
    }

    public function test_security_checks_read_headers_cookies_and_certificate(): void
    {
        $good = ['strict-transport-security' => ['max-age=31536000'], 'content-security-policy' => ["default-src 'self'; frame-ancestors 'self'"], 'x-content-type-options' => ['nosniff'], 'referrer-policy' => ['strict-origin'], 'permissions-policy' => ['camera=()'], 'set-cookie' => ['s=1; Secure; HttpOnly; SameSite=Lax']];
        $cert = ['ok' => true, 'trusted' => true, 'issuer' => "Let's Encrypt", 'expires' => time() + 90 * 86400, 'days_left' => 90, 'names' => ['example.com', 'www.example.com'], 'protocol' => 'TLSv1.3'];
        $r = SecurityCheck::analyse('https://example.com', 'example.com', $this->page('<html></html>', $good), $this->page('', [], 'https://example.com/'), $cert);
        $this->assertSame(100, $r['score']);

        $bad = SecurityCheck::analyse('https://example.com', 'example.com', ['ok' => false], $this->page('<meta name="generator" content="WordPress 5.2"><img src="http://x.com/a.jpg">', ['server' => ['Apache/2.4.1'], 'set-cookie' => ['s=1']], 'http://example.com/'),
            ['ok' => true, 'trusted' => false, 'issuer' => 'Self', 'expires' => time() - 86400, 'days_left' => -1, 'names' => ['other.com'], 'protocol' => 'TLSv1']);
        $status = collect($bad['checks'])->pluck('status', 'title');
        $this->assertSame('fail', $status['HTTPS']);
        $this->assertSame('fail', $status['Certificate valid']);
        $this->assertSame('fail', $status['Certificate expiry']);
        $this->assertSame('fail', $status['TLS version']);
        $this->assertSame('warn', $status['Server version']);
        $this->assertSame('warn', $status['CMS version']);
        $this->assertSame('poor', $bad['rating']);
    }

    public function test_meta_tags_are_extracted_with_absolute_urls(): void
    {
        $r = MetaPreview::extract('https://example.com', $this->page('<title>Hello</title><meta name="description" content="A short one."><meta property="og:image" content="/share.png"><link rel="icon" href="/fav.svg">'));
        $this->assertSame('Hello', $r['tags']['title']);
        $this->assertSame('https://example.com/share.png', $r['tags']['og:image']);
        $this->assertSame('https://example.com/fav.svg', $r['tags']['favicon']);
        $this->assertContains('No twitter:card — links on X show a small preview without a large image.', array_column($r['issues'], 1));
    }

    public function test_tools_refuse_private_addresses(): void
    {
        foreach (['/tech-stack-detector', '/security-checker', '/meta-tag-preview', '/broken-link-checker', '/email-dns-checker', '/redirect-checker', '/robots-txt-checker', '/domain-age-checker'] as $tool) {
            $this->postJson($tool, ['url' => 'localhost'])->assertStatus(422);
            $this->postJson($tool, ['url' => 'http://192.168.1.1'])->assertStatus(422);
        }
    }

    public function test_every_page_outputs_valid_json_ld(): void
    {
        $urls = array_merge(['/', '/about', '/services', '/free-tools'], array_map(fn ($t) => route($t['route']), FreeTools::all()));
        foreach ($urls as $u) {
            preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $this->get($u)->getContent(), $m);
            $this->assertNotEmpty($m[1], "$u has JSON-LD");
            foreach (array_filter($m[1], fn ($j) => ! str_contains($j, '${')) as $json) { // skip the schema generator's JS template
                $data = json_decode($json, true);
                $this->assertSame('https://schema.org', $data['@context'] ?? null, "$u JSON-LD has a clean @context");
            }
        }
    }

    public function test_email_domain_is_read_from_any_input(): void
    {
        $this->assertSame('gmail.com', EmailCheck::domain('You@Gmail.com'));
        $this->assertSame('example.org', EmailCheck::domain('https://www.example.org/contact'));
        $this->assertSame('shop.example.co.uk', EmailCheck::domain('shop.example.co.uk'));
        $this->assertNull(EmailCheck::domain('localhost'));
        $this->assertNull(EmailCheck::domain('printer.local'));
        $this->assertNull(EmailCheck::domain('not a domain'));
    }

    public function test_email_records_are_scored_with_fixes(): void
    {
        $good = EmailCheck::analyse('example.com', ['mx' => [['host' => 'aspmx.l.google.com', 'pri' => 1]], 'spf' => ['v=spf1 include:_spf.google.com ~all'], 'spf_lookups' => 4,
            'dmarc' => ['v=DMARC1; p=reject; rua=mailto:d@example.com'], 'dkim' => ['google' => 'v=DKIM1; k=rsa; p=abc'], 'ns' => ['a.ns.com', 'b.ns.com'], 'a' => ['1.2.3.4']]);
        $this->assertSame(100, $good['score']);
        $this->assertSame('Google Workspace', $good['provider']);

        $bad = EmailCheck::analyse('example.com', ['mx' => [['host' => 'aspmx.l.google.com', 'pri' => 1]], 'spf' => [], 'spf_lookups' => 0, 'dmarc' => [], 'dkim' => [], 'ns' => ['a.ns.com'], 'a' => []]);
        $status = collect($bad['checks'])->pluck('status', 'title');
        $this->assertSame('fail', $status['SPF record']);
        $this->assertSame('fail', $status['DMARC policy']);
        $this->assertSame('warn', $status['DKIM signature']);
        $this->assertSame('v=spf1 include:_spf.google.com ~all', $bad['suggest_spf']);

        $tooMany = EmailCheck::analyse('example.com', ['mx' => [], 'spf' => ['v=spf1 +all'], 'spf_lookups' => 12, 'dmarc' => ['v=DMARC1; p=none'], 'dkim' => [], 'ns' => [], 'a' => []]);
        $status = collect($tooMany['checks'])->pluck('status', 'title');
        $this->assertSame('fail', $status['SPF record']);
        $this->assertSame('fail', $status['SPF lookups']);
        $this->assertSame('warn', $status['DMARC policy']);
        $this->assertSame('fail', $status['Mail servers (MX)']);
    }

    public function test_links_are_extracted_resolved_and_sorted_into_broken_redirects_and_blocked(): void
    {
        $links = BrokenLinks::extract('<a href="/about">About</a><a href="../x/./y?a=1#top">Y</a><a href="mailto:a@b.c">Mail</a><a href="#top">Top</a><a href="/cdn-cgi/l/email-protection">E</a><img src="//cdn.example.com/a.png" alt="Logo">', 'https://example.com/blog/post');
        $this->assertSame(['https://example.com/about', 'https://example.com/x/y?a=1', 'https://cdn.example.com/a.png'], array_column($links, 'url'));
        $this->assertSame('image', $links[2]['type']);

        $l = fn ($t = 'link') => ['type' => $t, 'text' => 'x', 'pages' => ['https://example.com/']];
        $r = BrokenLinks::analyse('https://example.com', 'https://example.com/', ['https://example.com/'],
            ['https://example.com/a' => $l(), 'https://example.com/old' => $l(), 'https://other.com/gone' => $l(), 'https://linkedin.com/in/x' => $l(), 'https://example.com/i.png' => $l('image')],
            ['https://example.com/a' => ['code' => 200], 'https://example.com/old' => ['code' => 301, 'location' => 'https://example.com/new'], 'https://other.com/gone' => ['code' => 404],
                'https://linkedin.com/in/x' => ['code' => 999], 'https://example.com/i.png' => ['code' => 0, 'error' => 'Could not connect']]);
        $this->assertSame(['checked' => 5, 'ok' => 1, 'broken' => 2, 'redirects' => 1, 'blocked' => 1], $r['counts']);
        $this->assertSame('https://example.com/i.png', $r['broken'][0]['url'], 'broken links on your own site come first');
        $this->assertSame('Page not found', $r['broken'][1]['reason']);
        $this->assertSame('average', $r['rating']);
    }

    public function test_robots_rules_match_like_google(): void
    {
        $p = RobotsCheck::parse("User-agent: *\nDisallow: /admin\nAllow: /admin/public\nDisallow: /*.pdf$\n\nUser-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n\nSitemap: https://example.com/sitemap.xml");
        $this->assertSame(['https://example.com/sitemap.xml'], $p['sitemaps']);
        $this->assertFalse(RobotsCheck::allowed($p, 'Googlebot', '/admin/users')[0]);
        $this->assertTrue(RobotsCheck::allowed($p, 'Googlebot', '/admin/public/page')[0], 'longer Allow wins');
        $this->assertFalse(RobotsCheck::allowed($p, 'Googlebot', '/files/a.pdf')[0]);
        $this->assertTrue(RobotsCheck::allowed($p, 'Googlebot', '/files/a.pdf?x=1')[0], '$ anchors the end');
        $this->assertFalse(RobotsCheck::allowed($p, 'GPTBot', '/')[0]);
        $this->assertFalse(RobotsCheck::allowed($p, 'CCBot', '/blog')[0], 'grouped user-agents share rules');
        $this->assertTrue(RobotsCheck::allowed($p, 'ClaudeBot', '/blog')[0]);

        $page = $this->page('<meta name="robots" content="noindex, follow">');
        $r = RobotsCheck::analyse('https://example.com/blog', $page, 200, "User-agent: *\nDisallow: /", RobotsCheck::parse("User-agent: *\nDisallow: /"), 'https://example.com/sitemap.xml', ['ok' => false]);
        $status = collect($r['checks'])->pluck('status', 'title');
        $this->assertSame('fail', $status['Whole site']);
        $this->assertSame('fail', $status['Can appear in Google']);
        $this->assertSame('fail', $status['XML sitemap']);
        $this->assertSame('warn', $status['AI answer engines']);
    }

    public function test_redirect_chains_and_versions_are_checked(): void
    {
        $chain = [['url' => 'http://example.com/', 'code' => 302, 'ms' => 50, 'location' => 'https://example.com/'], ['url' => 'https://example.com/', 'code' => 301, 'ms' => 50, 'location' => 'https://www.example.com/'], ['url' => 'https://www.example.com/', 'code' => 200, 'ms' => 100, 'headers' => ['server' => 'nginx']]];
        $variants = [['url' => 'http://example.com/', 'final' => 'https://www.example.com/', 'code' => 200, 'hops' => 2], ['url' => 'https://example.com/', 'final' => 'https://www.example.com/', 'code' => 200, 'hops' => 1],
            ['url' => 'http://www.example.com/', 'final' => 'http://www.example.com/', 'code' => 200, 'hops' => 0], ['url' => 'https://www.example.com/', 'final' => 'https://www.example.com/', 'code' => 200, 'hops' => 0]];
        $r = RedirectCheck::analyse('http://example.com/', $chain, $variants);
        $status = collect($r['checks'])->pluck('status', 'title');
        $this->assertSame(2, $r['hops']);
        $this->assertSame('warn', $status['Redirect chain']);
        $this->assertSame('warn', $status['Redirect type']);
        $this->assertSame('fail', $status['One main address']);
        $this->assertSame('fail', $status['HTTPS redirect']);
        $this->assertSame(['server' => 'nginx'], $r['headers']);

        $loop = RedirectCheck::analyse('https://a.com/', [['url' => 'https://a.com/', 'code' => 301, 'ms' => 1, 'location' => 'https://b.com/'], ['url' => 'https://a.com/', 'code' => 301, 'ms' => 1, 'loop' => true]], []);
        $this->assertSame('fail', collect($loop['checks'])->firstWhere('title', 'Redirect loop')['status']);
    }

    public function test_domain_registration_is_read_from_rdap_and_whois(): void
    {
        $this->assertSame('example.co.uk', DomainCheck::domain('https://shop.example.co.uk/x'));
        $this->assertSame('example.com.pk', DomainCheck::domain('www.example.com.pk'));
        $this->assertSame('github.com', DomainCheck::domain('blog.github.com'));

        $rdap = DomainCheck::parseRdap(['events' => [['eventAction' => 'registration', 'eventDate' => '2015-03-01T00:00:00Z'], ['eventAction' => 'expiration', 'eventDate' => '2027-03-01T00:00:00Z']],
            'entities' => [['roles' => ['registrar'], 'vcardArray' => ['vcard', [['fn', [], 'text', 'NameCheap, Inc.']]]]],
            'status' => ['client transfer prohibited'], 'nameservers' => [['ldhName' => 'NS1.EXAMPLE.COM'], ['ldhName' => 'ns2.example.com']], 'secureDNS' => ['delegationSigned' => false]]);
        $r = DomainCheck::analyse('example.com', $rdap, strtotime('2026-10-30'));
        $this->assertSame('NameCheap, Inc.', $r['registrar']);
        $this->assertSame('11 years, 7 months', $r['age']);
        $this->assertSame(122, $r['days_left']);
        $this->assertSame('pass', collect($r['checks'])->firstWhere('title', 'Transfer lock')['status']);

        $whois = DomainCheck::parseWhois("Domain: example.pk\nCreation Date: 2020-01-10\nExpiry Date: 2026-11-10\nName Server: ns1.host.pk\nName Server: ns2.host.pk\n");
        $soon = DomainCheck::analyse('example.pk', $whois, strtotime('2026-10-30'));
        $this->assertSame('fail', collect($soon['checks'])->firstWhere('title', 'Expiry date')['status']);
        $this->assertSame('poor', $soon['rating']);
        $this->assertFalse(DomainCheck::parseWhois("No match for \"FREE-DOMAIN.COM\".")['registered']);
    }
}
