<?php

namespace Tests\Feature;

use App\Support\FreeTools;
use App\Support\MetaPreview;
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
        foreach (['free-tools', 'cost-calculator', 'security-checker', 'tech-stack', 'meta-preview'] as $r) {
            $this->assertStringContainsString('<loc>'.route($r).'</loc>', $sitemap, $r);
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
        foreach (['/tech-stack-detector', '/security-checker', '/meta-tag-preview'] as $tool) {
            $this->postJson($tool, ['url' => 'localhost'])->assertStatus(422);
            $this->postJson($tool, ['url' => 'http://192.168.1.1'])->assertStatus(422);
        }
    }
}
