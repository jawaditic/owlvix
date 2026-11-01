<?php

namespace App\Http\Controllers;

use App\Mail\BuilderOrderPlaced;
use App\Mail\BuilderOrderReceipt;
use App\Models\BuilderOrder;
use App\Models\BuilderStore;
use App\Support\Shop;
use App\Support\Stripe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Checkout for shops made with the online store builder. The shop's pages (published or downloaded, no session)
 * post the cart here; prices come from the shop's saved products. Cash on delivery and bank transfer orders are
 * placed straight away; card orders go to Stripe Checkout on the owner's own Stripe account, and count once Stripe
 * says they're paid. The customer ends on a private receipt page (/shop/order/{token}).
 */
class ShopController extends Controller
{
    private const ORDERS_PER_IP_HOUR = 10;

    private const ORDERS_PER_DAY = 300;

    /** Emails to customers per shop per day — the receipt goes to an address someone typed in. */
    private const RECEIPTS_PER_DAY = 100;

    private const RECEIPTS_PER_ACCOUNT_DAY = 150;

    private const RECEIPTS_ALL_DAY = 2000;

    public function order(Request $request, string $id)
    {
        $store = BuilderStore::whereKey($id)->whereNull('disabled_at')->first();
        if (! $store) {
            return $this->problem(null, null, ['This shop isn’t taking orders at the moment.'], 404);
        }
        $back = $this->backUrl($request);
        if (filled($request->input('_hp'))) { // bots fill the hidden field: pretend it worked
            return $this->problem($store, $back, ['Your order couldn’t be placed. Please try again.'], 422);
        }
        $s = $store->settings ?? [];
        $ways = Shop::payWays($store);
        [$lines, $errors] = Shop::lines($store, $request->input('_cart'));
        if ($errors) {
            return $this->problem($store, $back, $errors, 422);
        }
        // only downloads and gift cards: nothing to post, so no delivery, address or country
        $ships = Shop::ships($lines);
        $deliveries = $ships ? array_values(array_filter(Shop::DELIVERY, fn ($d) => ! empty($s[$d]))) : ['digital'];
        $delivery = $ships ? $request->input('_delivery', $deliveries[0] ?? 'delivery') : 'digital';
        $byCountry = $delivery === 'delivery' && Shop::byCountry($store);
        $input = ['_delivery' => $delivery] + $request->all();
        if (! $ships) {
            unset($input['address'], $input['country']);
        }

        $validator = Validator::make($input, [
            'country' => [$byCountry ? 'required' : 'nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            '_code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => [! empty($s['phone_req']) ? 'required' : 'nullable', 'string', 'max:24'],
            '_dial' => ['nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'address' => [$delivery === 'delivery' ? 'required' : 'nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            '_delivery' => ['required', 'in:'.implode(',', $deliveries ?: ['delivery'])],
            '_pay' => ['required', 'in:'.implode(',', $ways ?: ['none'])],
        ], [
            'name.required' => 'Please enter your name.', 'name.max' => 'Please keep your name under 100 characters.',
            'email.required' => 'Please enter your email.', 'email.email' => 'Please enter a valid email address.', 'email.max' => 'Please enter a shorter email address.',
            'phone.required' => 'Please enter your phone number.', 'phone.max' => 'Please enter a shorter phone number.',
            'address.required' => 'Please enter the delivery address.', 'address.max' => 'Please keep the address under 1000 characters.',
            'note.max' => 'Please keep your note under 1000 characters.',
            'country.required' => 'Please choose your country.', 'country.regex' => 'Please choose your country.',
            '_delivery.in' => 'Please choose delivery or pick-up.', '_delivery.required' => 'Please choose delivery or pick-up.',
            '_pay.required' => 'Please choose how you’d like to pay.',
            '_pay.in' => $request->input('_pay') === 'card' ? 'Card payments aren’t available right now. Please choose another way to pay.' : 'Please choose how you’d like to pay.',
        ]);
        if ($validator->fails()) {
            return $this->problem($store, $back, $validator->errors()->all(), 422);
        }
        $data = $validator->validated();
        foreach (['name', 'address', 'note'] as $field) {
            if (preg_match_all('#https?://|www\.#i', (string) ($data[$field] ?? '')) > 2) {
                return $this->problem($store, $back, ['Please leave links out of your order details.'], 422);
            }
        }
        // the calling code picked beside the phone box goes in front: "+44 7911 123456" (a number typed with + keeps its own)
        $data['phone'] = \App\Support\Phone::combine($data['phone'] ?? null, $data['_dial'] ?? null);
        $country = $byCountry ? strtoupper($data['country']) : null;
        if ($byCountry && ! Shop::zone($store, $country)) {
            return $this->problem($store, $back, ['Sorry, this shop doesn’t deliver to '.\App\Support\Country::name($country).'.'.(! empty($s['pickup']) ? ' You can choose pick-up instead.' : '')], 422);
        }
        $code = $gift = null;
        if (filled($data['_code'] ?? null)) {
            // wrong codes count against the same limit as the cart's code check, so codes can't be guessed here instead
            $tries = 'shop-code:'.$store->id.':'.$request->ip();
            if (RateLimiter::tooManyAttempts($tries, 20)) {
                return $this->problem($store, $back, ['Too many discount codes tried. Please wait a few minutes, or order without one.'], 429);
            }
            $code = Shop::discount($store, $data['_code']);
            $gift = $code ? null : Shop::giftCard($store, $data['_code']); // the same box takes a gift card
            $sub = array_sum(array_map(fn ($l) => $l['price'] * $l['qty'], $lines)) - Shop::giftLinesTotal($lines); // (codes are for goods, not gift cards)
            if ((! $code && ! $gift) || $code?->problem($sub, $store->currency)) {
                RateLimiter::hit($tries, 600);
            }
            if ($gift) {
                // (a gift card can't buy another gift card)
                if (collect($lines)->contains('kind', 'gift')) {
                    return $this->problem($store, $back, ['A gift card can’t be used to buy gift cards. Please remove it from your cart, or the code.'], 422);
                }
            } elseif (! $code) {
                return $this->problem($store, $back, ['The discount code “'.strtoupper(trim($data['_code'])).'” isn’t valid. Check it, or leave it empty.'], 422);
            }
            if ($code && ($why = $code->problem($sub, $store->currency))) {
                return $this->problem($store, $back, [$why], 422);
            }
        }
        $totals = Shop::totals($store, $lines, $data['_delivery'], $country, $code, $gift);
        if ($totals['total'] <= 0 && $data['_pay'] === 'card') {
            return $this->problem($store, $back, ['There is nothing to pay for. Please choose another way to pay.'], 422);
        }

        if ($this->overLimit($store, $request)) {
            return $this->problem($store, $back, ['Too many orders have been sent from here. Please try again later.'], 429);
        }

        $card = $data['_pay'] === 'card';
        // Every new order holds stock until it's paid (card: up to an hour; cash on delivery and bank transfer: until the
        // owner deals with it), so only a few at a time from one place — nobody can make a shop look sold out.
        $holds = 'shop-holds:'.$store->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($holds, 3)) {
            return $this->problem($store, $back, [$card
                ? 'You have unpaid orders waiting. Please finish paying for one of them, or try again in an hour.'
                : 'You’ve placed several orders just now. If you need to change one, message the shop — or try again in an hour.'], 429);
        }
        RateLimiter::hit($holds, Shop::HOLD_MINUTES * 60);
        Shop::releaseExpired($store); // unpaid card orders that ran out of time give their stock back first
        try {
            $order = $this->placeOrder($store, $data, $lines, $totals, $back, $card, $country, $code, $gift);
        } catch (\RuntimeException $e) {
            return $this->problem($store, $back, [$e->getMessage()], 409);
        }
        if (is_string($order)) {
            return $this->problem($store, $back, [$order], 409);
        }

        if (! $card && $order->total === 0) { // a gift card paid for all of it
            $order->forceFill(['paid_at' => now()])->save();
            Shop::onPaid($order);
        }
        if ($card) {
            if ($to = $this->toStripe($order)) {
                return $to;
            }
            // Stripe said no (or is down): the order goes, and its stock with it, so nothing is held for nothing
            $order->forceFill(['status' => 'cancelled'])->save();
            Shop::giveBack($order);

            return $this->problem($store, $back, ['Card payment couldn’t be started right now. Please try again in a minute, or choose another way to pay.'], 502);
        }
        $this->sendEmails($order);

        return redirect()->route('shop.order', $order->token, 303);
    }

    /**
     * Saves the order, one at a time per shop: its number, its stock and a use of its discount code. Returns the
     * problem (as a string) when there isn't enough stock; a code that ran out meanwhile throws and undoes it all.
     */
    private function placeOrder(BuilderStore $store, array $data, array $lines, array $totals, ?string $back, bool $card, ?string $country, $code, $gift = null): BuilderOrder|string
    {
        return DB::transaction(function () use ($store, $data, $lines, $totals, $back, $card, $country, $code, $gift) {
            // one order at a time per shop: the number, the stock and the code's uses
            $locked = BuilderStore::whereKey($store->id)->lockForUpdate()->first();
            if ($problem = Shop::takeStock($locked, $lines)) {
                return $problem;
            }
            if ($code) {
                $fresh = $locked->discounts()->whereKey($code->id)->lockForUpdate()->first();
                if (! $fresh || ($why = $fresh->problem($totals['subtotal'], $locked->currency))) {
                    throw new \RuntimeException($why ?? 'This discount code can’t be used any more.');
                }
                $fresh->increment('uses');
            }
            if ($gift && $totals['gift_amount'] > 0) { // the gift card pays its part now (it gets it back if the order is cancelled)
                $card_ = $locked->giftCards()->whereKey($gift->id)->lockForUpdate()->first();
                if (! $card_ || $card_->balance < $totals['gift_amount']) {
                    throw new \RuntimeException('This gift card has just been used. Please check your cart and try again.');
                }
                $card_->decrement('balance', $totals['gift_amount']);
            }
            $locked->increment('orders_count');

            return $locked->orders()->create([
                'number' => $locked->orders_count, 'token' => Str::random(40), 'status' => $card ? 'unpaid' : 'new', 'pay' => $data['_pay'],
                'delivery' => $data['_delivery'], 'name' => trim($data['name']), 'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'],
                'address' => $data['_delivery'] === 'delivery' ? trim($data['address']) : null, 'country' => $country,
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null, 'discount_code' => $code?->code, 'gift_code' => $totals['gift_amount'] ? $gift?->code : null,
                'items' => $lines, 'stock_taken' => true, 'hold_until' => $card ? now()->addMinutes(Shop::HOLD_MINUTES) : null, 'currency' => $store->currency, 'back' => $back, 'stripe_mode' => $card ? Shop::cardMode($store) : null,
            ] + $totals);
        });
    }

