<?php

namespace Tests\Feature;

use App\Mail\BuilderSignInCode;
use App\Models\BuilderSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Published builder sites on their own subdomain (BUILDER_DOMAIN), e.g. https://sites.owlvix.com/{name}. */
class BuilderSubdomainTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = 'sites.owlvix.test';

    protected function setUp(): void
    {
        // Routes are registered from config when the app boots, so set it before
        putenv('BUILDER_DOMAIN='.self::DOMAIN);
        $_ENV['BUILDER_DOMAIN'] = $_SERVER['BUILDER_DOMAIN'] = self::DOMAIN;
        parent::setUp();
        Storage::fake('builder');
    }

    protected function tearDown(): void
    {
        putenv('BUILDER_DOMAIN');
        unset($_ENV['BUILDER_DOMAIN'], $_SERVER['BUILDER_DOMAIN']);
        parent::tearDown();
    }

    private function publishSite(): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => 'maker@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $token = $this->postJson('/builder/account/verify', ['email' => 'maker@example.com', 'code' => $code])->json('token');
        $html = fn ($t) => '<!DOCTYPE html><html><head><title>'.$t.'</title></head><body><h1>'.$t.'</h1></body></html>';

        return $this->withHeaders(['X-Builder-Token' => $token])->postJson('/builder/publish', ['slug' => 'joes-bakery', 'name' => "Joe's Bakery", 'pages' => [
            ['slug' => 'index', 'title' => 'Home', 'html' => $html('Home page')],
            ['slug' => 'about', 'title' => 'About', 'html' => $html('About page')],
        ]])->assertOk()->json('url');
    }

    public function test_sites_are_served_on_the_subdomain(): void
    {
        // Same scheme as the main site (https on owlvix.com)
        $base = (parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.self::DOMAIN.'/';
        $this->assertSame($base, BuilderSite::base());
        $url = $this->publishSite();
        $this->assertSame($base.'joes-bakery', $url);

        $res = $this->get('https://'.self::DOMAIN.'/joes-bakery')->assertOk()->assertSee('Home page');
        $this->assertStringContainsString('sandbox allow-scripts', $res->headers->get('Content-Security-Policy'));
        $this->assertSame('noindex', $res->headers->get('X-Robots-Tag'));
        $res->assertSee(self::DOMAIN.'/joes-bakery/report', false); // report link in the bar
        $this->get('https://'.self::DOMAIN.'/joes-bakery/about')->assertOk()->assertSee('About page');
        $this->get('https://'.self::DOMAIN.'/joes-bakery/report')->assertOk();
        $this->get('https://'.self::DOMAIN.'/nobody-here')->assertStatus(301); // not a site: off to the main domain
    }

    public function test_old_links_and_other_pages_redirect(): void
    {
        $this->publishSite();
        $main = rtrim(config('app.url'), '/');
        // Old /p/ links move to the subdomain
        $this->get($main.'/p/joes-bakery/about')->assertRedirect(BuilderSite::base().'joes-bakery/about')->assertStatus(301);
        // The subdomain's home goes to the builder; main-site pages and the admin are never served on it
        $this->get('https://'.self::DOMAIN.'/')->assertRedirect(route('landing-builder'));
        $this->get('https://'.self::DOMAIN.'/admin/builder')->assertStatus(301)->assertRedirect($main.'/admin/builder');
        $this->get('https://'.self::DOMAIN.'/website-speed-test')->assertStatus(301)->assertRedirect($main.'/website-speed-test');
        // The main site itself is unaffected
        $this->get($main.'/')->assertOk();
    }
}
