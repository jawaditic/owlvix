<?php

namespace Tests\Feature;

use App\Mail\AuditRequested;
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.recaptcha.site_key' => null]); // the captcha has its own test below
    }

    public function test_email_forms_need_the_captcha_and_cap_emails_per_address(): void
    {
        Mail::fake();
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => 'secret']);
        $this->postJson('/seo-checker/audit', ['url' => 'example.com', 'name' => 'Ana', 'email' => 'ana@example.org'])
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->postJson('/seo-checker/email', ['url' => 'example.com', 'email' => 'ana@example.org'])->assertStatus(422);
        $this->assertSame(0, ContactMessage::count());

        // At most 3 confirmation emails a day to one address, however they're asked for
        $this->assertTrue(\App\Support\Enquiries::mayEmail('victim@example.org', '1.1.1.1'));
        $this->assertTrue(\App\Support\Enquiries::mayEmail('Victim@example.org', '2.2.2.2'));
        $this->assertTrue(\App\Support\Enquiries::mayEmail('victim@example.org', '3.3.3.3'));
        $this->assertFalse(\App\Support\Enquiries::mayEmail('victim@example.org', '4.4.4.4'));
        $this->assertTrue(\App\Support\Enquiries::mayEmail('someone@example.org', '4.4.4.4'));
    }

    private function page(string $html, array $headers = []): array
    {
        return ['ok' => true, 'url' => 'https://example.com/', 'status' => 200, 'headers' => $headers + ['content-type' => ['text/html']], 'body' => $html, 'ms' => 300, 'redirects' => 0];
    }

    private function report(string $html, array $headers = []): array
    {
        return SeoCheck::analyse('https://example.com', $this->page($html, $headers), ['ok' => false], 'https://example.com');
    }

    private function check(array $report, string $title): ?array
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

    /** Checks adapted from claude-seo. */
    public function test_robots_txt_ai_crawler_rules_are_understood(): void
    {
        $robots = "User-agent: GPTBot\nDisallow: /\n\nUser-agent: PerplexityBot\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: *\nAllow: /\nDisallow: /admin";
        $this->assertTrue(SeoCheck::robotsBlocks($robots, 'GPTBot'));
        $this->assertTrue(SeoCheck::robotsBlocks($robots, 'PerplexityBot'));
        $this->assertTrue(SeoCheck::robotsBlocks($robots, 'CCBot'));
        $this->assertFalse(SeoCheck::robotsBlocks($robots, 'ClaudeBot')); // falls back to "*", which allows
        $this->assertTrue(SeoCheck::robotsBlocks("User-agent: *\nDisallow: /", 'OAI-SearchBot'));
    }

    public function test_mixed_content_lazy_main_image_and_identical_title_h1(): void
    {
        $r = $this->report('<html><head><title>Best Shoes</title><meta name="description" content="Best Shoes for every runner"></head>
            <body><h1>Best Shoes</h1><img src="http://cdn.example.com/hero.jpg" alt="Shoe" loading="lazy"></body></html>');

        $this->assertSame('fail', $this->check($r, 'Mixed content')['status']);
        $this->assertSame('warn', $this->check($r, 'Main image loading')['status']);
        $this->assertSame('warn', $this->check($r, 'Title vs H1')['status']);
        $this->assertSame('warn', $this->check($r, 'Unique meta description')['status']); // starts with the title
    }

    public function test_readability_is_scored_for_english_text(): void
    {
        $easy = str_repeat('We build apps. They are fast. You own the code. ', 40);
        $hard = str_repeat('Comprehensive organizational transformation necessitates multidimensional stakeholder alignment, institutionalized governance frameworks and continuous operational optimization initiatives. ', 15);
        $this->assertSame('pass', $this->check($this->report("<html lang=\"en\"><body><p>$easy</p></body></html>"), 'Readability')['status']);
        $this->assertSame('fail', $this->check($this->report("<html lang=\"en\"><body><p>$hard</p></body></html>"), 'Readability')['status']);
        $this->assertNull($this->check($this->report('<html lang="de"><body><p>'.$easy.'</p></body></html>'), 'Readability')); // not measured for other languages
    }

    public function test_full_ai_audit_request_saves_a_lead_and_emails_both_sides(): void
    {
        Mail::fake();
        config(['admin.email' => 'owner@owlvix.com']);
        Cache::put('seocheck:'.sha1('https://example.com'), $this->report('<html><body>Hi</body></html>'), 3600);

        $this->postJson('/seo-checker/audit', ['url' => 'example.com', 'name' => 'Sam Lee', 'email' => 'sam@example.org', 'notes' => 'Ranking for dentist London'])
            ->assertOk()->assertJson(['ok' => true]);

        $lead = ContactMessage::where('source', 'ai-audit')->first();
        $this->assertSame(['Sam Lee', 'SEO audit request'], [$lead->name, $lead->sourceLabel()]);
        $this->assertStringContainsString('/seo audit https://example.com', $lead->message);
        $this->assertStringContainsString('dentist London', $lead->message);
        Mail::assertSent(AuditRequested::class, fn ($m) => $m->hasTo('sam@example.org'));
        Mail::assertSent(NewEnquiry::class, fn ($m) => $m->hasTo('owner@owlvix.com'));
        $this->assertStringContainsString('Your full audit is on its way', (new AuditRequested($lead, 'https://example.com'))->render());

        // Asking again the same day doesn't create a duplicate lead
        $this->postJson('/seo-checker/audit', ['url' => 'example.com', 'name' => 'Sam Lee', 'email' => 'sam@example.org'])->assertJson(['already' => true]);
        $this->assertSame(1, ContactMessage::where('source', 'ai-audit')->count());
    }

    public function test_audit_request_needs_name_and_email(): void
    {
        $this->postJson('/seo-checker/audit', ['url' => 'example.com'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_standalone_audit_page_and_menu_link(): void
    {
        Mail::fake();
        $this->get('/seo-audit')->assertOk()->assertSee('name="url" inputmode="url"', false);
        $this->get('/')->assertSee('href="'.route('seo-audit').'"', false);
        // Works without running the checker first
        $this->postJson('/seo-checker/audit', ['url' => 'new-site.example', 'name' => 'Ana', 'email' => 'ana@example.org'])->assertOk();
        $this->assertStringNotContainsString('free checker score', ContactMessage::where('source', 'ai-audit')->first()->message);
    }
}
