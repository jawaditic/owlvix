<?php

namespace Tests\Feature;

use App\Mail\BuilderFormMessage;
use App\Mail\BuilderSignInCode;
use App\Mail\BuilderSiteReported;
use App\Models\BuilderAccount;
use App\Models\BuilderForm;
use App\Models\BuilderSite;
use App\Support\BuilderHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Free landing page builder: sign-in, contact forms, publishing to /p/{slug} and admin moderation. */
class BuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('builder');
    }

    private function signIn(string $email = 'maker@example.com'): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => $email])->assertOk()->assertJson(['ok' => true]);
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return $this->postJson('/builder/account/verify', ['email' => $email, 'code' => $code])
            ->assertOk()->assertJson(['ok' => true, 'email' => $email])->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['X-Builder-Token' => $token]);
    }

    private function page(string $body = '<h1>Hello</h1>', string $slug = 'index'): array
    {
        return ['slug' => $slug, 'title' => 'Home', 'html' => '<!DOCTYPE html><html><head><title>Hi</title></head><body>'.$body.'</body></html>'];
    }

    private function publish(string $token, array $pages, string $slug = 'joes-bakery')
    {
        return $this->api($token)->postJson('/builder/publish', ['slug' => $slug, 'name' => "Joe's Bakery", 'pages' => $pages]);
    }

    public function test_sign_in_with_an_emailed_code(): void
    {
        $token = $this->signIn();
        $this->assertGreaterThanOrEqual(48, strlen($token));
        $this->assertDatabaseMissing('builder_tokens', ['token_hash' => $token]);

        // A second device gets its own token; the first keeps working
        $second = $this->signIn();
        $this->assertNotSame($token, $second);
        $this->api($token)->getJson('/builder/account')->assertOk()->assertJson(['ok' => true, 'email' => 'maker@example.com', 'sites' => [], 'forms' => []]);
        $this->api($second)->getJson('/builder/account')->assertOk();
        $this->assertSame(1, BuilderAccount::count());
    }

    public function test_wrong_codes_and_rate_limits(): void
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => 'x@example.com'])->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/builder/account/verify', ['email' => 'x@example.com', 'code' => 'abcdef'])->assertStatus(422)->assertJson(['ok' => false]);
        }
        // The fifth wrong try uses up the code
        $this->postJson('/builder/account/verify', ['email' => 'x@example.com', 'code' => 'abcdef'])->assertStatus(422)->assertJsonPath('error', 'Too many wrong codes. Please ask for a new one.');
        $this->postJson('/builder/account/verify', ['email' => 'x@example.com', 'code' => '123456'])->assertJsonPath('error', 'That code has expired. Please ask for a new one.');

        $this->postJson('/builder/account/code', ['email' => 'x@example.com'])->assertOk();
        $this->postJson('/builder/account/code', ['email' => 'x@example.com'])->assertOk();
        $this->postJson('/builder/account/code', ['email' => 'x@example.com'])->assertStatus(429)->assertJson(['ok' => false]);
        $this->postJson('/builder/account/code', ['email' => 'nope'])->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_a_token_is_required(): void
    {
        $this->getJson('/builder/account')->assertStatus(401)->assertJson(['ok' => false]);
        $this->api(str_repeat('a', 64))->postJson('/builder/forms', ['site_name' => 'X'])->assertStatus(401);
        $this->postJson('/builder/publish', [])->assertStatus(401);
    }

    public function test_forms_are_created_once_per_site_name(): void
    {
        $token = $this->signIn();
        $form = $this->api($token)->postJson('/builder/forms', ['site_name' => "Joe's Bakery"])->assertOk()->json('form');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $form['id']);
        $this->assertSame(url('/f/'.$form['id']), $form['action']);

        $again = $this->api($token)->postJson('/builder/forms', ['site_name' => "Joe's Bakery"])->json('form');
        $this->assertSame($form['id'], $again['id']);

        $this->api($token)->getJson('/builder/account')
            ->assertJsonPath('forms.0', ['id' => $form['id'], 'site_name' => "Joe's Bakery", 'action' => $form['action'], 'kind' => 'contact', 'submissions' => 0, 'leads' => 0]);
    }

    public function test_publish_and_serve_with_the_sandbox(): void
    {
        $token = $this->signIn();
        $form = $this->api($token)->postJson('/builder/forms', ['site_name' => "Joe's Bakery"])->json('form');
        $html = '<form method="POST" action="'.$form['action'].'"><input name="name"></form>';

        $this->publish($token, [$this->page($html), $this->page('<p>About us</p>', 'about')])
            ->assertOk()->assertJson(['ok' => true, 'slug' => 'joes-bakery', 'url' => url('/p/joes-bakery')]);

        $res = $this->get('/p/joes-bakery')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('Made with', false)
            ->assertSee(url('/p/joes-bakery/report'), false);
        $csp = $res->headers->get('Content-Security-Policy');
        // Scripts may run, but only owlvix's runtime file, in an opaque origin (no allow-same-origin)
        $this->assertStringStartsWith('sandbox allow-scripts allow-forms', $csp);
        $this->assertStringContainsString("script-src ".rtrim(url('/'), '/').'/js/builder-runtime.js;', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString('form-action '.rtrim(url('/'), '/'), $csp);
        $this->assertStringContainsString('connect-src '.rtrim(url('/'), '/').'/fs;', $csp);
        $this->assertMatchesRegularExpression('#Made with.*</body>#s', $res->getContent());

        $this->get('/p/joes-bakery/about')->assertOk()->assertSee('About us');
        $this->get('/p/joes-bakery/missing')->assertNotFound();
        $this->get('/p/nobody-here')->assertNotFound();
        $this->assertSame(2, BuilderSite::first()->views);

        $this->api($token)->getJson('/builder/account')
            ->assertJsonPath('sites.0.slug', 'joes-bakery')
            ->assertJsonPath('sites.0.status', 'live')
            ->assertJsonPath('sites.0.pages', [['slug' => 'index', 'title' => 'Home'], ['slug' => 'about', 'title' => 'Home']]);

        // Republishing replaces the pages
        $this->publish($token, [$this->page('<h1>New</h1>')])->assertOk();
        $this->get('/p/joes-bakery')->assertSee('New');
        $this->get('/p/joes-bakery/about')->assertNotFound();
        Storage::disk('builder')->assertMissing(BuilderSite::first()->id.'/about.html');
    }

    public function test_unsafe_html_is_rejected(): void
    {
        $token = $this->signIn();
        foreach ([
            '<script>alert(1)</script>',
            '<script src="https://evil.example/js/builder-runtime.js" defer></script>',
            '<script src="'.url('/js/builder-runtime.js').'" defer></script><script>alert(1)</script>',
            '<img src="x" onerror="alert(1)">',
            '<a href="javascript:alert(1)">x</a>',
            '<a href="jav&#x61;script:alert(1)">x</a>',
            '<object data="x"></object>',
            '<embed src="x">',
            '<base href="https://evil.example/">',
            '<meta http-equiv="refresh" content="0;url=https://evil.example">',
            '<input type="password" name="p">',
            '<iframe src="https://evil.example/"></iframe>',
            '<form action="https://evil.example/collect"></form>',
            '<form action="https://owlvix.com/f/abcdefghijklmnop"></form>',
        ] as $bad) {
            $this->publish($token, [$this->page($bad)])->assertStatus(422)->assertJson(['ok' => false]);
        }
        $this->assertSame(0, BuilderSite::count());

        $this->publish($token, [$this->page('<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe><iframe src="https://www.google.com/maps/embed?pb=1"></iframe>')])->assertOk();
        // owlvix's own runtime script is the one script allowed
        $this->publish($token, [$this->page(\App\Support\BuilderHtml::runtimeTag().'<div data-countdown="2030-01-01">x</div>')])->assertOk();
    }

    public function test_a_form_from_another_account_is_rejected(): void
    {
        $other = $this->signIn('other@example.com');
        $action = $this->api($other)->postJson('/builder/forms', ['site_name' => 'Other'])->json('form.action');

        $token = $this->signIn();
        $this->publish($token, [$this->page('<form method="POST" action="'.$action.'"></form>')])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'another account'));
    }

    public function test_slugs_must_be_free_valid_and_not_reserved(): void
    {
        $first = $this->signIn('first@example.com');
        $this->publish($first, [$this->page()])->assertOk();

        $token = $this->signIn();
        $this->publish($token, [$this->page()])->assertStatus(422)->assertJsonPath('error', 'That address is taken. Please choose another one.');
        $this->publish($token, [$this->page()], 'admin')->assertStatus(422);
        $this->publish($token, [$this->page()], '-bad')->assertStatus(422);
        $this->publish($token, [$this->page()], 'A')->assertStatus(422);
        $this->publish($token, [$this->page(), $this->page()], 'two-same')->assertStatus(422);
        $this->publish($token, [$this->page('x', 'report')], 'reportish')->assertStatus(422);

        // Up to 3 sites each
        foreach (['one-site', 'two-site', 'three-site'] as $slug) {
            $this->publish($token, [$this->page()], $slug)->assertOk();
        }
        $this->publish($token, [$this->page()], 'four-site')->assertStatus(422);
    }

    public function test_form_messages_are_emailed_to_the_owner(): void
    {
        $token = $this->signIn();
        $form = $this->api($token)->postJson('/builder/forms', ['site_name' => "Joe's Bakery"])->json('form');
        Mail::fake();

        $post = fn (array $data) => $this->post('/f/'.$form['id'], $data + [
            'name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Do you deliver?', '_back' => 'https://joes.example/contact',
        ]);

        $post([])->assertStatus(303)->assertRedirect(route('builder.form.sent', ['id' => $form['id'], 'back' => 'https://joes.example/contact']));
        Mail::assertSent(BuilderFormMessage::class, fn ($m) => $m->hasTo('maker@example.com') && $m->hasReplyTo('ann@example.com')
            && $m->envelope()->subject === "New message from your website: Joe's Bakery");
        $this->assertSame(1, BuilderForm::first()->submissions);

        // Honeypot: looks like success, nothing sent
        $post(['_hp' => 'spam'])->assertStatus(303);
        Mail::assertSentCount(1);

        // Too many links, or a missing field: a "not sent" page
        $post(['message' => 'http://a.com http://b.com http://c.com www.d.com'])->assertStatus(422)->assertSee('no more than 3 links');
        $post(['email' => 'nope'])->assertStatus(422)->assertSee('valid email');
        Mail::assertSentCount(1);

        // Per-visitor limit
        for ($i = 0; $i < 4; $i++) {
            $post([])->assertStatus(303);
        }
        $post([])->assertStatus(429);

        $this->get('/f/'.$form['id'].'/sent?back=https://joes.example/contact')->assertOk()
            ->assertSee('Message sent')->assertSee('href="https://joes.example/contact"', false)->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/f/'.$form['id'].'/sent?back=javascript:alert(1)')->assertOk()->assertDontSee('javascript:', false);
        $this->post('/f/zzzzzzzzzzzzzzzz', ['name' => 'A'])->assertNotFound();
    }

    public function test_the_form_endpoint_needs_no_csrf_token(): void
    {
        // Static pages hosted anywhere post to /f/{id}, so it must be excluded from CSRF checks
        $this->assertContains('f/*', app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->getExcludedPaths());
    }

    public function test_unpublish(): void
    {
        $token = $this->signIn();
        $this->publish($token, [$this->page()])->assertOk();
        $this->api($token)->postJson('/builder/unpublish', ['slug' => 'joes-bakery'])->assertOk()->assertJson(['ok' => true]);
        $this->get('/p/joes-bakery')->assertNotFound();
        $this->api($token)->getJson('/builder/account')->assertJsonPath('sites.0.status', 'removed');

        // Only the owner can unpublish; the owner can publish it again
        $other = $this->signIn('other@example.com');
        $this->api($other)->postJson('/builder/unpublish', ['slug' => 'joes-bakery'])->assertNotFound();
        $this->publish($token, [$this->page()])->assertOk();
        $this->get('/p/joes-bakery')->assertOk();
    }

    public function test_report_and_admin_takedown(): void
    {
        config(['admin.email' => 'owner@owlvix.test']);
        $token = $this->signIn();
        $this->publish($token, [$this->page()])->assertOk();
        $site = BuilderSite::first();

        $this->get('/p/joes-bakery/report')->assertOk()->assertSee('Report this page');
        Mail::fake();
        $this->post('/p/joes-bakery/report', ['reason' => 'This is a phishing page'])->assertRedirect('/p/joes-bakery/report');
        Mail::assertSent(BuilderSiteReported::class, fn ($m) => $m->hasTo('owner@owlvix.test'));
        $this->assertNotNull($site->fresh()->reported_at);

        $this->get('/admin/builder')->assertRedirect(route('admin.login'));
        $this->withSession(['is_admin' => true]);
        $this->get('/admin/builder')->assertOk()->assertSee('/p/joes-bakery')->assertSee('maker@example.com')->assertSee('Take down');

        $this->post("/admin/builder/sites/{$site->id}/takedown")->assertRedirect();
        $this->get('/p/joes-bakery')->assertNotFound();
        $this->publish($token, [$this->page()])->assertStatus(422);

        $this->post("/admin/builder/sites/{$site->id}/restore")->assertRedirect();
        $this->get('/p/joes-bakery')->assertOk();
        $this->assertNull($site->fresh()->reported_at);
    }

    public function test_the_bar_is_added_before_the_body_ends(): void
    {
        $site = new BuilderSite(['slug' => 'abc']);
        $this->assertStringEndsWith('</div></body></html>', BuilderHtml::withBar('<html><body><p>x</p></body></html>', $site));
        $this->assertStringContainsString('Report', BuilderHtml::withBar('<p>no body tag</p>', $site));
    }
}
