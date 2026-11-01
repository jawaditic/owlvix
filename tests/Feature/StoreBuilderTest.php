<?php

namespace Tests\Feature;

use App\Mail\BuilderOrderPlaced;
use App\Mail\BuilderOrderReceipt;
use App\Mail\BuilderOrderShipped;
use App\Mail\BuilderOrderRefunded;
use App\Models\BuilderAccount;
use App\Models\BuilderOrder;
use App\Models\BuilderStore;
use App\Support\BuilderHtml;
use App\Support\Shop;
use App\Support\Stripe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The online store builder: switching on checkout, orders priced on the server, card payments and the Orders tab. */
class StoreBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $keys;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->keys = storage_path('framework/testing/stripe-'.bin2hex(random_bytes(4)).'.enc');
        config(['app.url' => 'http://localhost', 'builder.payments.file' => $this->keys, 'builder.payments.enabled' => true]);
        Stripe::flush();
    }

    protected function tearDown(): void
    {
        File::delete($this->keys);
        Stripe::flush();
        parent::tearDown();
    }

    private function token(string $email = 'owner@example.com'): string
    {
        $account = BuilderAccount::firstOrCreate(['email' => $email]);
        // customer emails only go out for shops with a live published site
        $account->sites()->firstOrCreate(['slug' => 'shop-'.substr(md5($email), 0, 8)], ['name' => 'Shop', 'pages' => [], 'status' => 'live']);

        return $account->issueToken();
    }

    private function api(string $token)
    {
        return $this->withHeaders(['X-Builder-Token' => $token]);
    }

    private function shop(array $settings = []): array
    {
        return [
            'ref' => 'abc123def456', 'name' => 'Thread & Co.', 'currency' => 'usd',
            'products' => [
                ['id' => 'tee1', 'name' => 'Classic tee', 'price' => '24.50', 'options' => ['S', 'M', 'L'], 'sold' => false],
                ['id' => 'bag1', 'name' => 'Tote bag', 'price' => '10', 'options' => [], 'sold' => false],
                ['id' => 'hat1', 'name' => 'Beanie', 'price' => '22', 'options' => [], 'sold' => true],
                ['id' => 'bad1', 'name' => 'No price', 'price' => 'ask us'],
                ['id' => 'tee1', 'name' => 'Same id again', 'price' => '1'],
            ],
            'settings' => $settings + ['delivery' => true, 'fee' => '4.99', 'freeOver' => '50', 'pickup' => true, 'pickupText' => 'Shop on Main St',
                'cod' => true, 'bank' => true, 'bankText' => 'IBAN TEST 1234', 'card' => false, 'phoneReq' => true, 'whatsapp' => '+1 555 0100'],
        ];
    }

    private function store(?string $token = null, array $settings = []): BuilderStore
    {
        $id = $this->api($token ?? $this->token())->postJson('/builder/stores', $this->shop($settings))->assertOk()->json('store.id');

        return BuilderStore::find($id);
    }

    private function order(BuilderStore $store, array $cart, array $extra = [])
    {
        return $this->post('/shop/'.$store->id, $extra + [
            'name' => 'Ann Buyer', 'email' => 'Ann@Example.com', 'phone' => '+1 555 0199', 'address' => "1 High St\nTown",
            '_delivery' => 'delivery', '_pay' => 'cod', '_cart' => json_encode($cart), '_back' => 'https://localhost/p/thread/shop',
        ]);
    }

    private function saveKeys(): void
    {
        Stripe::save(['enabled' => true, 'fee' => 0, 'test' => ['on' => true, 'secret' => 'sk_test_abc123secretWXYZ', 'client_id' => 'ca_test123'], 'live' => ['on' => false]]);
        Stripe::flush();
    }

    public function test_the_page_and_engine_lists_match_the_server(): void
    {
        $this->get('/online-store-builder')->assertOk()->assertSee('data-mode="store"', false)->assertSee('data-tab="orders"', false);
        $engine = file_get_contents(public_path('js/builder-engine.js'));
        preg_match("/const CURRENCIES = \\[([^\\]]*)\\]/", $engine, $c);
        preg_match("/const ZERO_DEC = \\[([^\\]]*)\\]/", $engine, $z);
        $list = fn (string $s) => array_map(fn ($v) => trim($v, " '"), explode(',', $s));
        $this->assertSame(Stripe::CURRENCIES, $list($c[1]));
        $this->assertSame(Stripe::ZERO_DECIMAL, $list($z[1]));
        $runtime = file_get_contents(public_path('js/builder-runtime.js'));
        $this->assertStringContainsString("['".implode("', '", Stripe::ZERO_DECIMAL)."']", $runtime);
    }

    public function test_switching_on_checkout_keeps_clean_products_and_one_shop_per_project(): void
    {
        $this->postJson('/builder/stores', $this->shop())->assertStatus(401);
        $token = $this->token();
        $r = $this->api($token)->postJson('/builder/stores', $this->shop())->assertOk()->json('store');
        $this->assertSame(BuilderHtml::appOrigin().'/shop/'.$r['id'], $r['action']);
        $this->assertSame(3, $r['products']); // no price and the repeated id dropped; sold out kept
        $store = BuilderStore::find($r['id']);
        $this->assertSame([2450, 1000, 2200], array_column($store->catalog, 'price'));
        $this->assertSame(['S', 'M', 'L'], array_column($store->catalog[0]['vars'], 'name')); // older pages sent plain option names
        $this->assertSame([499, 5000, '15550100'], [$store->settings['fee'], $store->settings['free_over'], $store->settings['whatsapp']]);

        // the same project again: the same shop
        $this->assertSame($r['id'], $this->api($token)->postJson('/builder/stores', $this->shop())->json('store.id'));
        $this->assertSame($r['id'], $this->api($token)->postJson('/builder/stores', ['id' => $r['id']] + $this->shop())->json('store.id'));
        // someone else's shop id
        $this->api($this->token('other@example.com'))->postJson('/builder/stores', ['id' => $r['id']] + $this->shop())->assertStatus(403);
        $this->api($token)->postJson('/builder/stores', ['currency' => 'xyz'] + $this->shop())->assertStatus(422);
        // three shops each
        foreach (['bbb222bbb222', 'ccc333ccc333'] as $ref) {
            $this->api($token)->postJson('/builder/stores', ['ref' => $ref] + $this->shop())->assertOk();
        }
        $this->api($token)->postJson('/builder/stores', ['ref' => 'ddd444ddd444'] + $this->shop())->assertStatus(422);
    }

    public function test_an_order_is_priced_from_the_saved_products(): void
    {
        $store = $this->store();
        // the page says the tee costs 0.01: the server charges 24.50
        $this->order($store, [['id' => 'tee1', 'qty' => 2, 'opt' => 'M', 'price' => 0.01], ['id' => 'bag1', 'qty' => 1]])
            ->assertRedirect()->assertStatus(303);
        $o = BuilderOrder::first();
        $this->assertSame([5900, 0, 5900], [$o->subtotal, $o->delivery_fee, $o->total]); // free delivery over 50
        $this->assertSame([['id' => 'tee1', 'vid' => 'o'.substr(md5('M'), 0, 10), 'vid2' => null, 'name' => 'Classic tee', 'option' => 'M', 'price' => 2450, 'qty' => 2, 'kind' => 'item', 'file' => null],
            ['id' => 'bag1', 'vid' => null, 'vid2' => null, 'name' => 'Tote bag', 'option' => null, 'price' => 1000, 'qty' => 1, 'kind' => 'item', 'file' => null]], $o->items);
        $this->assertSame(['new', 'cod', 1, 'ann@example.com', '#1001'], [$o->status, $o->pay, $o->number, $o->email, $o->label()]);
        Mail::assertSent(BuilderOrderPlaced::class, fn ($m) => $m->hasTo('owner@example.com') && $m->hasReplyTo('ann@example.com'));
        Mail::assertSent(BuilderOrderReceipt::class, fn ($m) => $m->hasTo('ann@example.com') && $m->hasReplyTo('owner@example.com'));
        // bank details stay on the receipt page, not in emails to an address someone typed in
        $this->assertStringNotContainsString('IBAN', (new BuilderOrderReceipt(BuilderOrder::first(), 'owner@example.com'))->render());

        // the receipt: private link, totals, a way back to the shop that empties the cart
        $this->get('/shop/order/'.$o->token)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('#1001')->assertSee('$59')->assertSee('2 × Classic tee')->assertSee('https://localhost/p/thread/shop#ordered', false)
            ->assertSee('https://wa.me/15550100', false);
        $this->get('/shop/order/'.str_repeat('a', 40))->assertNotFound();

        // a small order pays for delivery; pick-up doesn't; bank transfer shows the bank details
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_pay' => 'bank']);
        $o2 = BuilderOrder::where('number', 2)->first();
        $this->assertSame([1000, 499, 1499, 'bank'], [$o2->subtotal, $o2->delivery_fee, $o2->total, $o2->pay]);
        $this->get('/shop/order/'.$o2->token)->assertSee('IBAN TEST 1234')->assertSee('Not paid yet');
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_delivery' => 'pickup', 'address' => '']);
        $this->assertSame([0, null], [BuilderOrder::where('number', 3)->value('delivery_fee'), BuilderOrder::where('number', 3)->value('address')]);
    }

    public function test_bad_orders_are_refused_with_a_reason(): void
    {
        $store = $this->store();
        $cases = [
            [[['id' => 'hat1', 'qty' => 1]], [], 'is sold out'],
            [[['id' => 'gone', 'qty' => 1]], [], 'no longer sold'],
            [[['id' => 'tee1', 'qty' => 1, 'opt' => 'XXL']], [], 'Please choose your option for'],
            [[['id' => 'tee1', 'qty' => 1]], [], 'Please choose your option for'],
            [[['id' => 'bag1', 'qty' => 0]], [], 'between 1 and 99'],
            [[], [], 'cart is empty'],
            [[['id' => 'bag1', 'qty' => 1]], ['address' => ''], 'delivery address'],
            [[['id' => 'bag1', 'qty' => 1]], ['phone' => ''], 'phone number'],
            [[['id' => 'bag1', 'qty' => 1]], ['_pay' => 'card'], 'Card payments aren’t available'],
            [[['id' => 'bag1', 'qty' => 1]], ['_pay' => 'bitcoin'], 'how you’d like to pay'],
        ];
        foreach ($cases as [$cart, $extra, $error]) {
            $this->order($store, $cart, $extra)->assertStatus(422)->assertSee($error)->assertSee('https://localhost/p/thread/shop#cart', false);
        }
        $this->assertSame(0, BuilderOrder::count());
        Mail::assertNothingSent();

        $store->update(['settings' => ['pickup' => false] + $store->settings]);
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_delivery' => 'pickup'])->assertStatus(422)->assertSee('delivery or pick-up');
        // Owlvix can switch a shop's checkout off from the admin
        $this->post('/admin/builder/stores/'.$store->id.'/toggle')->assertRedirect(route('admin.login'));
        $this->withSession(['is_admin' => true])->get('/admin/builder')->assertOk()->assertSee('Thread &amp; Co.', false)->assertSee('Turn checkout off');
        $this->withSession(['is_admin' => true])->post('/admin/builder/stores/'.$store->id.'/toggle')->assertRedirect();
        $this->order($store, [['id' => 'bag1', 'qty' => 1]])->assertNotFound()->assertSee('isn’t taking orders');
    }

    public function test_card_orders_go_to_stripe_and_count_once_paid(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->store($token, ['card' => true]);
        $this->assertFalse($this->api($token)->getJson('/builder/stores/'.$store->id)->json('store.card_ready')); // not connected yet

        // connect the owner's Stripe account (the app builder's callback, back to the store builder)
        $url = $this->api($token)->postJson('/builder/stores/'.$store->id.'/stripe/connect', ['mode' => 'test'])->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        Http::fake(['connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => 'acct_1ShopOwner'])]);
        $this->get('/builder/stripe/callback?state='.$q['state'].'&code=ac_1')->assertRedirect('/online-store-builder?stripe=connected-test');
        $this->assertSame('acct_1ShopOwner', $store->fresh()->stripe_test_account);
        $this->assertStringNotContainsString('acct_1ShopOwner', (string) DB::table('builder_stores')->value('stripe_test_account'));
        $this->assertTrue($this->api($token)->getJson('/builder/stores/'.$store->id)->json('store.card_ready'));

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_order1abcdef', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_order1abcdef'])]);
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 2]], ['_pay' => 'card'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_order1abcdef');
        $o = BuilderOrder::first();
        $this->assertSame(['unpaid', 'test', 'cs_test_order1abcdef', 2499], [$o->status, $o->stripe_mode, $o->stripe_session, $o->total]);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions' && $r->hasHeader('Stripe-Account', 'acct_1ShopOwner')
            && ($f = $r->data()) && $f['line_items[0][price_data][unit_amount]'] == 1000 && $f['line_items[0][quantity]'] == 2
            && $f['line_items[1][price_data][product_data][name]'] === 'Delivery' && $f['line_items[1][price_data][unit_amount]'] == 499
            && $f['metadata[owlvix_order]'] === (string) $o->id && $f['customer_email'] === 'ann@example.com'
            && str_ends_with($f['success_url'], '/shop/order/'.$o->token.'?paid={CHECKOUT_SESSION_ID}'));
        Mail::assertNothingSent(); // not paid yet
        $this->get('/shop/order/'.$o->token.'?r=cancelled')->assertSee('Finish your payment')->assertSee('Pay $24.99 now');

        // a session for another amount doesn't count; the real one does, once
        $amount = 1;
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => function () use (&$amount, $o, $store) {
            return Http::response(['payment_status' => 'paid', 'amount_total' => $amount, 'currency' => 'usd', 'metadata' => ['owlvix_order' => (string) $o->id, 'owlvix_store' => $store->id]]);
        }]);
        $this->get('/shop/order/'.$o->token.'?paid=cs_test_order1abcdef')->assertSee('Finish your payment');
        $this->assertNull($o->fresh()->paid_at);
        $amount = 2499;
        $this->get('/shop/order/'.$o->token.'?paid=cs_test_order1abcdef')->assertOk()->assertSee('Thank you, Ann Buyer')->assertSee('Paid');
        $this->get('/shop/order/'.$o->token.'?paid=cs_test_order1abcdef')->assertOk();
        $this->assertSame('new', $o->fresh()->status);
        Mail::assertSent(BuilderOrderPlaced::class, 1);
        // the owner's "Check payment" agrees
        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/1/check')->assertOk()->assertJson(['paid' => true]);
    }

    public function test_the_owner_sees_and_updates_orders(): void
    {
        $token = $this->token();
        $store = $this->store($token);
        $this->order($store, [['id' => 'bag1', 'qty' => 3]], ['_pay' => 'bank', 'note' => 'Gift wrap please']);
        $this->api($this->token('other@example.com'))->getJson('/builder/stores/'.$store->id.'/orders')->assertNotFound();

        $r = $this->api($token)->getJson('/builder/stores/'.$store->id.'/orders')->assertOk()->json();
        $this->assertSame([1, 1, '#1001', '$30', '$4.99', '$34.99', 'Gift wrap please'], [$r['total'], $r['counts']['new'], $r['orders'][0]['label'], $r['orders'][0]['subtotal'], $r['orders'][0]['delivery_fee'], $r['orders'][0]['total'], $r['orders'][0]['note']]);
        $this->assertArrayNotHasKey('token', $r['orders'][0]);

        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/1', ['status' => 'shipped', 'paid' => true])->assertOk()->assertJson(['order' => ['status' => 'shipped', 'paid' => true]]);
        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/1', ['status' => 'unpaid'])->assertStatus(422);
        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/9', ['status' => 'done'])->assertNotFound();
        $this->assertSame(1, count($this->api($token)->getJson('/builder/stores/'.$store->id.'/orders?status=shipped')->json('orders')));

        $csv = $this->api($token)->get('/builder/stores/'.$store->id.'/orders?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('#1001', $csv);
        $this->assertStringContainsString('3 × Tote bag', $csv);
        $this->assertStringContainsString('Bank transfer', $csv);

        // a card order that was never paid can only be cancelled
        $o = BuilderOrder::first();
        $o->update(['status' => 'unpaid', 'pay' => 'card']);
        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/1', ['status' => 'shipped'])->assertStatus(422);
        $this->api($token)->postJson('/builder/stores/'.$store->id.'/orders/1', ['status' => 'cancelled'])->assertOk();
    }

    public function test_published_pages_may_only_check_out_to_their_own_shop(): void
    {
        $token = $this->token();
        $store = $this->store($token);
        $other = $this->store($this->token('other@example.com'));
        $page = fn (BuilderStore $s) => '<!doctype html><html><body><article data-product="bag1"><select name="_opt" data-opt><option>S</option></select><button type="button" data-add>Add</button></article>'
            .'<form method="post" action="'.$s->action().'"><input name="name"><input name="email"><input name="phone"><textarea name="address"></textarea><textarea name="note"></textarea>'
            .'<input type="radio" name="_delivery" value="delivery"><input type="radio" name="_pay" value="card"><input type="hidden" name="_cart"><input name="_hp"><input type="hidden" name="_back"></form></body></html>';
        $publish = fn (string $html) => $this->api($token)->postJson('/builder/publish', ['slug' => 'thread-shop', 'name' => 'Thread', 'pages' => [['slug' => 'index', 'title' => 'Home', 'html' => $html]]]);
        $publish($page($other))->assertStatus(422)->assertJsonPath('error', '“Home”: This page uses a shop checkout from another account. Please switch checkout on again.');
        $publish($page($store))->assertOk();
        $this->assertNull(BuilderHtml::problem($page($store), [], [$store->id]));
        $this->assertNotNull(BuilderHtml::problem(str_replace('name="note"', 'name="card_number"', $page($store)), [], [$store->id]));
        $this->assertStringContainsString('https://checkout.stripe.com', BuilderHtml::csp());
    }

    /** A shop with sized products (one size costs more), for the newer tests. */
    private function sizedStore(string $token, array $settings = []): BuilderStore
    {
        $id = $this->api($token)->postJson('/builder/stores', ['ref' => 'sized123sized', 'name' => 'Golden Crust', 'currency' => 'usd', 'products' => [
            ['id' => 'cake1', 'name' => 'Celebration cake', 'price' => '35', 'opt' => 'Size', 'vars' => [['id' => 'v6', 'name' => '6 inch', 'price' => ''], ['id' => 'v8', 'name' => '8 inch', 'price' => '48']]],
            ['id' => 'bun1', 'name' => 'Cinnamon buns', 'price' => '14'],
        ], 'settings' => $settings + ['delivery' => true, 'fee' => '5', 'freeOver' => '100', 'cod' => true, 'phoneReq' => false]])->assertOk()->json('store.id');

        return BuilderStore::find($id);
    }

    public function test_sizes_have_their_own_prices_and_stock_runs_out(): void
    {
        $token = $this->token();
        $store = $this->sizedStore($token);
        // the 8 inch costs 48, the 6 inch the cake's 35
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 1], ['id' => 'cake1', 'vid' => 'v6', 'qty' => 1]]);
        $o = BuilderOrder::first();
        $this->assertSame([['8 inch', 4800], ['6 inch', 3500]], array_map(fn ($l) => [$l['option'], $l['price']], $o->items));
        $this->assertSame([8300, 500, 8800], [$o->subtotal, $o->delivery_fee, $o->total]);
        $this->order($store, [['id' => 'cake1', 'qty' => 1]])->assertStatus(422)->assertSee('Please choose your size');

        // count stock: two 8 inch cakes and three buns left; publishing again doesn't undo what orders took
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['cake1:v8' => 2, 'bun1' => 3, 'bad key!' => 5]])->assertOk()->assertJsonPath('stock.cake1:v8', 2);
        $this->get("/shop/{$store->id}/stock")->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')->assertJsonPath('stock.bun1', 3);
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 3]])->assertStatus(409)->assertSee('Only 2 of “Celebration cake (8 inch)” are left');
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 2], ['id' => 'bun1', 'qty' => 1]])->assertRedirect();
        $this->sizedStore($token); // publish again
        $this->assertSame(['cake1:v8' => 0, 'bun1' => 2], $store->fresh()->stock);
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 1]])->assertStatus(409)->assertSee('has just sold out');

        // cancelling gives the stock back, once; a cancelled order stays cancelled
        $n = BuilderOrder::latest('id')->value('number');
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/$n", ['status' => 'cancelled'])->assertOk();
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/$n", ['status' => 'cancelled'])->assertOk();
        $this->assertSame(['cake1:v8' => 2, 'bun1' => 3], $store->fresh()->stock);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/$n", ['status' => 'new'])->assertStatus(422);
        // stop counting
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['bun1' => null]])->assertOk();
        $this->assertSame(['cake1:v8' => 2], $store->fresh()->stock);
    }

    public function test_unpaid_card_orders_give_their_stock_back_after_a_while(): void
    {
        $token = $this->token();
        $store = $this->sizedStore($token);
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['bun1' => 1]]);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]]);
        $o = BuilderOrder::first();
        $o->forceFill(['status' => 'unpaid', 'pay' => 'card'])->save();
        $this->assertSame(0, $store->fresh()->stock['bun1']);
        $this->travel(70)->minutes();
        $this->api($token)->getJson("/builder/stores/{$store->id}/orders")->assertOk();
        $this->assertSame(['cancelled', 1], [$o->fresh()->status, $store->fresh()->stock['bun1']]);
        $this->post('/shop/order/'.$o->token.'/pay')->assertRedirect(route('shop.order', ['token' => $o->token, 'r' => 'expired']));
        $this->get('/shop/order/'.$o->token.'?r=expired')->assertSee('wasn’t paid in time');
    }

    public function test_discount_codes(): void
    {
        $token = $this->token();
        $store = $this->sizedStore($token);
        $codes = "/builder/stores/{$store->id}/discounts";
        $this->api($token)->postJson($codes, ['code' => 'save 10', 'kind' => 'percent', 'value' => 10])->assertStatus(422); // no spaces
        $this->api($token)->postJson($codes, ['code' => 'big', 'kind' => 'percent', 'value' => 150])->assertStatus(422);
        $id = $this->api($token)->postJson($codes, ['code' => 'save10', 'kind' => 'percent', 'value' => 10, 'min' => '20', 'max_uses' => 1])->assertOk()->json('discount.id');
        $this->api($token)->postJson($codes, ['code' => 'SAVE10', 'kind' => 'amount', 'value' => 5])->assertStatus(422)->assertJsonPath('error', 'You already have the code SAVE10.');
        $this->api($token)->postJson($codes, ['code' => 'FREESHIP', 'kind' => 'shipping'])->assertOk();
        $this->api($this->token('other@example.com'))->getJson($codes)->assertNotFound();
        $this->assertSame(['FREESHIP', 'SAVE10'], array_column($this->api($token)->getJson($codes)->json('discounts'), 'code'));

        // the cart checks a code (any case); the minimum order counts
        $this->getJson("/shop/{$store->id}/code?c=save10&sub=14")->assertHeader('Access-Control-Allow-Origin', '*')->assertJson(['ok' => false]);
        $this->getJson("/shop/{$store->id}/code?c=save10&sub=48")->assertJson(['ok' => true, 'code' => 'SAVE10', 'kind' => 'percent', 'value' => 10, 'label' => '10% off']);
        $this->getJson("/shop/{$store->id}/code?c=nope&sub=48")->assertJson(['ok' => false, 'error' => 'That code isn’t valid.']);

        // 10% off 48 = 4.80; delivery 5 → 48.20
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 1]], ['_code' => ' save10 '])->assertRedirect();
        $o = BuilderOrder::first();
        $this->assertSame([4800, 480, 500, 4820, 'SAVE10'], [$o->subtotal, $o->discount, $o->delivery_fee, $o->total, $o->discount_code]);
        $this->get('/shop/order/'.$o->token)->assertSee('Discount (SAVE10)')->assertSee('−$4.80');
        // used up (one use); cancelling gives the use back
        $this->order($store, [['id' => 'cake1', 'vid' => 'v8', 'qty' => 1]], ['_code' => 'SAVE10'])->assertStatus(422)->assertSee('has been used up');
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['status' => 'cancelled']);
        $this->getJson("/shop/{$store->id}/code?c=SAVE10&sub=48")->assertJson(['ok' => true]);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['_code' => 'FREESHIP'])->assertRedirect();
        $this->assertSame([0, 1400], [BuilderOrder::latest('id')->first()->delivery_fee, BuilderOrder::latest('id')->first()->total]);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['_code' => 'WRONG'])->assertStatus(422)->assertSee('isn’t valid');
        $this->api($token)->postJson("$codes/$id/delete")->assertOk();
    }

    public function test_shipping_by_country_and_tax(): void
    {
        $token = $this->token();
        $store = $this->sizedStore($token, ['zones' => [
            ['name' => 'UK', 'countries' => ['gb'], 'fee' => '3', 'freeOver' => '40'],
            ['name' => 'Europe', 'countries' => ['FR', 'DE', 'XX1'], 'fee' => '9'],
        ], 'taxRate' => '20', 'taxIncl' => true, 'taxLabel' => 'VAT']);
        $this->assertSame(['GB'], $store->settings['zones'][0]['countries']);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]])->assertStatus(422)->assertSee('choose your country');
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['country' => 'US'])->assertStatus(422)->assertSee('doesn’t deliver to United States');
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['country' => 'fr'])->assertRedirect();
        $o = BuilderOrder::first();
        // VAT included: 20% of (14) inside the price = 2.33; delivery not taxed
        $this->assertSame([1400, 900, 233, true, 2300, 'FR'], [$o->subtotal, $o->delivery_fee, $o->tax, $o->tax_included, $o->total, $o->country]);
        $this->get('/shop/order/'.$o->token)->assertSee('France')->assertSee('includes $2.33 VAT');

        // tax added on top, also on delivery; free UK delivery over 40
        $store->update(['settings' => Shop::settings(['zones' => [['countries' => ['GB'], 'fee' => '3', 'freeOver' => '40'], ['countries' => [], 'fee' => '15']], 'taxRate' => '10', 'taxIncl' => false, 'taxShip' => true, 'cod' => true], 'usd')]);
        $this->order($store, [['id' => 'bun1', 'qty' => 3]], ['country' => 'GB'])->assertRedirect();
        $this->assertSame([4200, 0, 420, 4620], [BuilderOrder::latest('id')->first()->subtotal, BuilderOrder::latest('id')->first()->delivery_fee, BuilderOrder::latest('id')->first()->tax, BuilderOrder::latest('id')->first()->total]);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['country' => 'JP'])->assertRedirect(); // "everywhere else"
        $this->assertSame([1500, 290, 3190], [BuilderOrder::latest('id')->first()->delivery_fee, BuilderOrder::latest('id')->first()->tax, BuilderOrder::latest('id')->first()->total]);
        // pick-up needs no country
        $store->update(['settings' => ['pickup' => true] + $store->settings]);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]], ['_delivery' => 'pickup', 'address' => ''])->assertRedirect();
    }

    public function test_marking_an_order_sent_emails_the_tracking_number(): void
    {
        $token = $this->token();
        $store = $this->sizedStore($token);
        $this->order($store, [['id' => 'bun1', 'qty' => 1]]);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['status' => 'shipped', 'tracking' => 'https://track.example.com/AB123'])
            ->assertOk()->assertJsonPath('order.tracking', 'https://track.example.com/AB123');
        Mail::assertSent(BuilderOrderShipped::class, fn ($m) => $m->hasTo('ann@example.com') && $m->hasReplyTo('owner@example.com'));
        // only once
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['status' => 'shipped']);
        Mail::assertSent(BuilderOrderShipped::class, 1);
        $o = BuilderOrder::first();
        $this->get('/shop/order/'.$o->token)->assertSee('Your order is on its way')->assertSee('https://track.example.com/AB123', false);
        $csv = $this->api($token)->get("/builder/stores/{$store->id}/orders?format=csv")->streamedContent();
        $this->assertStringContainsString('https://track.example.com/AB123', $csv);
    }

    public function test_no_customer_emails_without_a_live_site(): void
    {
        $account = BuilderAccount::create(['email' => 'new@example.com']);
        $store = $this->store($account->issueToken());
        $this->order($store, [['id' => 'bag1', 'qty' => 1]])->assertRedirect();
        Mail::assertSent(BuilderOrderPlaced::class);
        Mail::assertNotSent(BuilderOrderReceipt::class);
    }

    public function test_a_card_order_paid_after_it_was_cancelled_takes_its_stock_again(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->store($token, ['card' => true]);
        $store->forceFill(['stripe_test_account' => 'acct_1Shop'])->save();
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['bag1' => 1]]);
        $paid = false;
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_hold1abcdefgh', 'url' => 'https://checkout.stripe.com/x']),
            'api.stripe.com/v1/checkout/sessions/*/expire' => Http::response([]),
            'api.stripe.com/v1/checkout/sessions/*' => function () use (&$paid) {
                $o = BuilderOrder::first();

                return Http::response(['payment_status' => $paid ? 'paid' : 'unpaid', 'amount_total' => $o->total, 'currency' => 'usd', 'metadata' => ['owlvix_order' => (string) $o->id, 'owlvix_store' => $o->builder_store_id]]);
            }]);
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 1]], ['_pay' => 'card'])->assertRedirect('https://checkout.stripe.com/x');
        $o = BuilderOrder::first();
        $this->assertNotNull($o->hold_until);
        // the owner cancels it while Stripe's page is open: the page is closed and the stock goes back
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['status' => 'cancelled'])->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cs_test_hold1abcdefgh/expire'));
        $this->assertSame(1, $store->fresh()->stock['bag1']);
        // …but it was paid just before: it comes back and takes its stock again
        $paid = true;
        $this->get('/shop/order/'.$o->token.'?paid=cs_test_hold1abcdefgh')->assertOk();
        $this->assertSame(['new', true, 0], [$o->fresh()->status, $o->fresh()->stock_taken, $store->fresh()->stock['bag1']]);
    }

    public function test_an_expired_card_order_is_checked_with_stripe_before_it_is_cancelled(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->store($token, ['card' => true]);
        $store->forceFill(['stripe_test_account' => 'acct_1Shop'])->save();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_late1abcdefgh', 'url' => 'https://checkout.stripe.com/x']),
            'api.stripe.com/v1/checkout/sessions/*' => fn () => Http::response(['payment_status' => 'paid', 'amount_total' => BuilderOrder::first()->total, 'currency' => 'usd',
                'metadata' => ['owlvix_order' => (string) BuilderOrder::first()->id, 'owlvix_store' => $store->id]])]);
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 1]], ['_pay' => 'card']);
        $this->travel(70)->minutes();
        // the customer paid and closed the page: the owner's list finds the payment instead of cancelling it
        $this->api($token)->getJson("/builder/stores/{$store->id}/orders")->assertOk()->assertJsonPath('orders.0.status', 'new')->assertJsonPath('orders.0.paid', true);
    }

    public function test_a_card_order_stripe_refuses_gives_its_stock_back_at_once(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->store($token, ['card' => true]);
        $store->forceFill(['stripe_test_account' => 'acct_1Shop'])->save();
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['bag1' => 1]]);
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Amount too small']], 400)]);
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 1]], ['_pay' => 'card'])->assertStatus(502)->assertSee('Card payment couldn’t be started');
        $this->assertSame(['cancelled', 1], [BuilderOrder::first()->status, $store->fresh()->stock['bag1']]);
    }

    public function test_changing_currency_switches_off_codes_with_amounts(): void
    {
        $token = $this->token();
        $store = $this->store($token);
        $codes = "/builder/stores/{$store->id}/discounts";
        $this->api($token)->postJson($codes, ['code' => 'FIVE', 'kind' => 'amount', 'value' => 5])->assertOk();
        $this->api($token)->postJson($codes, ['code' => 'TEN', 'kind' => 'percent', 'value' => 10])->assertOk();
        $this->api($token)->postJson('/builder/stores', ['id' => $store->id, 'currency' => 'jpy'] + $this->shop())->assertOk();
        $this->assertSame(['FIVE' => false, 'TEN' => true], $store->discounts()->pluck('active', 'code')->all());
    }

    public function test_wrong_codes_at_checkout_count_towards_the_limit(): void
    {
        $store = $this->store();
        for ($i = 0; $i < 20; $i++) {
            $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_code' => 'GUESS'.$i])->assertStatus(422);
        }
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_code' => 'GUESS99'])->assertStatus(429);
        $this->getJson("/shop/{$store->id}/code?c=ANY&sub=10")->assertStatus(429);
    }

    public function test_the_calling_code_goes_in_front_of_the_phone_number(): void
    {
        $store = $this->store();
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_dial' => '+44', 'phone' => '07911 123456']);
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_dial' => '+44', 'phone' => '+1 555 0100']); // typed with its own code
        $this->order($store, [['id' => 'bag1', 'qty' => 1]], ['_dial' => '44; DROP', 'phone' => '555'])->assertStatus(422);
        $this->assertSame(['+44 7911 123456', '+1 555 0100'], BuilderOrder::orderBy('id')->pluck('phone')->all());
    }

    public function test_refunds_through_stripe_and_recorded_ones(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->store($token, ['card' => true]);
        $store->forceFill(['stripe_test_account' => 'acct_1Shop'])->save();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_refund1abcdef', 'url' => 'https://checkout.stripe.com/x']),
            'api.stripe.com/v1/checkout/sessions/*' => fn () => Http::response(['payment_status' => 'paid', 'amount_total' => BuilderOrder::first()->total, 'currency' => 'usd', 'payment_intent' => 'pi_123',
                'metadata' => ['owlvix_order' => (string) BuilderOrder::first()->id, 'owlvix_store' => $store->id]]),
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1'])]);
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 2]], ['_pay' => 'card']); // 20 + 4.99 delivery
        $url = "/builder/stores/{$store->id}/orders/1/refund";
        $this->api($token)->postJson($url)->assertStatus(422); // not paid yet
        $this->get('/shop/order/'.BuilderOrder::first()->token.'?paid=cs_test_refund1abcdef');
        $this->assertSame('24.99', $this->api($token)->getJson("/builder/stores/{$store->id}/orders")->json('orders.0.refundable'));

        $this->api($token)->postJson($url, ['amount' => '30'])->assertStatus(422)->assertJsonPath('error', 'You can refund up to $24.99.');
        $this->api($token)->postJson($url, ['amount' => '10'])->assertOk()->assertJsonPath('order.refunded', '$10')->assertJsonPath('order.refundable', '14.99');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/refunds' && $r['payment_intent'] === 'pi_123' && $r['amount'] == 1000
            && $r->hasHeader('Stripe-Account', 'acct_1Shop') && $r['refund_application_fee'] === 'true');
        Mail::assertSent(BuilderOrderRefunded::class, fn ($m) => $m->hasTo('ann@example.com') && $m->amount === 1000);
        $this->api($token)->postJson($url)->assertOk()->assertJsonPath('order.refunded', '$24.99')->assertJsonPath('order.refundable', null); // the rest
        $this->api($token)->postJson($url)->assertStatus(422); // nothing left
        $this->get('/shop/order/'.BuilderOrder::first()->token)->assertSee('Refunded')->assertSee('−$24.99');
        $this->api($this->token('other@example.com'))->postJson($url)->assertNotFound();

        // a cash order: paid by hand, then a refund is recorded (no Stripe)
        $this->order($store->fresh(), [['id' => 'bag1', 'qty' => 1]], ['_pay' => 'cod']);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/2/refund")->assertStatus(422)->assertJsonPath('error', 'Mark the order as paid first — then you can record a refund.');
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/2", ['paid' => true]);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/2/refund", ['amount' => '5'])->assertOk()->assertJsonPath('order.refunded', '$5');
        $this->assertSame(2, collect(Http::recorded())->filter(fn ($p) => $p[0]->url() === 'https://api.stripe.com/v1/refunds')->count());
    }

    public function test_sales_numbers(): void
    {
        $token = $this->token();
        $store = $this->store($token);
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['bag1' => 9]]);
        $this->order($store, [['id' => 'bag1', 'qty' => 3]]);                                   // 30 + 4.99
        $this->order($store, [['id' => 'tee1', 'qty' => 2, 'opt' => 'M']]);                    // 49 + 4.99 (free only over 50)
        $this->order($store, [['id' => 'bag1', 'qty' => 1]]);                                   // cancelled below
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/3", ['status' => 'cancelled']);
        $this->travel(-40)->days();
        $this->order($store, [['id' => 'bag1', 'qty' => 1]]);                                   // 14.99, in the 30 days before
        $this->travelBack();

        $r = $this->api($token)->getJson("/builder/stores/{$store->id}/sales?days=30")->assertOk()->json();
        $this->assertSame(['$88.98', 2, '$44.49', 30], [$r['takings'], $r['orders'], $r['average'], count($r['per_day'])]);
        $this->assertSame(88.98, end($r['per_day'])['value']);
        $this->assertSame(['Tote bag', 3, '$30'], [$r['best'][0]['name'], $r['best'][0]['qty'], $r['best'][0]['sales']]);
        $this->assertSame([['name' => 'Tote bag', 'left' => 5]], $r['low']); // 9 - 3 - 1 (the 40-day-old one), the cancelled one gave its 1 back
        $this->assertSame(494, $r['change']); // 88.98 against 14.99 in the 30 days before
        $this->api($this->token('other@example.com'))->getJson("/builder/stores/{$store->id}/sales")->assertNotFound();
    }

    /** A shop with a size × colour tee, a download and a gift card. */
    private function bigStore(string $token, array $settings = [], ?string $file = null): BuilderStore
    {
        $products = [
            ['id' => 'tee1', 'name' => 'Tee', 'price' => '20', 'opt' => 'Size', 'vars' => [['id' => 's1', 'name' => 'S', 'price' => ''], ['id' => 'm1', 'name' => 'M', 'price' => '22']],
                'opt2' => 'Colour', 'vars2' => [['id' => 'red', 'name' => 'Red'], ['id' => 'blu', 'name' => 'Blue']]],
            ['id' => 'ebook', 'name' => 'Recipe e-book', 'price' => '9', 'kind' => 'digital', 'file' => $file],
            ['id' => 'gift25', 'name' => 'Gift card $25', 'price' => '25', 'kind' => 'gift', 'vars' => [['id' => 'x', 'name' => 'ignored']]],
        ];
        $id = $this->api($token)->postJson('/builder/stores', ['ref' => 'big123big123', 'name' => 'Big Shop', 'currency' => 'usd', 'products' => $products,
            'settings' => $settings + ['delivery' => true, 'fee' => '5', 'cod' => true, 'bank' => true, 'phoneReq' => false]])->assertOk()->json('store.id');

        return BuilderStore::find($id);
    }

    public function test_size_and_colour_together(): void
    {
        $token = $this->token();
        $store = $this->bigStore($token);
        $this->assertSame(['Colour', 'gift', []], [$store->catalog[0]['opt2'], $store->catalog[2]['kind'], $store->catalog[2]['vars']]);
        $this->order($store, [['id' => 'tee1', 'vid' => 'm1', 'qty' => 1]])->assertStatus(422)->assertSee('Please choose your colour');
        $this->api($token)->postJson("/builder/stores/{$store->id}/stock", ['stock' => ['tee1:m1_red' => 1]])->assertOk();
        $this->order($store, [['id' => 'tee1', 'vid' => 'm1', 'vid2' => 'red', 'qty' => 1]])->assertRedirect();
        $o = BuilderOrder::first();
        $this->assertSame(['M / Red', 2200, 'm1', 'red'], [$o->items[0]['option'], $o->items[0]['price'], $o->items[0]['vid'], $o->items[0]['vid2']]);
        $this->assertSame(0, $store->fresh()->stock['tee1:m1_red']);
        $this->order($store, [['id' => 'tee1', 'vid' => 'm1', 'vid2' => 'red', 'qty' => 1]])->assertStatus(409)->assertSee('Tee (M / Red)” has just sold out');
        $this->order($store, [['id' => 'tee1', 'vid' => 'm1', 'vid2' => 'blu', 'qty' => 1]])->assertRedirect(); // not counted: unlimited
    }

    public function test_digital_downloads(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $token = $this->token();
        $store = $this->bigStore($token);
        $this->api($token)->post("/builder/stores/{$store->id}/files", ['file' => \Illuminate\Http\UploadedFile::fake()->create('evil.php', 10)])->assertStatus(422);
        $this->api($token)->post("/builder/stores/{$store->id}/files", ['file' => \Illuminate\Http\UploadedFile::fake()->create('big.pdf', 26 * 1024)])->assertStatus(422);
        $file = $this->api($token)->post("/builder/stores/{$store->id}/files", ['file' => \Illuminate\Http\UploadedFile::fake()->create('Recipes 2026.pdf', 120, 'application/pdf')])->assertOk()->json('file');
        $store = $this->bigStore($token, [], $file['id']);
        $this->assertSame($file['id'], $store->catalog[1]['file']);
        // another shop can't sell this file
        $other = $this->bigStore($this->token('other@example.com'), [], $file['id']);
        $this->assertNull($other->catalog[1]['file']);

        // only a download: no address, no delivery fee
        $this->order($store, [['id' => 'ebook', 'qty' => 1]], ['address' => '', '_delivery' => 'nonsense', '_pay' => 'bank'])->assertRedirect();
        $o = BuilderOrder::first();
        $this->assertSame(['digital', 0, 900, null], [$o->delivery, $o->delivery_fee, $o->total, $o->address]);
        $this->get("/shop/order/{$o->token}/file/ebook")->assertNotFound(); // not paid yet
        $this->get('/shop/order/'.$o->token)->assertSee('Ready here once your payment is received');
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['paid' => true])->assertOk();
        Mail::assertSent(\App\Mail\BuilderOrderPaid::class, fn ($m) => $m->hasTo('ann@example.com'));
        $this->get('/shop/order/'.$o->token)->assertSee("/shop/order/{$o->token}/file/ebook", false);
        $this->get("/shop/order/{$o->token}/file/ebook")->assertOk()->assertDownload('Recipes 2026.pdf');
        for ($i = 1; $i < \App\Models\BuilderShopFile::MAX_DOWNLOADS; $i++) {
            $this->get("/shop/order/{$o->token}/file/ebook")->assertOk();
        }
        $this->get("/shop/order/{$o->token}/file/ebook")->assertRedirect(route('shop.order', ['token' => $o->token, 'r' => 'downloads']));
        // with something to post, delivery is charged as usual
        $this->order($store, [['id' => 'ebook', 'qty' => 1], ['id' => 'tee1', 'vid' => 's1', 'vid2' => 'red', 'qty' => 1]]);
        $this->assertSame(['delivery', 500], [BuilderOrder::find(2)->delivery, BuilderOrder::find(2)->delivery_fee]);
    }

    public function test_gift_cards(): void
    {
        $token = $this->token();
        $store = $this->bigStore($token);
        $this->order($store, [['id' => 'gift25', 'qty' => 2]], ['_pay' => 'bank', 'address' => '']);
        $this->assertSame(0, $store->giftCards()->count()); // made once paid
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['paid' => true]);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['paid' => true]); // once only
        $cards = $store->giftCards()->get();
        $this->assertSame([2, 2500, 2500], [$cards->count(), $cards[0]->initial, $cards[0]->balance]);
        $this->assertMatchesRegularExpression('/^GIFT-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $cards[0]->code);
        Mail::assertSent(\App\Mail\BuilderOrderPaid::class, fn ($m) => str_contains($m->render(), $cards[0]->code));
        $this->assertSame(2, count($this->api($token)->getJson("/builder/stores/{$store->id}/gift-cards")->json('cards')));

        // the cart checks it (any case, no dashes needed)
        $this->getJson("/shop/{$store->id}/code?c=".strtolower(str_replace('-', '', $cards[0]->code)).'&sub=20')->assertJson(['ok' => true, 'kind' => 'gift', 'value' => 25]);
        // it pays part of an order (after delivery), and gives it back if the order is cancelled
        $this->order($store, [['id' => 'tee1', 'vid' => 's1', 'vid2' => 'red', 'qty' => 1]], ['_code' => $cards[0]->code]);
        $o = BuilderOrder::find(2);
        $this->assertSame([2500, 0, $cards[0]->code], [$o->gift_amount, $o->total, $o->gift_code]); // 20 + 5 delivery, all covered
        $this->assertNotNull($o->paid_at); // nothing left to pay
        $this->assertSame(0, $cards[0]->fresh()->balance);
        $this->getJson("/shop/{$store->id}/code?c={$cards[0]->code}")->assertJson(['ok' => false]);
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/2", ['status' => 'cancelled']);
        $this->assertSame(2500, $cards[0]->fresh()->balance);
        // a smaller order leaves money on the card; a gift card can't buy gift cards
        $this->order($store, [['id' => 'ebook', 'qty' => 1]], ['_code' => $cards[0]->code]);
        $this->assertSame([900, 0, 1600], [BuilderOrder::find(3)->gift_amount, BuilderOrder::find(3)->total, $cards[0]->fresh()->balance]);
        $this->order($store, [['id' => 'gift25', 'qty' => 1]], ['_code' => $cards[1]->code])->assertStatus(422)->assertSee('can’t be used to buy gift cards');
    }

    public function test_reviews_from_buyers(): void
    {
        $token = $this->token();
        $store = $this->bigStore($token);
        $this->order($store, [['id' => 'tee1', 'vid' => 's1', 'vid2' => 'red', 'qty' => 1]], ['name' => 'Ann Buyer']);
        $o = BuilderOrder::first();
        $this->post("/shop/order/{$o->token}/review", ['product' => 'tee1', 'rating' => 5])->assertRedirect(); // not sent yet: ignored
        $this->assertSame(0, $store->reviews()->count());
        $this->get('/shop/order/'.$o->token)->assertDontSee('How did you like it?');
        $this->api($token)->postJson("/builder/stores/{$store->id}/orders/1", ['status' => 'shipped']);
        $this->get('/shop/order/'.$o->token)->assertSee('How did you like it?');
        $this->post("/shop/order/{$o->token}/review", ['product' => 'ebook', 'rating' => 5])->assertRedirect(route('shop.order', ['token' => $o->token, 'r' => 'review-error']).'#review'); // not in the order
        $this->post("/shop/order/{$o->token}/review", ['product' => 'tee1', 'rating' => 4, 'text' => 'Soft and fits well.'])->assertRedirect();
        $this->post("/shop/order/{$o->token}/review", ['product' => 'tee1', 'rating' => 5, 'text' => 'Even better after washing.'])->assertRedirect(); // updated, not doubled
        $this->getJson("/shop/{$store->id}/reviews")->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('reviews.tee1.avg', 5)->assertJsonPath('reviews.tee1.count', 1)->assertJsonPath('reviews.tee1.items.0.name', 'Ann B.')
            ->assertJsonPath('reviews.tee1.items.0.text', 'Even better after washing.');
        $rid = $this->api($token)->getJson("/builder/stores/{$store->id}/reviews")->assertOk()->json('reviews.0.id');
        $this->api($token)->postJson("/builder/stores/{$store->id}/reviews/$rid", ['hidden' => true])->assertOk();
        $this->getJson("/shop/{$store->id}/reviews")->assertJsonMissingPath('reviews.tee1');
        $this->api($this->token('other@example.com'))->postJson("/builder/stores/{$store->id}/reviews/$rid", ['delete' => true])->assertNotFound();
    }

    public function test_customers_see_their_orders_with_an_emailed_code(): void
    {
        $token = $this->token();
        $store = $this->bigStore($token);
        $this->order($store, [['id' => 'ebook', 'qty' => 1]]);
        $this->get("/shop/{$store->id}/account")->assertOk()->assertSee('Send my code');
        // a stranger's address: same answer, no email
        $this->post("/shop/{$store->id}/account/code", ['email' => 'nobody@example.com'])->assertRedirect();
        Mail::assertNotSent(\App\Mail\BuilderShopSignIn::class);
        $this->get("/shop/{$store->id}/account")->assertSee('If <b>nobody@example.com</b> has ordered', false);
        $this->post("/shop/{$store->id}/account/logout");
        $this->post("/shop/{$store->id}/account/code", ['email' => 'ANN@example.com'])->assertRedirect();
        $code = null;
        Mail::assertSent(\App\Mail\BuilderShopSignIn::class, function ($m) use (&$code) { $code = $m->code; return $m->hasTo('ann@example.com'); });
        $this->post("/shop/{$store->id}/account/verify", ['code' => '000000' === $code ? '111111' : '000000'])->assertStatus(422)->assertSee('isn’t right');
        $this->post("/shop/{$store->id}/account/verify", ['code' => $code])->assertRedirect(route('shop.account', $store->id));
        $this->get("/shop/{$store->id}/account")->assertSee('#1001')->assertSee('Recipe e-book')->assertSee(BuilderOrder::first()->receiptUrl(), false);
    }

    public function test_abandoned_card_orders_get_one_reminder_and_coupons_are_reused(): void
    {
        $this->saveKeys();
        $token = $this->token();
        $store = $this->bigStore($token, ['card' => true]);
        $store->forceFill(['stripe_test_account' => 'acct_1Shop'])->save();
        $this->api($token)->postJson("/builder/stores/{$store->id}/discounts", ['code' => 'TEN', 'kind' => 'percent', 'value' => 10])->assertOk();
        Http::fake(['api.stripe.com/v1/coupons' => Http::response(['id' => 'co_1']), 'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_abandon1abcd', 'url' => 'https://checkout.stripe.com/x']),
            'api.stripe.com/v1/checkout/sessions/*/expire' => Http::response([]),
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['payment_status' => 'unpaid', 'amount_total' => 0, 'currency' => 'usd', 'metadata' => []])]);
        $this->order($store->fresh(), [['id' => 'ebook', 'qty' => 1]], ['_pay' => 'card', '_code' => 'TEN', '_back' => 'https://localhost/p/big/shop'])->assertRedirect('https://checkout.stripe.com/x');
        $o = BuilderOrder::first();
        $this->assertSame('co_1', $o->stripe_coupon);
        $this->post('/shop/order/'.$o->token.'/pay')->assertRedirect('https://checkout.stripe.com/x');
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($p) => $p[0]->url() === 'https://api.stripe.com/v1/coupons')->count()); // reused
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions' && $r['discounts[0][coupon]'] === 'co_1');

        $this->travel(70)->minutes();
        Shop::releaseExpired($store); // what the scheduler does every five minutes
        $this->assertSame('cancelled', $o->fresh()->status);
        Mail::assertSent(\App\Mail\BuilderOrderReminder::class, fn ($m) => $m->hasTo('ann@example.com') && str_contains($m->render(), 'https://localhost/p/big/shop'));
        Shop::remind($o->fresh());
        Mail::assertSent(\App\Mail\BuilderOrderReminder::class, 1); // only once

        // a shop that turned reminders off sends none
        $quiet = $this->bigStore($this->token('quiet@example.com'), ['card' => true, 'remind' => false]);
        $this->assertFalse($quiet->settings['remind']);
    }
}
