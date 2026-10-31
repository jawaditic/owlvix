<?php

namespace Tests\Feature;

use App\Models\BuilderAccount;
use App\Models\BuilderApp;
use App\Support\Stripe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Card payments in builder apps (Stripe Connect), cash/card/bank choices and the admin's encrypted keys. */
class AppPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private string $public;

    private string $keys;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->public = storage_path('framework/testing/app-data-'.bin2hex(random_bytes(4)));
        $this->keys = $this->public.'-stripe.enc';
        config(['builder.apps.public_dir' => $this->public, 'builder.apps.firebase' => '', 'app.url' => 'https://owlvix.test',
            'builder.payments.file' => $this->keys, 'builder.payments.enabled' => true]);
        Stripe::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        File::delete($this->keys);
        Stripe::flush();
        parent::tearDown();
    }

    private function token(): string
    {
        return BuilderAccount::create(['email' => 'shop@example.com'])->issueToken();
    }

    private function api(string $token)
    {
        return $this->withHeaders(['X-Builder-Token' => $token]);
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(1024, 1024);
        ob_start();
        imagepng($im);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    private function appData(array $pay = []): array
    {
        return [
            'name' => 'Joe’s Café', 'package' => 'com.joescafe.app', 'url' => '', 'version_name' => '1.0.0',
            'colors' => ['primary' => '#16A34A', 'bar' => '#ffffff', 'icon' => '#16a34a', 'splash' => '#ffffff'],
            'shop' => ['currency' => '€', 'email' => 'orders@example.com', 'pay' => $pay + ['online' => true, 'mode' => 'test', 'currency' => 'eur']],
            'tabs' => [['label' => 'Order', 'icon' => 'cart', 'type' => 'screen', 'blocks' => [['type' => 'products', 'title' => 'Menu', 'items' => [
                ['name' => 'Latte', 'price' => '4.50'], ['name' => 'Cake of the day', 'price' => ''],
            ]]]]],
        ];
    }

    private function saveKeys(): void
    {
        $this->withSession(['is_admin' => true])->post('/admin/payments', [
            'enabled' => 1, 'fee' => 0,
            'test' => ['on' => 1, 'secret' => 'sk_test_abc123secretWXYZ', 'client_id' => 'ca_test123'],
            'live' => ['on' => 0],
        ])->assertRedirect();
        Stripe::flush();
    }

    public function test_admin_keys_are_stored_encrypted_and_switchable(): void
    {
        $this->get('/admin/payments')->assertRedirect(route('admin.login'));
        $this->withSession(['is_admin' => true])->post('/admin/payments', ['test' => ['secret' => 'pk_test_nope']])->assertSessionHasErrors('test.secret');
        $this->saveKeys();

        $raw = File::get($this->keys);
        $this->assertStringNotContainsString('sk_test_abc123', $raw); // encrypted at rest
        $this->assertTrue(Stripe::available('test'));
        $this->assertFalse(Stripe::available('live'));
        $this->withSession(['is_admin' => true])->get('/admin/payments')->assertOk()->assertSee('…WXYZ')->assertDontSee('sk_test_abc123');

        // Saving again without typing the key keeps it; the main switch turns everything off
        $this->withSession(['is_admin' => true])->post('/admin/payments', ['enabled' => 0, 'test' => ['on' => 1], 'live' => ['on' => 0]])->assertRedirect();
        Stripe::flush();
        $this->assertSame('sk_test_abc123secretWXYZ', Stripe::settings()['test']['secret']);
        $this->assertFalse(Stripe::available('test'));
        // …and so does .env
        $this->withSession(['is_admin' => true])->post('/admin/payments', ['enabled' => 1, 'test' => ['on' => 1], 'live' => ['on' => 0]]);
        config(['builder.payments.enabled' => false]);
        Stripe::flush();
        $this->assertFalse(Stripe::available('test'));
    }

    public function test_connect_stripe_take_a_card_payment_and_disconnect(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $id = $this->api($token)->postJson('/builder/apps', ['app' => $this->appData(['cash' => true]), 'icon' => $this->png(), 'foreground' => $this->png()])->assertOk()->json('app.id');
        $this->assertSame(['available' => true, 'connected' => false], $this->api($token)->getJson('/builder/apps')->json('apps.0.stripe.test'));
        $this->assertNull(json_decode(File::get($this->public."/$id/content.json"), true)['shop']['pay']['online']); // not connected yet

        // The owner goes to Stripe and comes back
        $this->api($token)->postJson("/builder/apps/$id/stripe/connect", ['mode' => 'live'])->assertStatus(503); // live is off
        $url = $this->api($token)->postJson("/builder/apps/$id/stripe/connect", ['mode' => 'test'])->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('ca_test123', $q['client_id']);
        Http::fake(['connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => 'acct_1OwnerTest'])]);
        $this->get('/builder/stripe/callback?state=wrong&code=x')->assertRedirect('/mobile-app-builder?stripe=expired');
        $this->get('/builder/stripe/callback?state='.$q['state'].'&code=ac_123')->assertRedirect('/mobile-app-builder?stripe=connected-test');
        Http::assertSent(fn ($r) => $r->url() === 'https://connect.stripe.com/oauth/token' && $r->hasHeader('Authorization', 'Bearer sk_test_abc123secretWXYZ') && $r['code'] === 'ac_123');
        $this->assertSame('acct_1OwnerTest', BuilderApp::find($id)->stripe_test_account);
        $this->assertStringNotContainsString('acct_1OwnerTest', (string) DB::table('builder_apps')->value('stripe_test_account')); // encrypted
        $pay = json_decode(File::get($this->public."/$id/content.json"), true)['shop']['pay'];
        $this->assertTrue($pay['cash']);
        $this->assertSame(['test' => true, 'url' => "https://owlvix.test/api/app-pay/$id"], $pay['online']);

        // A payment: prices from the app, never the phone
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_abcdefghijkl', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abcdefghijkl']),
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['payment_status' => 'paid', 'amount_total' => 900, 'currency' => 'eur', 'metadata' => ['owlvix_app' => $id]])]);
        $this->postJson("/api/app-pay/$id", ['items' => [['name' => 'Cake of the day', 'qty' => 1]]])->assertStatus(422); // price on request
        $this->postJson("/api/app-pay/$id", ['items' => [['name' => 'Free stuff', 'qty' => 1]]])->assertStatus(422);
        $r = $this->postJson("/api/app-pay/$id", ['items' => [['name' => 'Latte', 'qty' => 2, 'price' => 0.01]]])->assertOk()->json();
        $this->assertSame(['ok' => true, 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abcdefghijkl', 'session' => 'cs_test_abcdefghijkl', 'test' => true], $r);
        Http::assertSent(fn ($req) => $req->url() === 'https://api.stripe.com/v1/checkout/sessions' && $req->hasHeader('Stripe-Account', 'acct_1OwnerTest')
            && ($f = $req->data()) && $f['line_items[0][price_data][unit_amount]'] == 450 && $f['line_items[0][quantity]'] == 2 && $f['line_items[0][price_data][currency]'] === 'eur'
            && ! isset($f['payment_intent_data[application_fee_amount]']));
        $this->getJson("/api/app-pay/$id/cs_test_abcdefghijkl")->assertOk()->assertJson(['paid' => true, 'amount' => 900]);
        $this->get("/app-paid/$id?r=ok")->assertOk()->assertSee('Payment received');

        // Disconnecting takes the button away from phones
        Http::fake(['connect.stripe.com/*' => Http::response([])]);
        $this->api($token)->postJson("/builder/apps/$id/stripe/disconnect", ['mode' => 'test'])->assertOk()->assertJsonPath('app.stripe.test.connected', false);
        $this->assertNull(json_decode(File::get($this->public."/$id/content.json"), true)['shop']['pay']['online']);
        $this->postJson("/api/app-pay/$id", ['items' => [['name' => 'Latte', 'qty' => 1]]])->assertStatus(409);
    }

    public function test_bank_transfer_needs_details(): void
    {
        $token = $this->token();
        $this->api($token)->postJson('/builder/apps', ['app' => $this->appData(['bank' => true]), 'icon' => $this->png(), 'foreground' => $this->png()])->assertStatus(422);
        $id = $this->api($token)->postJson('/builder/apps', ['app' => $this->appData(['bank' => true, 'bank_details' => 'IBAN DE00 1234']), 'icon' => $this->png(), 'foreground' => $this->png()])->assertOk()->json('app.id');
        $pay = json_decode(File::get($this->public."/$id/content.json"), true)['shop']['pay'];
        $this->assertSame([true, 'IBAN DE00 1234', null], [$pay['bank'], $pay['bank_details'], $pay['online']]); // no Stripe keys: no card payments
    }
}
