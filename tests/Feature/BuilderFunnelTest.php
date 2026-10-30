<?php

namespace Tests\Feature;

use App\Http\Controllers\BuilderController;
use App\Mail\BuilderFormMessage;
use App\Mail\BuilderLeadMessage;
use App\Mail\BuilderSignInCode;
use App\Models\BuilderForm;
use App\Models\BuilderLead;
use App\Models\BuilderSite;
use App\Models\BuilderStat;
use App\Support\BuilderHtml;
use App\Support\LeadIntegrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Funnel builder server side: lead forms, stored leads, step stats beacon, email-tool integrations. */
class BuilderFunnelTest extends TestCase
{
    use RefreshDatabase;

    private const MC_KEY = '0123456789abcdef' . '0123456789abcdef' . '-us21'; // fake key, split so secret scanners skip it

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('builder');
        // No real DNS in tests: hooks.example.com is public, everything else isn't
        LeadIntegrations::$resolver = fn (string $host) => $host === 'hooks.example.com' ? '93.184.216.34' : null;
    }

    protected function tearDown(): void
    {
        LeadIntegrations::$resolver = null;
        parent::tearDown();
    }

    private function signIn(string $email = 'maker@example.com'): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => $email])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use ($email, &$code) {
            $code = $m->code;

            return $m->hasTo($email);
        });

        return $this->postJson('/builder/account/verify', ['email' => $email, 'code' => $code])->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['X-Builder-Token' => $token]);
    }

    /** A funnel "joes-funnel" with steps optin → thanks, whose opt-in page has the lead form. */
    private function funnel(string $token): array
    {
        $form = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe’s Funnel', 'kind' => 'leads'])->json('form');
        $optin = '<!DOCTYPE html><html data-fs-site="joes-funnel" data-fs-step="optin" data-fs-v="a"><head><title>Optin</title></head><body>'
            .'<form method="POST" action="'.$form['action'].'">'
            .'<input name="name"><input type="email" name="email"><input name="phone">'
            .'<input type="hidden" name="_hp"><input type="hidden" name="_back" value="'.url('/p/joes-funnel').'">'
            .'<input type="hidden" name="_next" value="'.url('/p/joes-funnel/thanks').'">'
            .'<input type="hidden" name="_site" value="joes-funnel"><input type="hidden" name="_step" value="optin"><input type="hidden" name="_v" value="a">'
            .'<button>Get it</button></form></body></html>';
        $thanks = '<!DOCTYPE html><html><head><title>Thanks</title></head><body><h1>Thanks</h1></body></html>';
        $this->api($token)->postJson('/builder/publish', ['slug' => 'joes-funnel', 'name' => 'Joe’s Funnel', 'pages' => [
            ['slug' => 'optin', 'title' => 'Opt-in', 'html' => $optin],
            ['slug' => 'thanks', 'title' => 'Thanks', 'html' => $thanks],
        ]])->assertOk()->assertJson(['ok' => true]);

        return $form;
    }

    private function lead(string $formId, array $fields = [])
    {
        return $this->post('/f/'.$formId, $fields + [
            'name' => 'Ann Lee', 'email' => 'ann@example.com', 'phone' => '+44 20 1234 5678',
            '_back' => url('/p/joes-funnel'), '_next' => url('/p/joes-funnel/thanks'),
            '_site' => 'joes-funnel', '_step' => 'optin', '_v' => 'b',
        ]);
    }

    public function test_lead_forms_are_their_own_kind(): void
    {
        $token = $this->signIn();
        $contact = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe’s Funnel'])->assertOk()->json('form');
        $leads = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe’s Funnel', 'kind' => 'leads'])->assertOk()->json('form');
        $this->assertSame('contact', $contact['kind']);
        $this->assertSame('leads', $leads['kind']);
        $this->assertNotSame($contact['id'], $leads['id']);
        $this->assertSame(url('/f/'.$leads['id']), $leads['action']);

        // The same name and kind reuses the form
        $this->assertSame($leads['id'], $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe’s Funnel', 'kind' => 'leads'])->json('form.id'));
        $this->api($token)->postJson('/builder/forms', ['site_name' => 'X', 'kind' => 'poll'])->assertStatus(422)->assertJson(['ok' => false]);

        $this->api($token)->getJson('/builder/account')
            ->assertJsonPath('forms.1', ['id' => $leads['id'], 'site_name' => 'Joe’s Funnel', 'action' => $leads['action'], 'kind' => 'leads', 'submissions' => 0, 'leads' => 0]);
    }

    public function test_a_lead_is_stored_emailed_counted_and_sent_to_the_next_step(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);

        $this->lead($form['id'])->assertStatus(303)->assertRedirect(url('/p/joes-funnel/thanks'));

        $lead = BuilderLead::sole();
        $site = BuilderSite::where('slug', 'joes-funnel')->sole();
        $this->assertSame(['Ann Lee', 'ann@example.com', '+44 20 1234 5678', 'optin', 'b', $site->id],
            [$lead->name, $lead->email, $lead->phone, $lead->step, $lead->variant, $lead->builder_site_id]);
        Mail::assertSent(BuilderLeadMessage::class, fn ($m) => $m->hasTo('maker@example.com') && $m->hasReplyTo('ann@example.com')
            && $m->envelope()->subject === 'New lead from Joe’s Funnel');
        $stat = BuilderStat::sole();
        $this->assertSame(['optin', 'b', 0, 1], [$stat->step, $stat->variant, $stat->views, $stat->leads]);

        // Allowed next steps: the app's own host (above), the host the browser says the form came from, the builder domain
        $this->withHeader('Referer', 'https://joes.example/page')->lead($form['id'], ['_next' => 'https://joes.example/thanks'])->assertRedirect('https://joes.example/thanks');
        config(['builder.domain' => 'sites.owlvix.test']);
        $this->lead($form['id'], ['_next' => 'https://sites.owlvix.test/joes-funnel/thanks'])->assertRedirect('https://sites.owlvix.test/joes-funnel/thanks');

        // Not an open redirect: a host named only in the posted _back/_next is never trusted — even for the honeypot
        foreach ([[], ['_hp' => 'x']] as $extra) {
            $res = $this->lead($form['id'], $extra + ['_back' => 'https://evil.example/', '_next' => 'https://evil.example/phish'])->assertStatus(303);
            $this->assertStringStartsWith(url('/f/'.$form['id'].'/sent'), $res->headers->get('Location'));
        }
        $this->withHeader('Referer', '')->flushHeaders();

        // A foreign host (or a non-http address) goes to the usual "sent" page instead
        foreach (['https://evil.example/phish', 'javascript:alert(1)', '//evil.example/x'] as $next) {
            $res = $this->lead($form['id'], ['_next' => $next])->assertStatus(303);
            $this->assertStringStartsWith(url('/f/'.$form['id'].'/sent'), $res->headers->get('Location'));
        }
        $this->get('/f/'.$form['id'].'/sent')->assertOk()->assertSee('You’re signed up');

        // A site of another account (or an unknown step) isn't counted
        $this->lead($form['id'], ['_step' => 'nope']);
        $this->lead($form['id'], ['_site' => 'someone-else']);
        $this->assertSame(7, (int) BuilderStat::sum('leads'));
        $this->assertNull(BuilderLead::latest('id')->first()->builder_site_id);
        $this->assertSame(9, BuilderLead::count());

        // Admin sees the kind and the number of stored leads
        $this->flushHeaders()->withSession(['is_admin' => true])->get('/admin/builder')->assertOk()
            ->assertSee('Lead form')->assertSee('9 leads stored')->assertSee('Funnel leads');
    }

    public function test_form_posts_never_start_a_session(): void
    {
        // Posted from other people's pages, often in a browser signed in to owlvix.com — a new session would sign them out
        $token = $this->signIn();
        $leads = $this->funnel($token);
        $contact = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe’s Funnel'])->json('form');
        foreach ([$this->lead($leads['id']), $this->post('/f/'.$contact['id'], ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello there, a question.', '_back' => 'https://example.com/']),
            $this->get('/f/'.$leads['id'].'/sent'), $this->get('/p/joes-funnel/thanks')] as $res) {
            $this->assertSame([], $res->headers->getCookies(), 'no cookies set');
        }
    }

    public function test_lead_form_spam_checks(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);

        // Honeypot: looks like it worked, nothing stored or sent
        $this->lead($form['id'], ['_hp' => 'gotcha'])->assertRedirect(url('/p/joes-funnel/thanks'));
        $this->assertSame(0, BuilderLead::count());
        Mail::assertNotSent(BuilderLeadMessage::class);

        $this->lead($form['id'], ['email' => 'not-an-email'])->assertStatus(422)->assertSee('valid email')->assertSee(url('/p/joes-funnel'), false);
        $this->lead($form['id'], ['name' => 'http://a.co http://b.co http://c.co http://d.co'])->assertStatus(422)->assertSee('no more than 3 links');

        for ($i = 0; $i < 10; $i++) {
            $this->lead($form['id'])->assertStatus(303);
        }
        $this->lead($form['id'])->assertStatus(429);
        $this->assertSame(10, BuilderLead::count());
    }

    public function test_contact_forms_work_as_before(): void
    {
        $token = $this->signIn();
        $form = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Joe'])->json('form');

        $this->post('/f/'.$form['id'], ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello there', '_next' => url('/elsewhere')])
            ->assertStatus(303)->assertRedirect(route('builder.form.sent', ['id' => $form['id'], 'back' => null]));
        Mail::assertSent(BuilderFormMessage::class);
        Mail::assertNotSent(BuilderLeadMessage::class);
        $this->assertSame(0, BuilderLead::count());
        $this->post('/f/'.$form['id'], ['email' => 'ann@example.com'])->assertStatus(422)->assertSee('Please enter your name.');
        $this->get('/f/'.$form['id'].'/sent')->assertSee('Message sent');
    }

    public function test_the_beacon_counts_views_of_live_sites_known_steps(): void
    {
        $token = $this->signIn();
        $this->funnel($token);
        $beacon = fn (string $body) => $this->call('POST', '/fs', [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8'], $body);

        $res = $beacon('{"s":"joes-funnel","p":"optin","v":"a"}')->assertNoContent();
        $this->assertSame('*', $res->headers->get('Access-Control-Allow-Origin'));
        $this->assertEmpty($res->headers->getCookies()); // no session for the beacon
        $beacon('{"s":"joes-funnel","p":"optin","v":"a"}');
        $beacon('{"s":"joes-funnel","p":"optin","v":"b"}');
        $beacon('{"s":"joes-funnel","p":"thanks"}');
        // Ignored: unknown step, unknown site, junk
        $beacon('{"s":"joes-funnel","p":"missing","v":"a"}')->assertNoContent();
        $beacon('{"s":"nobody-here","p":"optin"}')->assertNoContent();
        $beacon('not json')->assertNoContent();
        $beacon('{"s":["x"],"p":1}')->assertNoContent();

        $rows = BuilderStat::orderBy('step')->orderBy('variant')->get(['step', 'variant', 'views'])->toArray();
        $this->assertSame([
            ['step' => 'optin', 'variant' => 'a', 'views' => 2],
            ['step' => 'optin', 'variant' => 'b', 'views' => 1],
            ['step' => 'thanks', 'variant' => 'a', 'views' => 1],
        ], $rows);

        // An unpublished site isn't counted
        $this->api($token)->postJson('/builder/unpublish', ['slug' => 'joes-funnel'])->assertOk();
        $beacon('{"s":"joes-funnel","p":"optin","v":"a"}')->assertNoContent();
        $this->assertSame(4, (int) BuilderStat::sum('views'));
    }

    public function test_stats_are_totalled_for_the_owner_only(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $site = BuilderSite::where('slug', 'joes-funnel')->sole();
        DB::table('builder_stats')->insert([
            ['builder_site_id' => $site->id, 'step' => 'optin', 'variant' => 'a', 'day' => now()->subDays(3)->toDateString(), 'views' => 10, 'leads' => 2],
            ['builder_site_id' => $site->id, 'step' => 'optin', 'variant' => 'a', 'day' => now()->subDays(40)->toDateString(), 'views' => 99, 'leads' => 9],
            ['builder_site_id' => $site->id, 'step' => 'thanks', 'variant' => 'a', 'day' => now()->subDay()->toDateString(), 'views' => 4, 'leads' => 0],
        ]);
        $this->call('POST', '/fs', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"s":"joes-funnel","p":"optin","v":"a"}');
        $this->lead($form['id'], ['_v' => 'a']);

        $this->api($token)->getJson('/builder/stats?slug=joes-funnel')->assertOk()->assertExactJson(['ok' => true, 'days' => 30, 'steps' => [
            ['step' => 'optin', 'variant' => 'a', 'views' => 11, 'leads' => 3],
            ['step' => 'thanks', 'variant' => 'a', 'views' => 4, 'leads' => 0],
        ]]);

        $other = $this->signIn('other@example.com');
        $this->api($other)->getJson('/builder/stats?slug=joes-funnel')->assertNotFound()->assertJson(['ok' => false]);
        $this->flushHeaders()->getJson('/builder/stats?slug=joes-funnel')->assertUnauthorized();
    }

    public function test_leads_as_json_and_csv_for_the_owner_only(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $this->lead($form['id'], ['name' => 'First', 'email' => 'first@example.com']);
        $this->travel(1)->minutes();
        $this->lead($form['id'], ['name' => '=HYPERLINK("http://evil.example","x")', 'email' => 'second@example.com', 'phone' => '']);

        $this->api($token)->getJson('/builder/leads?form='.$form['id'])->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('total', 2)
            ->assertJsonPath('leads.0.email', 'second@example.com')
            ->assertJsonPath('leads.0.phone', null)
            ->assertJsonPath('leads.1', fn ($l) => $l['name'] === 'First' && $l['phone'] === '+44 20 1234 5678' && $l['step'] === 'optin' && $l['variant'] === 'b' && is_string($l['created_at']));

        $res = $this->api($token)->get('/builder/leads?form='.$form['id'].'&format=csv')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('leads-joes-funnel-'.now()->toDateString().'.csv', $res->headers->get('Content-Disposition'));
        $csv = $res->streamedContent();
        $this->assertStringContainsString("Date,Name,Email,Phone,Step,Variant\n", $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil.example"",""x"")",second@example.com', $csv);
        $this->assertStringContainsString("First,first@example.com,\"'+44 20 1234 5678\",optin,b", $csv);
        $this->assertSame("'-1", BuilderController::csvCell('-1'));
        $this->assertSame("'@SUM(A1)", BuilderController::csvCell('@SUM(A1)'));
        $this->assertSame('ann', BuilderController::csvCell('ann'));

        $other = $this->signIn('other@example.com');
        $this->api($other)->getJson('/builder/leads?form='.$form['id'])->assertNotFound();
        $this->api($other)->get('/builder/leads?form='.$form['id'].'&format=csv')->assertNotFound();
        $this->flushHeaders()->getJson('/builder/leads?form='.$form['id'])->assertUnauthorized();
    }

    public function test_integrations_are_saved_masked_and_removed(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $url = '/builder/forms/'.$form['id'].'/integrations';

        $this->api($token)->getJson($url)->assertOk()->assertExactJson(['ok' => true, 'integrations' => ['mailchimp' => null, 'brevo' => null, 'webhook' => null]]);

        $this->api($token)->postJson($url, [
            'mailchimp' => ['key' => self::MC_KEY, 'list' => 'abc123def'],
            'brevo' => ['key' => 'xkeysib-secret-wxyz', 'list' => '7'],
            'webhook' => ['url' => 'https://hooks.example.com/owlvix'],
        ])->assertOk()->assertExactJson(['ok' => true, 'integrations' => [
            'mailchimp' => ['list' => 'abc123def', 'key_hint' => '…cdef'],
            'brevo' => ['list' => '7', 'key_hint' => '…wxyz'],
            'webhook' => ['url' => 'https://hooks.example.com/owlvix'],
        ]]);
        // Stored encrypted, never returned
        $raw = DB::table('builder_forms')->where('id', $form['id'])->value('integrations');
        $this->assertStringNotContainsString('xkeysib', $raw);
        $this->assertStringNotContainsString('xkeysib', $this->api($token)->getJson('/builder/account')->getContent());
        $this->assertStringNotContainsString('xkeysib', $this->api($token)->getJson($url)->getContent());

        // An empty key keeps the saved one; null / empty list removes
        $this->api($token)->postJson($url, ['mailchimp' => ['key' => '', 'list' => 'newlist1'], 'brevo' => null, 'webhook' => ['url' => '']])
            ->assertOk()->assertJsonPath('integrations.mailchimp', ['list' => 'newlist1', 'key_hint' => '…cdef'])
            ->assertJsonPath('integrations.brevo', null)->assertJsonPath('integrations.webhook', null);
        $this->assertSame(self::MC_KEY, BuilderForm::find($form['id'])->integrations['mailchimp']['key']);
        $this->assertSame('us21', BuilderForm::find($form['id'])->integrations['mailchimp']['dc']);

        // Validation
        foreach ([
            ['webhook' => ['url' => 'http://127.0.0.1/hook']],
            ['webhook' => ['url' => 'https://127.0.0.1/hook']],
            ['webhook' => ['url' => 'http://hooks.example.com/hook']],
            ['webhook' => ['url' => 'https://internal.local/hook']],
            ['webhook' => ['url' => 'https://hooks.example.com:8443/hook']],
            ['mailchimp' => ['key' => 'not-a-key', 'list' => 'abc123']],
            ['mailchimp' => ['key' => self::MC_KEY, 'list' => 'a!']],
            ['brevo' => ['key' => '', 'list' => '7']],
            ['brevo' => ['key' => 'k', 'list' => 'abc']],
            ['brevo' => ['key' => str_repeat('k', 201), 'list' => '7']],
        ] as $body) {
            $this->api($token)->postJson($url, $body)->assertStatus(422)->assertJson(['ok' => false]);
        }

        $this->api($token)->postJson($url, ['mailchimp' => null])->assertOk()->assertJsonPath('integrations.mailchimp', null);
        $this->assertNull(BuilderForm::find($form['id'])->integrations);

        $other = $this->signIn('other@example.com');
        $this->api($other)->getJson($url)->assertNotFound();
        $this->api($other)->postJson($url, ['webhook' => null])->assertNotFound();
    }

    public function test_leads_are_passed_to_the_integrations_after_the_response(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $token = $this->signIn();
        $form = $this->funnel($token);
        $this->api($token)->postJson('/builder/forms/'.$form['id'].'/integrations', [
            'mailchimp' => ['key' => self::MC_KEY, 'list' => 'abc123def'],
            'brevo' => ['key' => 'xkeysib-secret', 'list' => '7'],
            'webhook' => ['url' => 'https://hooks.example.com/owlvix'],
        ])->assertOk();

        $this->lead($form['id'], ['email' => 'Ann@Example.com'])->assertStatus(303);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT'
            && $r->url() === 'https://us21.api.mailchimp.com/3.0/lists/abc123def/members/'.md5('ann@example.com')
            && $r['status_if_new'] === 'pending' && $r['merge_fields'] === ['FNAME' => 'Ann Lee', 'PHONE' => '+442012345678']
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('owlvix:'.self::MC_KEY)));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->url() === 'https://api.brevo.com/v3/contacts'
            && $r->hasHeader('api-key', 'xkeysib-secret') && $r['listIds'] === [7] && $r['updateEnabled'] === true
            && (array) $r['attributes'] === ['FIRSTNAME' => 'Ann Lee', 'SMS' => '+442012345678']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://hooks.example.com/owlvix' && $r->hasHeader('X-Owlvix-Event', 'lead')
            && $r['email'] === 'Ann@Example.com' && $r['funnel'] === 'joes-funnel' && $r['step'] === 'optin' && $r['variant'] === 'b');
        Http::assertSentCount(3);

        // A failing tool never fails the visitor
        Http::fake(['*' => fn () => throw new \RuntimeException('down')]);
        $this->lead($form['id'])->assertStatus(303)->assertRedirect(url('/p/joes-funnel/thanks'));
        $this->assertSame(2, BuilderLead::count());
    }

    public function test_published_pages_allow_the_beacon_and_lead_forms_pass_the_check(): void
    {
        $origin = rtrim(url('/'), '/');
        $this->assertStringContainsString("connect-src {$origin}/fs;", BuilderHtml::csp());
        $this->assertStringContainsString("connect-src {$origin}/fs;", BuilderHtml::htaccess());
        $this->assertStringContainsString("default-src 'none'", BuilderHtml::csp());

        $form = str_repeat('a', 16);
        $html = '<html data-fs-site="x" data-fs-step="optin" data-fs-v="b"><body><form method="post" action="'.$origin.'/f/'.$form.'">'
            .'<input name="email"><input type="hidden" name="_next" value="https://sites.owlvix.com/x/thanks"><input type="hidden" name="_step" value="optin"></form></body></html>';
        $this->assertNull(BuilderHtml::problem($html, [$form]));
        // Nothing else loosened
        $this->assertNotNull(BuilderHtml::problem($html, []));
        $this->assertNotNull(BuilderHtml::problem('<html data-fs-x="1" onload="x()"></html>', []));

        // With the sites subdomain, the redirect to the next step is allowed by form-action
        config(['builder.domain' => 'sites.owlvix.test']);
        $this->assertMatchesRegularExpression('#form-action '.preg_quote($origin, '#').' https?://sites\.owlvix\.test;#', BuilderHtml::csp());
    }

    public function test_email_tools_get_a_valid_phone_and_retry_without_it(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $this->api($token)->postJson('/builder/forms/'.$form['id'].'/integrations', ['mailchimp' => ['key' => self::MC_KEY, 'list' => 'abc123def'], 'brevo' => ['key' => 'k', 'list' => '7']])->assertOk();

        // One fake for the whole test: "reject" makes every first try fail with a 400, like a missing PHONE field
        $mode = 'ok';
        $calls = 0;
        Http::fake(['*' => function () use (&$mode, &$calls) {
            return $mode === 'reject' && ++$calls % 2 ? Http::response(['title' => 'Invalid Resource'], 400) : Http::response([], 200);
        }]);
        $only = fn (string $host, array $fields) => Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), $host)
            && ($host === 'mailchimp' ? ($r['merge_fields'] ?? null) : (array) ($r['attributes'] ?? [])) === $fields)->count();

        // A local number isn't sent at all
        $this->lead($form['id'], ['phone' => '07700 900123']);
        $this->assertSame(1, $only('mailchimp', ['FNAME' => 'Ann Lee']));
        $this->assertSame(1, $only('brevo', ['FIRSTNAME' => 'Ann Lee']));

        // A rejected phone (e.g. the audience has no PHONE field) is retried without it
        $mode = 'reject';
        $this->lead($form['id']);
        $this->assertSame(1, $only('mailchimp', ['FNAME' => 'Ann Lee', 'PHONE' => '+442012345678']));
        $this->assertSame(2, $only('mailchimp', ['FNAME' => 'Ann Lee']));
        $this->assertSame(2, $only('brevo', ['FIRSTNAME' => 'Ann Lee']));
    }

    public function test_two_projects_with_the_same_name_get_separate_forms(): void
    {
        $token = $this->signIn();
        $a = $this->api($token)->postJson('/builder/forms', ['site_name' => 'My funnel', 'kind' => 'leads', 'ref' => 'projecta1'])->json('form');
        $b = $this->api($token)->postJson('/builder/forms', ['site_name' => 'My funnel', 'kind' => 'leads', 'ref' => 'projectb2'])->json('form');
        $again = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Renamed', 'kind' => 'leads', 'ref' => 'projecta1'])->json('form');
        $this->assertNotSame($a['id'], $b['id']);
        $this->assertSame($a['id'], $again['id']);
        $this->assertSame('Renamed', $again['site_name']);
    }

    public function test_csv_exports_every_lead(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $id = \App\Models\BuilderForm::find($form['id'])->id;
        DB::table('builder_leads')->insert(array_map(fn ($i) => ['builder_form_id' => $id, 'name' => "L$i", 'email' => "l$i@example.com", 'created_at' => now()], range(1, 1200)));
        $csv = $this->api($token)->get('/builder/leads?form='.$form['id'].'&format=csv')->assertOk()->streamedContent();
        $this->assertSame(1201, substr_count(trim($csv), "\n") + 1); // header + 1,200 leads
    }

    public function test_old_stats_and_leads_of_removed_sites_are_pruned(): void
    {
        $token = $this->signIn();
        $form = $this->funnel($token);
        $site = BuilderSite::where('slug', 'joes-funnel')->sole();
        $this->lead($form['id']);
        $this->travel(91)->days();
        $this->lead($form['id']);
        DB::table('builder_stats')->delete();
        DB::table('builder_stats')->insert([
            ['builder_site_id' => $site->id, 'step' => 'optin', 'variant' => 'a', 'day' => now()->subDays(401)->toDateString(), 'views' => 1, 'leads' => 0],
            ['builder_site_id' => $site->id, 'step' => 'optin', 'variant' => 'a', 'day' => now()->subDays(399)->toDateString(), 'views' => 1, 'leads' => 0],
        ]);

        Artisan::call('model:prune', ['--model' => [BuilderStat::class, BuilderLead::class]]);
        $this->assertSame(1, BuilderStat::count());
        $this->assertSame(2, BuilderLead::count()); // site still live: leads kept

        // A lead not linked to a site (e.g. a downloaded funnel hosted elsewhere) is kept while the form exists
        $this->lead($form['id'], ['_site' => '']);
        $this->assertSame(3, BuilderLead::count());

        // Unpublishing doesn't delete leads straight away — only 90 days after the funnel came down
        $this->api($token)->postJson('/builder/unpublish', ['slug' => 'joes-funnel'])->assertOk();
        Artisan::call('model:prune', ['--model' => [BuilderStat::class, BuilderLead::class]]);
        $this->assertSame(3, BuilderLead::count());
        $this->travel(91)->days();
        Artisan::call('model:prune', ['--model' => [BuilderStat::class, BuilderLead::class]]);
        $this->assertSame(1, BuilderLead::count()); // the unpublished funnel's leads are gone; the unlinked one stays
    }
}
