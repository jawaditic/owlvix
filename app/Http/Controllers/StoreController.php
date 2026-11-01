<?php

namespace App\Http\Controllers;

use App\Models\BuilderAccount;
use App\Models\BuilderOrder;
use App\Models\BuilderStore;
use App\Support\Shop;
use App\Support\Stripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * JSON API for the online store builder's editor (signed in with a builder account): switch on checkout, keep the
 * shop's products and settings up to date, see and update orders, and connect Stripe for card payments.
 * Every answer is {ok: true, ...} or {ok: false, error}.
 */
class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'stores' => $this->user($request)->stores()->oldest()->get()->map->toApi()->values()]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);

        return $store ? response()->json(['ok' => true, 'store' => $store->toApi()]) : $this->fail('Shop not found.', 404);
    }

    /**
     * Create the shop (first time: checkout is switched on) or update it. One shop per editor project (ref).
     * The editor sends the shop's products and settings before every publish and download.
     */
    public function save(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id' => ['nullable', 'string', 'regex:/^[a-z0-9]{16}$/'],
            'ref' => ['nullable', 'string', 'regex:/^[a-z0-9]{6,20}$/'],
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'in:'.implode(',', Stripe::CURRENCIES)],
            'products' => ['nullable', 'array', 'max:'.Shop::MAX_PRODUCTS],
            'settings' => ['nullable', 'array'],
        ], [
            'name.required' => 'Please give your shop a name first.', 'name.max' => 'Please use a shop name under 100 characters.',
            'currency.in' => 'Please choose one of the currencies in the list.',
            'products.max' => 'A shop can have up to '.Shop::MAX_PRODUCTS.' products.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }
        $data = $v->validated();
        $account = $this->user($request);
        $store = ! empty($data['id']) ? BuilderStore::find($data['id']) : null;
        if (! empty($data['id']) && ! $store) {
            return $this->fail('This shop was deleted. Switch checkout on again.', 404);
        }
        if ($store && (int) $store->builder_account_id !== (int) $account->id) {
            return $this->fail('This shop belongs to another account. Switch checkout on again.', 403);
        }
        $store ??= ! empty($data['ref']) ? $account->stores()->where('ref', $data['ref'])->first() : null;
        if (! $store && $account->stores()->count() >= BuilderStore::MAX_PER_ACCOUNT) {
            return $this->fail('You can have up to '.BuilderStore::MAX_PER_ACCOUNT.' shops.', 422);
        }
        $currency = $data['currency']; // orders keep their own currency
        if ($store && $store->currency !== $currency) {
            // amounts in codes were in the old currency: those codes are switched off until the owner checks them
            $store->discounts()->where(fn ($q) => $q->where('kind', 'amount')->orWhere('min_total', '>', 0))->update(['active' => false]);
        }
        $fill = ['name' => trim($data['name']), 'currency' => $currency,
            'catalog' => Shop::catalog($data['products'] ?? [], $currency), 'settings' => Shop::settings($data['settings'] ?? [], $currency)];
        if (! $store) {
            $store = new BuilderStore(['id' => BuilderStore::newId(), 'ref' => $data['ref'] ?? null] + $fill);
            $store->builder_account_id = $account->id;
            $store->save();
        } else {
            $store->update($fill + ['ref' => $store->ref ?? ($data['ref'] ?? null)]);
        }
        // stock of products (or sizes) that are gone is forgotten (under the lock orders use)
        $keys = collect($store->catalog)->flatMap(fn ($p) => $p['vars'] ? array_map(fn ($v) => Shop::key($p['id'], $v['id']), $p['vars']) : [$p['id']])->all();
        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $keys) {
            $locked = BuilderStore::whereKey($store->id)->lockForUpdate()->first();
            if ($locked->stock) {
                $locked->stock = array_intersect_key($locked->stock, array_flip($keys)) ?: null;
                $locked->save();
            }
        });
        $store->refresh();

        return response()->json(['ok' => true, 'store' => $store->toApi()]);
    }

    /** The shop's orders, newest first (?status=new…, ?format=csv for all of them). */
    public function orders(Request $request, string $id)
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        if ($request->query('format') === 'csv') {
            $name = 'orders-'.Str::slug($store->name ?: 'shop').'-'.now()->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($store) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
                fputcsv($out, ['Order', 'Date', 'Status', 'Paid', 'Payment', 'Delivery', 'Name', 'Email', 'Phone', 'Address', 'Country', 'Items', 'Subtotal', 'Discount', 'Code', 'Delivery fee', 'Tax', 'Total', 'Tracking', 'Note'], escape: '');
                $store->orders()->latest('id')->chunk(200, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        $items = implode('; ', array_map(fn ($l) => $l['qty'].' × '.$l['name'].($l['option'] ? ' ('.$l['option'].')' : ''), $o->items ?? []));
                        fputcsv($out, array_map([BuilderController::class, 'csvCell'], [$o->label(), $o->created_at?->format('Y-m-d H:i'), BuilderOrder::STATUS_LABELS[$o->status] ?? $o->status,
                            $o->isPaid() ? 'Yes' : 'No', BuilderOrder::PAY_LABELS[$o->pay] ?? $o->pay, $o->delivery === 'pickup' ? 'Pick-up' : 'Delivery', $o->name, $o->email, $o->phone,
                            $o->address, $o->country, $items, $o->money($o->subtotal), $o->discount ? $o->money($o->discount) : '', $o->discount_code, $o->money($o->delivery_fee),
                            $o->tax ? $o->money($o->tax).($o->tax_included ? ' (included)' : '') : '', $o->money($o->total), $o->tracking, $o->note]), escape: '');
                    }
                });
                fclose($out);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        Shop::releaseExpired($store);
        $status = $request->query('status');
        $orders = $store->orders()->when(in_array($status, BuilderOrder::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest('id')->take(100)->get();
        $counts = $store->orders()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return response()->json(['ok' => true, 'store' => $store->toApi(), 'orders' => $orders->map->toApi()->values(),
            'counts' => (object) $counts->map(fn ($n) => (int) $n)->all(), 'total' => $store->orders_count]);
    }

    /** Move an order along (new → sent → completed, or cancelled), or mark a cash / bank order as paid. */
    public function updateOrder(Request $request, string $id, int $number): JsonResponse
    {
        $store = $this->own($request, $id);
        $order = $store?->orders()->where('number', $number)->first();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }
        $status = $request->input('status');
        if ($status !== null) {
            if (! in_array($status, BuilderOrder::OWNER_STATUSES, true)) {
                return $this->fail('Unknown order status.', 422);
            }
            if ($order->status === 'unpaid' && $status !== 'cancelled') {
                return $this->fail('This card order isn’t paid yet. Press “Check payment” first.', 422);
            }
            if ($order->status === 'cancelled' && $status !== 'cancelled') {
                return $this->fail('A cancelled order can’t be opened again. Ask the customer to order again.', 422);
            }
            $tracking = $request->input('tracking');
            if (is_string($tracking) && mb_strlen(trim($tracking)) > 500) {
                return $this->fail('Please keep the tracking number or link under 500 characters.', 422);
            }
            $shipNow = $status === 'shipped' && ! $order->shipped_at;
            if ($status === 'shipped' && is_string($tracking)) {
                $order->tracking = trim($tracking) ?: null;
            }
            if ($shipNow) {
                $order->shipped_at = now();
            }
            $order->status = $status;
        }
        if ($request->has('paid') && $order->pay !== 'card') { // card orders are only marked paid by Stripe
            $order->paid_at = $request->boolean('paid') ? ($order->paid_at ?? now()) : null;
        }
        if (($status ?? null) === 'cancelled') {
            Shop::closeStripe($order); // an open Stripe page can't be paid any more
        }
        $order->save();
        if (($status ?? null) === 'cancelled') {
            Shop::giveBack($order); // its stock and its code's use can be used again
        }
        if (! empty($shipNow) && $request->boolean('notify', true) && ShopController::mayEmailCustomer($store)) { // "Your order is on its way"
            $owner = $this->user($request)->email;
            \Illuminate\Support\defer(function () use ($order, $owner) {
                try {
                    \Illuminate\Support\Facades\Mail::to($order->email)->send(new \App\Mail\BuilderOrderShipped($order, $owner));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json(['ok' => true, 'order' => $order->fresh()->toApi()]);
    }

    /**
     * Set or stop tracking stock: {stock: {"product" or "product:variant": count, or null to stop}}. Kept apart from
     * the products, so publishing again never undoes what orders took.
     */
    public function stock(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $in = $request->input('stock');
        if (! is_array($in) || count($in) > 500) {
            return $this->fail('Nothing to save.', 422);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $in) {
            $locked = BuilderStore::whereKey($store->id)->lockForUpdate()->first();
            $stock = $locked->stock ?? [];
            foreach ($in as $key => $qty) {
                if (! is_string($key) || ! preg_match('/^[a-z0-9]{1,24}(:[a-z0-9]{1,24})?$/i', $key)) {
                    continue;
                }
                if ($qty === null || $qty === '') {
                    unset($stock[$key]);
                } elseif (is_numeric($qty)) {
                    $stock[$key] = max(0, min(1_000_000, (int) $qty));
                }
            }
            $locked->stock = $stock ?: null;
            $locked->save();
        });

        return response()->json(['ok' => true, 'stock' => (object) ($store->fresh()->stock ?? [])]);
    }

    public function discounts(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);

        return $store ? response()->json(['ok' => true, 'discounts' => $store->discounts()->orderBy('code')->get()->map->toApi($store->currency)->values()])
            : $this->fail('Shop not found.', 404);
    }

    /** Add or change a discount code: {id?, code, kind, value, min, ends, max_uses, active}. */
    public function saveDiscount(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $v = Validator::make($request->all(), [
            'id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{3,40}$/'],
            'kind' => ['required', 'in:'.implode(',', \App\Models\BuilderDiscount::KINDS)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'ends' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Please type a code, like SAVE10.', 'code.regex' => 'Codes use 3–40 letters, numbers, dashes or underscores (no spaces).',
            'value.numeric' => 'Please enter the discount as a number.', 'min.numeric' => 'Please enter the minimum order as a number.',
            'ends.date' => 'Please pick an end date.', 'max_uses.integer' => 'Please enter how many times it can be used, as a whole number.',
        ]);
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }
        $d = $v->validated();
        $value = match ($d['kind']) {
            'percent' => (int) round((float) ($d['value'] ?? 0)),
            'amount' => Shop::minor($d['value'] ?? 0, $store->currency) ?? 0,
            default => 0,
        };
        if ($d['kind'] === 'percent' && ($value < 1 || $value > 100)) {
            return $this->fail('A percent discount is between 1 and 100.', 422);
        }
        if ($d['kind'] === 'amount' && $value < 1) {
            return $this->fail('Please enter how much the code takes off.', 422);
        }
        $code = strtoupper($d['code']);
        $item = ! empty($d['id']) ? $store->discounts()->find($d['id']) : null;
        if (! empty($d['id']) && ! $item) {
            return $this->fail('Discount not found.', 404);
        }
        if ($store->discounts()->where('code', $code)->when($item, fn ($q) => $q->whereKeyNot($item->id))->exists()) {
            return $this->fail('You already have the code '.$code.'.', 422);
        }
        if (! $item && $store->discounts()->count() >= \App\Models\BuilderDiscount::MAX_PER_STORE) {
            return $this->fail('A shop can have up to '.\App\Models\BuilderDiscount::MAX_PER_STORE.' discount codes.', 422);
        }
        $item ??= $store->discounts()->make();
        $item->fill(['code' => $code, 'kind' => $d['kind'], 'value' => $value, 'min_total' => Shop::minor($d['min'] ?? 0, $store->currency) ?? 0,
            'ends_at' => ! empty($d['ends']) ? \Illuminate\Support\Carbon::parse($d['ends'])->endOfDay() : null,
            'max_uses' => $d['max_uses'] ?? null, 'active' => $d['active'] ?? true])->save();

        return response()->json(['ok' => true, 'discount' => $item->toApi($store->currency)]);
    }

    public function deleteDiscount(Request $request, string $id, int $discount): JsonResponse
    {
        $store = $this->own($request, $id);
        $item = $store?->discounts()->find($discount);
        if (! $item) {
            return $this->fail('Discount not found.', 404);
        }
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /** Ask Stripe again whether a card order was paid (the customer may have closed the page before coming back). */
    public function checkOrder(Request $request, string $id, int $number): JsonResponse
    {
        $store = $this->own($request, $id);
        $order = $store?->orders()->where('number', $number)->first();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }
        $paid = ShopController::checkPaid($order);

        return response()->json(['ok' => true, 'paid' => $paid, 'order' => $order->fresh()->toApi()]);
    }

    public function delete(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        foreach (Stripe::MODES as $mode) {
            if ($account = $store->stripeAccount($mode)) {
                try {
                    Stripe::disconnect($mode, $account);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        $store->delete();

        return response()->json(['ok' => true]);
    }

    /** Stripe's page where the owner connects their own Stripe account (test or real payments). */
    public function stripeConnect(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $mode = $request->input('mode') === 'live' ? 'live' : 'test';
        if (! Stripe::available($mode)) {
            return $this->fail($mode === 'live' ? 'Real card payments aren’t available yet.' : 'Test payments aren’t available yet.', 503);
        }
        $state = Str::random(40);
        // the same callback as the app builder: it tells the two apart by the state
        Cache::put('app-stripe-state:'.$state, ['store' => $store->id, 'mode' => $mode], now()->addMinutes(30));

        return response()->json(['ok' => true, 'url' => Stripe::connectUrl($mode, $state)]);
    }

    public function stripeDisconnect(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $mode = $request->input('mode') === 'live' ? 'live' : 'test';
        $column = $mode === 'live' ? 'stripe_live_account' : 'stripe_test_account';
        if ($account = $store->{$column}) {
            try {
                Stripe::disconnect($mode, $account);
            } catch (\Throwable $e) {
                report($e); // forgotten here either way; the owner can also remove owlvix in their Stripe dashboard
            }
        }
        $store->forceFill([$column => null])->save();

        return response()->json(['ok' => true, 'store' => $store->fresh()->toApi()]);
    }

    /** Back from Stripe's connect page (called by AppBuilderController::stripeCallback for shop states). */
    public static function stripeConnected(array $state, string $account): string
    {
        $store = BuilderStore::find($state['store']);
        if (! $store) {
            return 'expired';
        }
        $store->forceFill([$state['mode'] === 'live' ? 'stripe_live_account' : 'stripe_test_account' => $account])->save();

        return 'connected-'.$state['mode'];
    }

    private function own(Request $request, mixed $id): ?BuilderStore
    {
        return is_string($id) ? $this->user($request)->stores()->whereKey($id)->first() : null;
    }

    private function user(Request $request): BuilderAccount
    {
        return $request->attributes->get('builder_account');
    }

    private function fail(string $error, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error], $status);
    }
}
