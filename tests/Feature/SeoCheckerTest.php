<?php

namespace Tests\Feature;

use App\Mail\NewEnquiry;
use App\Mail\SeoTips;
use App\Models\ContactMessage;
use App\Support\SafeFetch;
use App\Support\SeoCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Free SEO checker (/seo-checker). */
class SeoCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $html, array $headers = []): array
    {
        return ['ok' => true, 'url' => 'https://example.com/', 'status' => 200, 'headers' => $headers + ['content-type' => ['text/html']], 'body' => $html, 'ms' => 300, 'redirects' => 0];
    }

    private function report(string $html, array $headers = []): array
    {
        return SeoCheck::analyse('https://example.com', $this->page($html, $headers), ['ok' => false], 'https://example.com');
    }

    private function check(array $report, string $title): array
    {
        return collect($report['checks'])->firstWhere('title', $title);
    }

    public function test_page_loads_and_is_linked(): void
    {
        $this->get('/seo-checker')->assertOk()->assertSee('id="seo-form"', false);
        $this->get('/')->assertSee('href="'.route('seo-checker').'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.route('seo-checker').'</loc>', false);
    }

    public function test_internal_and_private_addresses_are_never_fetched(): void
    {
        foreach (['http://localhost/', 'http://127.0.0.1/', 'http://10.0.0.5/', 'http://[::1]/', 'file:///etc/passwd', 'http://example.com:8080/'] as $url) {
            $this->assertFalse(SafeFetch::get($url)['ok'], $url);
        }
        $this->postJson('/seo-checker', ['url' => 'localhost'])->assertStatus(422);
    }

    public function test_a_bare_page_gets_the_right_problems_and_tips(): void
    {
        $r = $this->report('<html><body><p>Hello</p><img src="a.jpg"></body></html>');

        $this->assertSame('fail', $this->check($r, 'Page title')['status']);
        $this->assertSame('fail', $this->check($r, 'Meta description')['status']);
        $this->assertSame('fail', $this->check($r, 'H1 heading')['status']);
        $this->assertSame('fail', $this->check($r, 'Mobile friendly (viewport)')['status']);
        $this->assertSame('fail', $this->check($r, 'Image alt text')['status']);
        $this->assertNotEmpty($this->check($r, 'Page title')['tip']);
        $this->assertSame('poor', $r['rating']);
        $this->assertSame('fail', $r['checks'][0]['status']); // problems first
    }

    public function test_a_well_built_page_scores_well(): void
    {
        $words = str_repeat('Our team builds fast websites and apps for growing businesses. ', 60);
        $html = '<!doctype html><html lang="en"><head><title>Web App Development Services for Growing Businesses</title>
            <meta name="description" content="We design, build and launch web apps, mobile apps and AI tools for growing businesses. Free quote within 24 hours, and you own all the code.">
            <meta name="viewport" content="width=device-width, initial-scale=1"><link rel="canonical" href="https://example.com/"><link rel="icon" href="/f.svg">
            <meta property="og:title" content="x"><meta property="og:description" content="x"><meta property="og:image" content="x"><meta name="twitter:card" content="summary">
            <script type="application/ld+json">{}</script></head><body><h1>Web apps</h1><h2>How it works</h2><p>'.$words.'</p>
            <a href="/a">A</a><a href="/b">B</a><a href="/c">C</a><img src="x.jpg" alt="Team"></body></html>';
        $r = $this->report($html, ['content-encoding' => ['br']]);

        $this->assertGreaterThanOrEqual(85, $r['score']);
        $this->assertSame('pass', $this->check($r, 'Page title')['status']);
        $this->assertSame('pass', $this->check($r, 'H1 heading')['status']);
    }

    public function test_noindex_is_flagged(): void
    {
        $r = $this->report('<html><head><meta name="robots" content="noindex, nofollow"></head><body></body></html>');
        $this->assertSame('fail', $this->check($r, 'Indexable by Google')['status']);
    }

    public function test_emailing_tips_saves_a_lead_and_sends_the_report(): void
    {
        Mail::fake();
        config(['admin.email' => 'owner@owlvix.com']);
        Cache::put('seocheck:'.sha1('https://example.com'), $this->report('<html><body>Hi</body></html>'), 3600);

        $this->postJson('/seo-checker/email', ['url' => 'example.com', 'email' => 'visitor@example.org', 'name' => 'Sam Lee'])
            ->assertOk()->assertJson(['ok' => true]);

        $lead = ContactMessage::first();
        $this->assertSame(['visitor@example.org', 'SEO', 'seo-checker', 'SEO checker'], [$lead->email, $lead->service, $lead->source, $lead->sourceLabel()]);
        Mail::assertSent(SeoTips::class, fn ($m) => $m->hasTo('visitor@example.org'));
        Mail::assertSent(NewEnquiry::class, fn ($m) => $m->hasTo('owner@owlvix.com'));
        $this->assertStringContainsString('Your SEO tips for example.com', (new SeoTips($lead ? Cache::get('seocheck:'.sha1('https://example.com')) : [], 'Sam'))->envelope()->subject);
    }

    public function test_tips_email_renders(): void
    {
        $html = (new SeoTips($this->report('<html><body>Hi</body></html>'), 'Sam Lee'))->render();
        $this->assertStringContainsString('Hi Sam, here are your SEO tips', $html);
        $this->assertStringContainsString('Fix first', $html);
    }
}
