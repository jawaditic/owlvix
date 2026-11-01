<?php

namespace App\Support;

use App\Models\BuilderDiscount;
use App\Models\BuilderGiftCard;
use App\Models\BuilderOrder;
use App\Models\BuilderStore;
use Illuminate\Support\Facades\DB;

/**
 * Online store builder: the checks for a shop's products and settings (sent by the editor, see
 * public/js/builder-engine.js "online store"), money in the currency's smallest unit, and the price of a cart —
 * variants, discount code, shipping by country and tax. Prices always come from the shop's saved products, never
 * from the page that posts the order. Stock is kept here too (BuilderStore::stock), so orders lower it.
 */
class Shop
{
    public const MAX_PRODUCTS = 300;

    public const MAX_VARIANTS = 30;

    public const MAX_ZONES = 20;

    public const MAX_LINES = 50;

    public const MAX_QTY = 99;

    public const PAY = ['card', 'cod', 'bank'];

    /** item: posted or collected; digital: a file to download; gift: a gift card (its price is its value). */
    public const KINDS = ['item', 'digital', 'gift'];

    public const DELIVERY = ['delivery', 'pickup'];

    /** Unpaid card orders hold their stock this long (Stripe's checkout page closes a little earlier). */
    public const HOLD_MINUTES = 65;

    /** The most a single product, fee or order can cost (in whole units), so totals stay sane. */
    private const MAX_PRICE = 1_000_000;

    public static function zeroDecimal(string $currency): bool
    {
        return in_array($currency, Stripe::ZERO_DECIMAL, true);
    }

    /** A price ("24.99", 24.99) in the smallest unit (2499), or null if it isn't a price. */
    public static function minor(mixed $price, string $currency): ?int
    {
        if (is_string($price)) {
            $price = trim(str_replace(',', '', $price));
        }
        if (! is_numeric($price) || (float) $price < 0 || (float) $price > self::MAX_PRICE) {
            return null;
        }

        return Stripe::minor((float) $price, $currency);
    }

    /** 2499 → "24.99" (for the editor's inputs). */
    public static function plain(int $minor, string $currency): string
    {
        return self::zeroDecimal($currency) ? (string) $minor : rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.');
    }