    /** The customer's receipt. Coming back from Stripe (?paid=cs_…) checks the payment first. */
    public function receipt(Request $request, string $token)
    {
        $order = BuilderOrder::where('token', $token)->with('store')->firstOrFail();
        $session = $request->query('paid');
        if (! $order->isPaid() && $order->pay === 'card' && is_string($session) && preg_match('/^cs_[A-Za-z0-9_]{10,200}$/', $session)) {
            $this->checkPaid($order, $session);
            $order->refresh();
        }

        return response()->view('builder.shop-order', ['order' => $order, 'store' => $order->store, 'result' => $request->query('r')])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer'); // the link is the key to the order
    }

    /** "Pay now" on an unpaid card order (the customer went back from Stripe, or Stripe was down). */
    public function pay(string $token)
    {
        $order = BuilderOrder::where('token', $token)->with('store')->firstOrFail();
        // Paid already, but the tab closed before Stripe sent them back? Then "Pay now" must not replace that payment.
        if (! $order->isPaid() && $order->stripe_session && self::checkPaid($order)) {
            return redirect()->route('shop.order', $order->token, 303);
        }
        Shop::releaseExpired($order->store);
        if ($order->fresh()->status === 'cancelled' && ! $order->isPaid()) {
            return redirect()->route('shop.order', ['token' => $order->token, 'r' => 'expired'], 303);
        }
        if ($order->isPaid() || $order->pay !== 'card' || $order->status !== 'unpaid') {
            return redirect()->route('shop.order', $order->token, 303);
        }
        if (! $order->store->isActive() || ! ($mode = Shop::cardMode($order->store))) {
            return redirect()->route('shop.order', ['token' => $order->token, 'r' => 'off'], 303);
        }
        // A new payment page keeps the stock held for a while longer — but only a few times per order, so an unpaid
        // order can't keep stock away from other customers for ever
        $renewals = 'shop-pay-again:'.$order->id;
        if (RateLimiter::tooManyAttempts($renewals, 3)) {
            return redirect()->route('shop.order', ['token' => $order->token, 'r' => 'error'], 303);
        }
        RateLimiter::hit($renewals, 86400);
        // one open Stripe page per order (no paying twice), and a fresh hold that outlasts it
        Shop::closeStripe($order);
        $order->update(['stripe_mode' => $mode, 'hold_until' => now()->addMinutes(Shop::HOLD_MINUTES)]);

        return $this->toStripe($order) ?? redirect()->route('shop.order', ['token' => $order->token, 'r' => 'error'], 303);
    }