    /** "$24.99", "Rs 1,500", "€8" — for emails and the receipt page. */
    public static function format(int $minor, string $currency): string
    {
        $zero = self::zeroDecimal($currency);
        $amount = $zero ? $minor : $minor / 100;
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
            $f->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, ! $zero && $minor % 100 ? 2 : 0);
            $f->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $zero ? 0 : 2);
            $out = $f->formatCurrency($amount, strtoupper($currency));
            if ($out !== false) {
                return $out;
            }
        }

        return strtoupper($currency).' '.number_format($amount, ! $zero && $minor % 100 ? 2 : 0);
    }

    /**
     * The shop's products from the editor, checked: [{id, name, price (minor), sold, opt, vars: [{id, name, price|null}]}].
     * A variant without its own price costs the product's price. Products without a price are left out.
     *
     * @return list<array{id: string, name: string, price: int, sold: bool, opt: ?string, vars: list<array{id: string, name: string, price: ?int}>}>
     */
    public static function catalog(mixed $products, string $currency): array
    {
        $out = [];
        $seen = [];
        foreach (array_slice(is_array($products) ? $products : [], 0, self::MAX_PRODUCTS) as $p) {
            if (! is_array($p) || ! is_string($p['id'] ?? null) || ! preg_match('/^[a-z0-9]{1,24}$/i', $p['id']) || isset($seen[$p['id']])) {
                continue;
            }
            $name = trim(strip_tags((string) ($p['name'] ?? '')));
            $price = self::minor($p['price'] ?? null, $currency);
            if ($name === '' || $price === null) {
                continue;
            }
            $vars = [];
            foreach (array_slice(is_array($p['vars'] ?? null) ? $p['vars'] : [], 0, self::MAX_VARIANTS) as $v) {
                $vname = is_array($v) && is_string($v['name'] ?? null) ? trim(mb_substr(strip_tags($v['name']), 0, 60)) : '';
                $vid = is_array($v) && is_string($v['id'] ?? null) && preg_match('/^[a-z0-9]{1,24}$/i', $v['id']) ? $v['id'] : null;
                if ($vname === '' || ! $vid || in_array($vid, array_column($vars, 'id'), true) || in_array($vname, array_column($vars, 'name'), true)) {
                    continue;
                }
                $vp = ($v['price'] ?? '') === '' || $v['price'] === null ? null : self::minor($v['price'], $currency);
                $vars[] = ['id' => $vid, 'name' => $vname, 'price' => $vp];
            }
            // older editors sent a plain list of option names
            if (! $vars && is_array($p['options'] ?? null)) {
                foreach (array_slice($p['options'], 0, self::MAX_VARIANTS) as $o) {
                    $o = is_string($o) ? trim(mb_substr($o, 0, 60)) : '';
                    if ($o !== '' && ! in_array($o, array_column($vars, 'name'), true)) {
                        $vars[] = ['id' => 'o'.substr(md5($o), 0, 10), 'name' => $o, 'price' => null];
                    }
                }
            }
            // a second choice (size × colour): names only, every combination costs what the first choice costs
            $vars2 = [];
            foreach (array_slice($vars && is_array($p['vars2'] ?? null) ? $p['vars2'] : [], 0, self::MAX_VARIANTS) as $v) {
                $vname = is_array($v) && is_string($v['name'] ?? null) ? trim(mb_substr(strip_tags($v['name']), 0, 60)) : '';
                $vid = is_array($v) && is_string($v['id'] ?? null) && preg_match('/^[a-z0-9]{1,24}$/i', $v['id']) ? $v['id'] : null;
                if ($vname !== '' && $vid && ! in_array($vid, array_column($vars2, 'id'), true) && ! in_array($vname, array_column($vars2, 'name'), true)) {
                    $vars2[] = ['id' => $vid, 'name' => $vname];
                }
            }
            $kind = in_array($p['kind'] ?? null, self::KINDS, true) ? $p['kind'] : 'item';
            if ($kind === 'gift') { // a gift card is worth what it costs: no sizes
                $vars = $vars2 = [];
            }
            $seen[$p['id']] = true;
            $opt = is_string($p['opt'] ?? null) ? trim(mb_substr($p['opt'], 0, 30)) : '';
            $opt2 = is_string($p['opt2'] ?? null) ? trim(mb_substr($p['opt2'], 0, 30)) : '';
            $out[] = ['id' => $p['id'], 'name' => mb_substr($name, 0, 200), 'price' => $price, 'sold' => ! empty($p['sold']),
                'opt' => $vars ? ($opt ?: 'Option') : null, 'vars' => $vars, 'opt2' => $vars2 ? ($opt2 ?: 'Option') : null, 'vars2' => $vars2,
                'kind' => $kind, 'file' => $kind === 'digital' && is_string($p['file'] ?? null) && preg_match('/^[a-z0-9]{24}$/', $p['file']) ? $p['file'] : null];
        }

        return $out;
    }

    /** The shop's checkout settings from the editor, checked (money in the smallest unit). */
    public static function settings(mixed $s, string $currency): array
    {
        $s = is_array($s) ? $s : [];
        $str = fn (string $k, int $max) => is_string($s[$k] ?? null) ? trim(mb_substr($s[$k], 0, $max)) : '';
        $zones = [];
        foreach (array_slice(is_array($s['zones'] ?? null) ? $s['zones'] : [], 0, self::MAX_ZONES) as $z) {
            if (! is_array($z)) {
                continue;
            }
            $codes = is_array($z['countries'] ?? null) ? $z['countries'] : preg_split('/[\s,]+/', (string) ($z['countries'] ?? ''));
            $codes = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $codes), fn ($c) => preg_match('/^[A-Z]{2}$/', $c))));
            $zones[] = ['name' => is_string($z['name'] ?? null) ? trim(mb_substr($z['name'], 0, 60)) : '', 'countries' => array_slice($codes, 0, 250),
                'fee' => self::minor($z['fee'] ?? 0, $currency) ?? 0, 'free_over' => self::minor($z['freeOver'] ?? 0, $currency) ?? 0];
        }
        $rate = is_numeric($s['taxRate'] ?? null) ? round(max(0, min(50, (float) $s['taxRate'])), 3) : 0.0;
        $out = [
            'delivery' => ($s['delivery'] ?? true) !== false,
            'fee' => self::minor($s['fee'] ?? 0, $currency) ?? 0,
            'free_over' => self::minor($s['freeOver'] ?? 0, $currency) ?? 0,
            'zones' => $zones,
            'pickup' => ! empty($s['pickup']),
            'pickup_text' => $str('pickupText', 200),
            'cod' => ! empty($s['cod']),
            'bank' => ! empty($s['bank']),
            'bank_text' => $str('bankText', 1000),
            'card' => ! empty($s['card']),
            'mode' => ($s['mode'] ?? 'test') === 'live' ? 'live' : 'test',
            'tax_rate' => $rate,
            'tax_incl' => ($s['taxIncl'] ?? true) !== false,
            'tax_label' => $str('taxLabel', 20) ?: 'Tax',
            'tax_ship' => ! empty($s['taxShip']),
            'phone_req' => ($s['phoneReq'] ?? true) !== false,
            'remind' => ($s['remind'] ?? true) !== false, // one email to people who started paying by card and stopped
            'thanks' => $str('thanks', 300),
            'whatsapp' => substr(preg_replace('/\D/', '', (string) ($s['whatsapp'] ?? '')), 0, 15),
        ];
        if (! $out['delivery'] && ! $out['pickup']) {
            $out['delivery'] = true;
        }
        if (! $out['cod'] && ! $out['bank'] && ! $out['card']) {
            $out['cod'] = true;
        }

        return $out;
    }

    /** 'test' or 'live' when this shop takes card payments right now, else null. */
    public static function cardMode(BuilderStore $store): ?string
    {
        $s = $store->settings ?? [];
        $mode = ($s['mode'] ?? 'test') === 'live' ? 'live' : 'test';

        return ! empty($s['card']) && Stripe::available($mode) && $store->stripeAccount($mode) ? $mode : null;
    }

    /** The ways to pay this shop offers right now. */
    public static function payWays(BuilderStore $store): array
    {
        $s = $store->settings ?? [];

        return array_values(array_filter(self::PAY, fn ($p) => $p === 'card' ? self::cardMode($store) !== null : ! empty($s[$p])));
    }

    /** Delivery asks for a country when the shop charges by country (shipping zones). */
    public static function byCountry(BuilderStore $store): bool
    {
        return ! empty($store->settings['zones']);
    }

    /** The delivery fee and free-delivery amount for a country, or null when the shop doesn't deliver there. */
    public static function zone(BuilderStore $store, ?string $country): ?array
    {
        $s = $store->settings ?? [];
        if (empty($s['zones'])) {
            return ['fee' => (int) ($s['fee'] ?? 0), 'free_over' => (int) ($s['free_over'] ?? 0)];
        }
        $country = strtoupper((string) $country);
        foreach ($s['zones'] as $z) {
            if (in_array($country, $z['countries'], true)) {
                return $z;
            }
        }
        foreach ($s['zones'] as $z) { // a zone without countries: everywhere else
            if (! $z['countries'] && preg_match('/^[A-Z]{2}$/', $country)) {
                return $z;
            }
        }

        return null;
    }

    /** The stock key of a product or one of its variants ("tee1", "tee1:v2"). */
    /** The stock key of a product or one of its variants ("tee1", "tee1:v2", "tee1:v2_c3" for size × colour). */
    public static function key(string $id, ?string $vid, ?string $vid2 = null): string
    {
        return $vid ? $id.':'.$vid.($vid2 ? '_'.$vid2 : '') : $id;
    }

    /** Every stock key of a catalog product. */
    public static function keysOf(array $p): array
    {
        if (! $p['vars']) {
            return [$p['id'] => $p['name']];
        }
        $out = [];
        foreach ($p['vars'] as $v) {
            foreach (($p['vars2'] ?? []) ?: [null] as $v2) {
                $out[self::key($p['id'], $v['id'], $v2['id'] ?? null)] = $p['name'].' ('.$v['name'].($v2 ? ' / '.$v2['name'] : '').')';
            }
        }

        return $out;
    }

    /**
     * The order lines for a posted cart ([{id, vid, qty}] as JSON — or {opt} from older pages), priced from the shop's
     * products. Stock is checked when the order is saved (Shop::takeStock), under a lock.
     *
     * @return array{0: list<array{id: string, vid: ?string, name: string, option: ?string, price: int, qty: int}>, 1: list<string>}  [lines, errors]
     */
    public static function lines(BuilderStore $store, mixed $cart): array
    {
        $cart = is_string($cart) && strlen($cart) <= 20000 ? json_decode($cart, true) : null;
        if (! is_array($cart) || ! $cart) {
            return [[], ['Your cart is empty. Please add something first.']];
        }
        if (count($cart) > self::MAX_LINES) {
            return [[], ['Please order fewer than '.self::MAX_LINES.' different items at once.']];
        }
        $products = collect($store->catalog ?? [])->keyBy('id');
        $lines = [];
        $errors = [];
        foreach ($cart as $l) {
            $id = is_array($l) && is_string($l['id'] ?? null) ? $l['id'] : '';
            $qty = is_array($l) && is_numeric($l['qty'] ?? null) ? (int) $l['qty'] : 0;
            $vid = is_array($l) && is_string($l['vid'] ?? null) ? $l['vid'] : '';
            $vid2 = is_array($l) && is_string($l['vid2'] ?? null) ? $l['vid2'] : '';
            $opt = is_array($l) && is_string($l['opt'] ?? null) ? trim($l['opt']) : '';
            $p = $products->get($id);
            if (! $p) {
                $errors[] = 'Something in your cart is no longer sold. Please go back and check your cart.';

                continue;
            }
            if ($p['sold']) {
                $errors[] = '“'.$p['name'].'” is sold out.';

                continue;
            }
            if ($qty < 1 || $qty > self::MAX_QTY) {
                $errors[] = 'Please choose between 1 and '.self::MAX_QTY.' of “'.$p['name'].'”.';

                continue;
            }
            $var = null;
            if ($p['vars']) {
                $var = collect($p['vars'])->first(fn ($v) => ($vid !== '' && $v['id'] === $vid) || ($vid === '' && $opt !== '' && $v['name'] === $opt));
                if (! $var) {
                    $errors[] = 'Please choose your '.strtolower($p['opt'] ?? 'option').' for “'.$p['name'].'”.';

                    continue;
                }
            }
            $var2 = null;
            if (! empty($p['vars2'])) {
                $var2 = collect($p['vars2'])->first(fn ($v) => $v['id'] === $vid2);
                if (! $var2) {
                    $errors[] = 'Please choose your '.strtolower($p['opt2'] ?? 'option').' for “'.$p['name'].'”.';

                    continue;
                }
            }
            $line = ['id' => $id, 'vid' => $var['id'] ?? null, 'vid2' => $var2['id'] ?? null, 'name' => $p['name'],
                'option' => isset($var) ? $var['name'].($var2 ? ' / '.$var2['name'] : '') : null, 'price' => $var['price'] ?? $p['price'], 'qty' => $qty,
                'kind' => $p['kind'] ?? 'item', 'file' => $p['file'] ?? null];
            foreach ($lines as &$same) { // the same product and variant twice: one line
                if ($same['id'] === $id && $same['vid'] === $line['vid'] && $same['vid2'] === $line['vid2']) {
                    $same['qty'] = min(self::MAX_QTY, $same['qty'] + $qty);

                    continue 2;
                }
            }
            unset($same);
            $lines[] = $line;
        }

        return [$errors ? [] : $lines, array_values(array_unique($errors))];
    }

    /** The shop's discount code (any case), or null. */
    public static function discount(BuilderStore $store, mixed $code): ?BuilderDiscount
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        return $code !== '' && strlen($code) <= 40 ? $store->discounts()->where('code', $code)->first() : null;
    }

    /** The shop's gift card for this code (any case, with or without dashes), when it has money left. */
    public static function giftCard(BuilderStore $store, mixed $code): ?BuilderGiftCard
    {
        $code = is_string($code) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)) : '';
        if (! preg_match('/^GIFT[A-Z0-9]{8}$/', $code)) {
            return null;
        }

        return $store->giftCards()->where('code', substr($code, 0, 4).'-'.substr($code, 4, 4).'-'.substr($code, 8))
            ->where('currency', $store->currency)->where('balance', '>', 0)->first();
    }

    /** Something in these lines has to be posted or collected (not only downloads and gift cards). */
    public static function ships(array $lines): bool
    {
        return collect($lines)->contains(fn ($l) => ($l['kind'] ?? 'item') === 'item');
    }

    /**
     * Subtotal, discount, delivery fee, tax and total (smallest unit) for these lines.
     *
     * @return array{subtotal: int, discount: int, delivery_fee: int, tax: int, tax_included: bool, total: int}
     */
    public static function totals(BuilderStore $store, array $lines, string $delivery, ?string $country = null, ?BuilderDiscount $code = null, ?BuilderGiftCard $gift = null): array
    {
        $s = $store->settings ?? [];
        $sub = array_sum(array_map(fn ($l) => $l['price'] * $l['qty'], $lines));
        $off = match ($code?->kind) {
            'percent' => intdiv($sub * min(100, $code->value), 100),
            'amount' => min($code->value, $sub),
            default => 0,
        };
        $after = $sub - $off;
        $fee = 0;
        if ($delivery === 'delivery' && self::ships($lines) && ($zone = self::zone($store, $country))) {
            $fee = $code?->kind === 'shipping' || ($zone['free_over'] > 0 && $after >= $zone['free_over']) ? 0 : (int) $zone['fee'];
        }
        $rate = (float) ($s['tax_rate'] ?? 0);
        $incl = ($s['tax_incl'] ?? true) !== false;
        $base = $after + (! empty($s['tax_ship']) ? $fee : 0);
        $tax = $rate > 0 ? ($incl ? $base - (int) round($base / (1 + $rate / 100)) : (int) round($base * $rate / 100)) : 0;

        $total = $after + $fee + ($incl ? 0 : $tax);
        $giftAmount = $gift ? min($gift->balance, $total) : 0; // a gift card pays what it can; the rest is paid as usual

        return ['subtotal' => $sub, 'discount' => $off, 'delivery_fee' => $fee, 'tax' => $tax, 'tax_included' => $incl, 'gift_amount' => $giftAmount, 'total' => $total - $giftAmount];
    }

    /**
     * Takes the order's stock (and a use of its code), called inside the order's transaction with the store row
     * locked. Returns the problem when there isn't enough left.
     */
    public static function takeStock(BuilderStore $store, array $lines): ?string
    {
        $stock = $store->stock ?? [];
        foreach ($lines as $l) {
            $k = self::key($l['id'], $l['vid'], $l['vid2'] ?? null);
            if (array_key_exists($k, $stock)) {
                if ($stock[$k] < $l['qty']) {
                    return $stock[$k] > 0
                        ? 'Only '.$stock[$k].' of “'.$l['name'].($l['option'] ? ' ('.$l['option'].')' : '').'” '.($stock[$k] === 1 ? 'is' : 'are').' left. Please change your cart.'
                        : '“'.$l['name'].($l['option'] ? ' ('.$l['option'].')' : '').'” has just sold out.';
                }
                $stock[$k] -= $l['qty'];
            }
        }
        $store->stock = $stock ?: null;
        $store->save();

        return null;
    }

    /** A cancelled order gives its stock and its code's use back — once, even when two requests cancel it together. */
    public static function giveBack(BuilderOrder $order): void
    {
        DB::transaction(function () use ($order) {
            // only the request that flips stock_taken gives anything back
            if (! BuilderOrder::whereKey($order->id)->where('stock_taken', true)->update(['stock_taken' => false])) {
                return;
            }
            $store = BuilderStore::whereKey($order->builder_store_id)->lockForUpdate()->first();
            $stock = $store->stock ?? [];
            foreach ($order->items as $l) {
                $k = self::key($l['id'], $l['vid'] ?? null, $l['vid2'] ?? null);
                if (array_key_exists($k, $stock)) {
                    $stock[$k] += $l['qty'];
                }
            }
            $store->stock = $stock ?: null;
            $store->save();
            if ($order->discount_code) {
                $store->discounts()->where('code', $order->discount_code)->where('uses', '>', 0)->decrement('uses');
            }
            if ($order->gift_code && $order->gift_amount) { // the gift card gets back what it paid
                $store->giftCards()->where('code', $order->gift_code)->increment('balance', $order->gift_amount);
            }
        });
        $order->stock_taken = false;
    }

    /**
     * A paid order that had given its stock back (it was cancelled while its Stripe page was still open) takes it
     * again — the money is in, so the stock goes, down to 0 at most — and its code's use.
     */
    public static function retake(BuilderOrder $order): void
    {
        DB::transaction(function () use ($order) {
            if (! BuilderOrder::whereKey($order->id)->where('stock_taken', false)->update(['stock_taken' => true])) {
                return;
            }
            $store = BuilderStore::whereKey($order->builder_store_id)->lockForUpdate()->first();
            $stock = $store->stock ?? [];
            foreach ($order->items as $l) {
                $k = self::key($l['id'], $l['vid'] ?? null, $l['vid2'] ?? null);
                if (array_key_exists($k, $stock)) {
                    $stock[$k] = max(0, $stock[$k] - $l['qty']);
                }
            }
            $store->stock = $stock ?: null;
            $store->save();
            if ($order->discount_code) {
                $store->discounts()->where('code', $order->discount_code)->increment('uses');
            }
            if ($order->gift_code && $order->gift_amount && ($card = $store->giftCards()->where('code', $order->gift_code)->lockForUpdate()->first())) {
                $card->update(['balance' => max(0, $card->balance - $order->gift_amount)]);
            }
        });
        $order->stock_taken = true;
    }

    /**
     * Card orders left unpaid past their hold are cancelled, so their stock can be sold again — after asking Stripe
     * once more (the customer may have paid and closed the page) and closing their Stripe page.
     */
    public static function releaseExpired(BuilderStore $store): void
    {
        $store->orders()->where('status', 'unpaid')->whereNull('paid_at')
            ->where(fn ($q) => $q->where('hold_until', '<', now())->orWhere(fn ($q) => $q->whereNull('hold_until')->where('created_at', '<', now()->subMinutes(self::HOLD_MINUTES))))
            ->limit(20)->get()
            ->each(function (BuilderOrder $o) {
                if ($o->stripe_session && \App\Http\Controllers\ShopController::checkPaid($o)) {
                    return; // it was paid after all
                }
                self::closeStripe($o);
                if (BuilderOrder::whereKey($o->id)->where('status', 'unpaid')->whereNull('paid_at')->update(['status' => 'cancelled'])) {
                    self::giveBack($o);
                    self::remind($o);
                }
            });
    }

    /** Closes the order's open Stripe page, so it can't be paid any more (best effort). */
    public static function closeStripe(BuilderOrder $order): void
    {
        if ($order->stripe_session && in_array($order->stripe_mode, Stripe::MODES, true) && ! $order->isPaid()) {
            try {
                Stripe::expireSession($order->stripe_mode, $order->store->stripeAccount($order->stripe_mode), $order->stripe_session);
            } catch (\Throwable $e) {
                // already closed or paid: checkPaid sorts out the second case
            }
        }
    }

    /**
     * One email to someone who started paying by card and stopped (an abandoned cart): what they chose and a link back
     * to the shop. Only when the shop allows it, once per order, and only to shops that may email customers.
     */
    public static function remind(BuilderOrder $order): void
    {
        $store = $order->store;
        if (($store->settings['remind'] ?? true) === false || ! $order->email || ! \App\Http\Controllers\ShopController::mayEmailCustomer($store)) {
            return;
        }
        if (! BuilderOrder::whereKey($order->id)->whereNull('reminded_at')->update(['reminded_at' => now()])) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::to($order->email)->send(new \App\Mail\BuilderOrderReminder($order->fresh(), $store->account->email));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * An order has just been paid: its gift cards are made (once). Returns the cards made now.
     *
     * @return list<BuilderGiftCard>
     */
    public static function onPaid(BuilderOrder $order): array
    {
        $store = $order->store;
        $gifts = array_values(array_filter($order->items ?? [], fn ($l) => ($l['kind'] ?? '') === 'gift'));
        if (! $gifts || $store->giftCards()->where('builder_order_id', $order->id)->exists()) {
            return [];
        }
        $made = [];
        foreach ($gifts as $l) {
            for ($i = 0; $i < $l['qty']; $i++) {
                $made[] = $store->giftCards()->create(['code' => BuilderGiftCard::newCode($store), 'currency' => $order->currency, 'initial' => $l['price'],
                    'balance' => $l['price'], 'builder_order_id' => $order->id, 'email' => $order->email]);
            }
        }

        return $made;
    }
}