    /** Stripe's checkout page for a card order, or null when Stripe can't be reached. */
    private function toStripe(BuilderOrder $order)
    {
        $store = $order->store;
        $zero = Shop::zeroDecimal($order->currency);
        $whole = fn (int $minor) => $zero ? $minor : $minor / 100;
        $lines = array_map(fn ($l) => ['name' => $l['name'].($l['option'] ? ' ('.$l['option'].')' : ''), 'price' => $whole($l['price']), 'qty' => $l['qty']], $order->items);
        if ($order->delivery_fee > 0) {
            $lines[] = ['name' => 'Delivery', 'price' => $whole($order->delivery_fee), 'qty' => 1];
        }
        if ($order->tax > 0 && ! $order->tax_included) {
            $lines[] = ['name' => $store->settings['tax_label'] ?? 'Tax', 'price' => $whole($order->tax), 'qty' => 1];
        }
        // Stripe's page closes before the order's hold ends (Stripe needs at least 30 minutes)
        $hold = $order->hold_until ?? $order->created_at->copy()->addMinutes(Shop::HOLD_MINUTES);
        $left = max(30, (int) now()->diffInMinutes($hold->copy()->subMinutes(5), false));
        if ($hold->lt(now()->addMinutes($left + 5))) {
            $order->update(['hold_until' => $hold = now()->addMinutes($left + 5)]);
        }
        // {CHECKOUT_SESSION_ID} is filled in by Stripe, so it must not be URL-encoded
        $receipt = route('shop.order', $order->token);
        try {
            $s = Stripe::checkoutOn($order->stripe_mode, $store->stripeAccount($order->stripe_mode), $order->currency, $lines,
                $receipt.'?paid={CHECKOUT_SESSION_ID}', $receipt.'?r=cancelled',
                ['owlvix_store' => $store->id, 'owlvix_order' => (string) $order->id], $order->email,
                $order->discount + $order->gift_amount, trim(($order->discount_code ?? '').($order->gift_amount ? ' + gift card' : ''), ' +') ?: 'Discount',
                now()->addMinutes($left)->timestamp, $order->stripe_coupon);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        $order->update(['stripe_session' => $s['id'], 'stripe_coupon' => $s['coupon'] ?? null]);

        return redirect()->away($s['url'], 303);
    }

    /**
     * A cash or bank order the owner has just marked paid: gift cards bought are made, and the customer is told —
     * with their gift card codes and download links — when the order has any.
     */
    public static function paidNow(BuilderOrder $order, string $ownerEmail): void
    {
        Shop::onPaid($order);
        if (! $order->digitalLines() || ! self::mayEmailCustomer($order->store)) {
            return;
        }
        \Illuminate\Support\defer(function () use ($order, $ownerEmail) {
            try {
                Mail::to($order->email)->send(new \App\Mail\BuilderOrderPaid($order->fresh(), $ownerEmail));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** The shop's published reviews by product: average, count and the latest ones. Any page may read it. */
    public function reviews(string $id)
    {
        $store = BuilderStore::whereKey($id)->whereNull('disabled_at')->first();
        $out = [];
        foreach ($store ? $store->reviews()->where('hidden', false)->latest()->take(500)->get()->groupBy('product_id') : [] as $pid => $list) {
            $out[$pid] = ['avg' => round($list->avg('rating'), 1), 'count' => $list->count(), 'items' => $list->take(10)->map->toPublic()->values()];
        }

        return response()->json(['ok' => (bool) $store, 'reviews' => (object) $out], $store ? 200 : 404)
            ->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'public, max-age=60');
    }

    /** A buyer reviews a product from their order page, once it has been sent (or, for downloads, paid). */
    public function review(Request $request, string $token)
    {
        $order = BuilderOrder::where('token', $token)->with('store')->firstOrFail();
        $back = redirect()->to(route('shop.order', $order->token).'#review', 303);
        if (! self::canReview($order)) {
            return $back;
        }
        $v = Validator::make($request->all(), [
            'product' => ['required', 'string', 'in:'.implode(',', array_unique(array_column($order->items, 'id')))],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:1500'],
        ]);
        if ($v->fails() || preg_match_all('#https?://|www\.#i', (string) $request->input('text')) > 1) {
            return redirect()->to(route('shop.order', ['token' => $order->token, 'r' => 'review-error']).'#review', 303);
        }
        $order->store->reviews()->updateOrCreate(['builder_order_id' => $order->id, 'product_id' => $request->input('product')],
            ['name' => $order->name, 'rating' => (int) $request->input('rating'), 'text' => trim((string) $request->input('text')) ?: null]);

        return redirect()->to(route('shop.order', ['token' => $order->token, 'r' => 'reviewed']).'#review', 303);
    }

    /** Buyers review once their order is on its way (or done); downloads and gift cards once paid. */
    public static function canReview(BuilderOrder $order): bool
    {
        return in_array($order->status, ['shipped', 'done'], true) || ($order->status === 'new' && $order->isPaid() && ! Shop::ships($order->items ?? []));
    }

    /** A paid order's download (a product sold as a file), a limited number of times. */
    public function download(string $token, string $product)
    {
        $order = BuilderOrder::where('token', $token)->with('store')->firstOrFail();
        $line = collect($order->items)->first(fn ($l) => $l['id'] === $product && ($l['kind'] ?? '') === 'digital');
        abort_unless($line && $order->isPaid() && $order->status !== 'cancelled', 404);
        $fileId = $line['file'] ?? null;
        $file = $fileId ? $order->store->files()->find($fileId) : null;
        abort_unless($file && \App\Models\BuilderShopFile::disk()->exists($file->path()), 404);
        if ($order->downloads >= \App\Models\BuilderShopFile::MAX_DOWNLOADS * max(1, count($order->digitalLines()))) {
            return redirect()->route('shop.order', ['token' => $order->token, 'r' => 'downloads'], 303);
        }
        $order->increment('downloads');

        return \App\Models\BuilderShopFile::disk()->download($file->path(), $file->name, [
            'Content-Type' => $file->mime, 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Tracked stock for the shop's pages (they mark sold-out sizes and "Only 3 left"). Any page may read it. */
    public function stock(string $id)
    {
        $store = BuilderStore::whereKey($id)->whereNull('disabled_at')->first();

        return response()->json(['ok' => (bool) $store, 'stock' => (object) ($store?->stock ?? [])], $store ? 200 : 404)
            ->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'public, max-age=20');
    }

    /** Checks a discount code from the cart, so the shopper sees the price before ordering. Tries are limited. */
    public function code(Request $request, string $id)
    {
        $cors = fn ($r) => $r->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'no-store');
        $store = BuilderStore::whereKey($id)->whereNull('disabled_at')->first();
        if (! $store) {
            return $cors(response()->json(['ok' => false, 'error' => 'This shop isn’t taking orders.'], 404));
        }
        $key = 'shop-code:'.$store->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return $cors(response()->json(['ok' => false, 'error' => 'Too many tries. Please wait a few minutes.'], 429));
        }
        RateLimiter::hit($key, 600);
        $code = Shop::discount($store, $request->query('c'));
        $sub = Shop::minor($request->query('sub', 0), $store->currency) ?? 0;
        if (! $code && ($gift = Shop::giftCard($store, $request->query('c')))) { // a gift card: its balance comes off the total
            return $cors(response()->json(['ok' => true, 'code' => $gift->code, 'kind' => 'gift', 'label' => 'Gift card · '.Shop::format($gift->balance, $store->currency).' left',
                'value' => Shop::zeroDecimal($store->currency) ? $gift->balance : $gift->balance / 100, 'min' => 0]));
        }
        if (! $code) {
            return $cors(response()->json(['ok' => false, 'error' => 'That code isn’t valid.']));
        }
        if ($why = $code->problem($sub, $store->currency)) {
            return $cors(response()->json(['ok' => false, 'error' => $why]));
        }

        return $cors(response()->json(['ok' => true, 'code' => $code->code, 'kind' => $code->kind, 'label' => $code->label($store->currency),
            'min' => Shop::zeroDecimal($store->currency) ? $code->min_total : $code->min_total / 100,
            'value' => $code->kind === 'amount' ? (Shop::zeroDecimal($store->currency) ? $code->value : $code->value / 100) : $code->value]));
    }

    /** Asks Stripe whether this order's checkout was paid (the right shop, order and amount); marks it paid if so. */
    public static function checkPaid(BuilderOrder $order, ?string $session = null): bool
    {
        $session ??= $order->stripe_session;
        if ($order->isPaid() || ! $session || ! in_array($order->stripe_mode, Stripe::MODES, true)) {
            return $order->isPaid();
        }
        try {
            $s = Stripe::sessionOn($order->stripe_mode, $order->store->stripeAccount($order->stripe_mode), $session);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        $ok = $s['paid'] && ($s['meta']['owlvix_order'] ?? null) === (string) $order->id && ($s['meta']['owlvix_store'] ?? null) === $order->builder_store_id
            && $s['amount'] === $order->total && strtolower($s['currency']) === $order->currency;
        if (! $ok) {
            return false;
        }
        // Only the first check marks it paid and sends the emails (the customer may reload the receipt). An order
        // that was cancelled while its Stripe page was open is paid now: it comes back and takes its stock again.
        $now = DB::table('builder_orders')->where('id', $order->id)->whereNull('paid_at')
            ->update(['paid_at' => now(), 'status' => 'new', 'stripe_session' => $session, 'updated_at' => now()]);
        if ($now) {
            $order->refresh();
            if (! $order->stock_taken) {
                Shop::retake($order);
            }
            Shop::onPaid($order); // gift cards bought are made now (the receipt email carries their codes)
            (new self)->sendEmails($order);
        }

        return true;
    }

    /**
     * The owner hears about every order. The customer gets a receipt only from shops with a live published site (so
     * the shop can be reported and taken down), and only so many a day per shop, per account and for everyone —
     * a receipt goes to an address someone typed in. Without one they still have the receipt page.
     */
    private function sendEmails(BuilderOrder $order): void
    {
        $owner = $order->store->account->email;
        $receipt = self::mayEmailCustomer($order->store);
        if ($receipt) {
            self::countCustomerEmail($order->store);
        }
        \Illuminate\Support\defer(function () use ($owner, $order, $receipt) {
            try {
                Mail::to($owner)->send(new BuilderOrderPlaced($order));
            } catch (\Throwable $e) {
                report($e); // the order is saved either way
            }
            if ($receipt) {
                try {
                    Mail::to($order->email)->send(new BuilderOrderReceipt($order, $owner));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    /** @return array<string, array{int, int}> limiter key => [max, seconds] */
    private static function receiptLimits(BuilderStore $store): array
    {
        return ['shop-receipts:'.$store->id => [self::RECEIPTS_PER_DAY, 86400], 'shop-receipts-acct:'.$store->builder_account_id => [self::RECEIPTS_PER_ACCOUNT_DAY, 86400],
            'shop-receipts-all' => [self::RECEIPTS_ALL_DAY, 86400]];
    }

    /** One more customer email sent for this shop (counts towards the daily limits above). */
    public static function countCustomerEmail(BuilderStore $store): void
    {
        foreach (self::receiptLimits($store) as $key => [$max, $secs]) {
            RateLimiter::hit($key, $secs);
        }
    }

    /** Customer emails (receipt, "on its way") may go out for this shop now. */
    public static function mayEmailCustomer(BuilderStore $store): bool
    {
        if (! $store->account->sites()->where('status', 'live')->exists()) {
            return false;
        }
        foreach (self::receiptLimits($store) as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }

        return true;
    }

    private function overLimit(BuilderStore $store, Request $request): bool
    {
        $ip = 'shop-order-ip:'.$store->id.':'.$request->ip();
        $day = 'shop-order-day:'.$store->id;
        if (RateLimiter::tooManyAttempts($ip, self::ORDERS_PER_IP_HOUR) || RateLimiter::tooManyAttempts($day, self::ORDERS_PER_DAY)) {
            return true;
        }
        RateLimiter::hit($ip, 3600);
        RateLimiter::hit($day, 86400);

        return false;
    }

    /** The shop page the order came from (only http(s), and only on the sites domain, this app or the page's own host). */
    private function backUrl(Request $request): ?string
    {
        $url = $request->input('_back');
        $url = is_string($url) && strlen($url) <= 2000 && preg_match('#^https?://[^\s/]+#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        $referer = (string) $request->headers->get('referer');
        $url ??= preg_match('#^https?://[^\s/]+#i', $referer) && strlen($referer) <= 2000 && filter_var($referer, FILTER_VALIDATE_URL) ? $referer : null;
        if (! $url) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = array_filter([strtolower((string) config('builder.domain')), strtolower((string) parse_url($referer, PHP_URL_HOST)),
            strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)), strtolower($request->getHost())]);

        return in_array($host, $allowed, true) ? preg_replace('/#.*$/', '', $url) : null;
    }

    private function problem(?BuilderStore $store, ?string $back, array $errors, int $status)
    {
        return response()->view('builder.shop-problem', ['store' => $store, 'back' => $back, 'errors_list' => $errors], $status)
            ->header('X-Robots-Tag', 'noindex');
    }
}
