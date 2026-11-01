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
        $catalog = Shop::catalog($data['products'] ?? [], $currency);
        $own = $store ? $store->files()->pluck('id')->all() : [];
        foreach ($catalog as &$product) { // a download can only be one of this shop's own files
            if ($product['file'] && ! in_array($product['file'], $own, true)) {
                $product['file'] = null;
            }
        }
        unset($product);
        $fill = ['name' => trim($data['name']), 'currency' => $currency, 'catalog' => $catalog, 'settings' => Shop::settings($data['settings'] ?? [], $currency)];
        if (! $store) {
            $store = new BuilderStore(['id' => BuilderStore::newId(), 'ref' => $data['ref'] ?? null] + $fill);
            $store->builder_account_id = $account->id;
            $store->save();
        } else {
            $store->update($fill + ['ref' => $store->ref ?? ($data['ref'] ?? null)]);
        }
        // stock of products (or sizes) that are gone is forgotten (under the lock orders use)
        $keys = collect($store->catalog)->flatMap(fn ($p) => array_keys(Shop::keysOf($p)))->all();
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
                fputcsv($out, ['Order', 'Date', 'Status', 'Paid', 'Payment', 'Delivery', 'Name', 'Email', 'Phone', 'Address', 'Country', 'Items', 'Subtotal', 'Discount', 'Code', 'Delivery fee', 'Tax', 'Total', 'Refunded', 'Tracking', 'Note'], escape: '');
                $store->orders()->latest('id')->chunk(200, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        $items = implode('; ', array_map(fn ($l) => $l['qty'].' × '.$l['name'].($l['option'] ? ' ('.$l['option'].')' : ''), $o->items ?? []));
                        fputcsv($out, array_map([BuilderController::class, 'csvCell'], [$o->label(), $o->created_at?->format('Y-m-d H:i'), BuilderOrder::STATUS_LABELS[$o->status] ?? $o->status,
                            $o->isPaid() ? 'Yes' : 'No', BuilderOrder::PAY_LABELS[$o->pay] ?? $o->pay, $o->delivery === 'pickup' ? 'Pick-up' : 'Delivery', $o->name, $o->email, $o->phone,
                            $o->address, $o->country, $items, $o->money($o->subtotal), $o->discount ? $o->money($o->discount) : '', $o->discount_code, $o->money($o->delivery_fee),
                            $o->tax ? $o->money($o->tax).($o->tax_included ? ' (included)' : '') : '', $o->money($o->total), $o->refunded ? $o->money($o->refunded) : '', $o->tracking, $o->note]), escape: '');
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
        $paidNow = false;
        if ($request->has('paid') && $order->pay !== 'card') { // card orders are only marked paid by Stripe
            $paidNow = $request->boolean('paid') && ! $order->paid_at;
            $order->paid_at = $request->boolean('paid') ? ($order->paid_at ?? now()) : null;
        }
        if (($status ?? null) === 'cancelled') {
            Shop::closeStripe($order); // an open Stripe page can't be paid any more
        }
        $order->save();
        if (($status ?? null) === 'cancelled') {
            Shop::giveBack($order); // its stock and its code's use can be used again
        }
        if ($paidNow && $order->status !== 'cancelled') {
            ShopController::paidNow($order, $this->user($request)->email); // gift cards made; downloads and codes emailed
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
     * Give money back: {amount: "12.50"} (empty: everything not refunded yet). Card orders are refunded through Stripe
     * on the owner's account; for cash and bank orders the owner pays it back themselves and this records it.
     * The customer is emailed. A refund doesn't cancel the order or give stock back — that's the status's job.
     */
    public function refundOrder(Request $request, string $id, int $number): JsonResponse
    {
        $store = $this->own($request, $id);
        $order = $store?->orders()->where('number', $number)->first();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }
        if (! $order->isPaid()) {
            return $this->fail($order->pay === 'card' ? 'This order isn’t paid, so there is nothing to refund.' : 'Mark the order as paid first — then you can record a refund.', 422);
        }
        $left = $order->total - $order->refunded;
        $in = $request->input('amount');
        $amount = $in === null || $in === '' ? $left : Shop::minor($in, $order->currency);
        if (! $amount || $amount < 1) {
            return $this->fail('Please enter how much to refund.', 422);
        }
        if ($amount > $left) {
            return $this->fail('You can refund up to '.$order->money($left).'.', 422);
        }
        if ($order->pay === 'card') {
            if (! in_array($order->stripe_mode, Stripe::MODES, true) || ! $order->stripe_session || ! ($account = $store->stripeAccount($order->stripe_mode))) {
                return $this->fail('Stripe isn’t connected any more. Refund it in your Stripe dashboard instead.', 422);
            }
            try {
                $intent = Stripe::sessionOn($order->stripe_mode, $account, $order->stripe_session)['payment_intent'] ?? '';
                if (! $intent) {
                    throw new \RuntimeException('No payment found for this order.');
                }
                Stripe::refund($order->stripe_mode, $account, $intent, $amount);
            } catch (\Throwable $e) {
                report($e);

                return $this->fail('Stripe couldn’t make the refund. Please try again, or refund it in your Stripe dashboard.', 502);
            }
        }
        // counted under a lock, so two clicks can't refund more than was paid
        $updated = \Illuminate\Support\Facades\DB::table('builder_orders')->where('id', $order->id)->whereRaw('refunded + ? <= total', [$amount])
            ->update(['refunded' => \Illuminate\Support\Facades\DB::raw('refunded + '.(int) $amount), 'refunded_at' => now(), 'updated_at' => now()]);
        if (! $updated) {
            return $this->fail('This order was refunded meanwhile. Please reload.', 409);
        }
        $order->refresh();
        if (ShopController::mayEmailCustomer($store)) {
            $owner = $this->user($request)->email;
            \Illuminate\Support\defer(function () use ($order, $owner, $amount) {
                try {
                    \Illuminate\Support\Facades\Mail::to($order->email)->send(new \App\Mail\BuilderOrderRefunded($order, $owner, $amount));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json(['ok' => true, 'order' => $order->toApi()]);
    }

    /**
     * Sales for the last 7, 30 or 90 days: takings (orders not cancelled or unpaid, less refunds), orders, average
     * order, the change from the period before, takings per day, best sellers and what is running low.
     */
    public function sales(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $from = now()->startOfDay()->subDays($days - 1);
        $counted = fn ($q) => $q->whereIn('status', ['new', 'shipped', 'done']);
        $orders = $counted($store->orders()->where('created_at', '>=', $from))->get(['total', 'refunded', 'items', 'pay', 'created_at']);
        $before = $counted($store->orders()->whereBetween('created_at', [$from->copy()->subDays($days), $from]))->get(['total', 'refunded']);
        $takings = fn ($list) => $list->sum(fn ($o) => $o->total - $o->refunded);
        $money = fn (int $m) => Shop::format($m, $store->currency);
        $now = $takings($orders);
        $prev = $takings($before);

        $perDay = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $perDay[$d->format('Y-m-d')] = 0;
        }
        foreach ($orders as $o) {
            $perDay[$o->created_at->format('Y-m-d')] = ($perDay[$o->created_at->format('Y-m-d')] ?? 0) + $o->total - $o->refunded;
        }
        $best = [];
        foreach ($orders as $o) {
            foreach ($o->items ?? [] as $l) {
                $k = $l['name'].($l['option'] ? ' ('.$l['option'].')' : '');
                $best[$k] = ['name' => $k, 'qty' => ($best[$k]['qty'] ?? 0) + $l['qty'], 'sales' => ($best[$k]['sales'] ?? 0) + $l['price'] * $l['qty']];
            }
        }
        usort($best, fn ($a, $b) => $b['qty'] <=> $a['qty'] ?: $b['sales'] <=> $a['sales']);
        // tracked stock at 5 or less, with the product's name
        $names = [];
        foreach ($store->catalog ?? [] as $p) {
            $names += Shop::keysOf($p + ['vars2' => []]);
        }
        $low = collect($store->stock ?? [])->filter(fn ($n, $k) => $n <= 5 && isset($names[$k]))->sort()
            ->map(fn ($n, $k) => ['name' => $names[$k], 'left' => $n])->values()->take(8);

        return response()->json(['ok' => true, 'days' => $days, 'currency' => $store->currency,
            'takings' => $money($now), 'orders' => $orders->count(), 'average' => $orders->count() ? $money(intdiv($now, $orders->count())) : $money(0),
            'change' => $prev > 0 ? (int) round(($now - $prev) / $prev * 100) : null,
            'per_day' => collect($perDay)->map(fn ($m, $day) => ['day' => $day, 'value' => Shop::zeroDecimal($store->currency) ? $m : $m / 100, 'label' => $money($m)])->values(),
            'best' => array_map(fn ($b) => ['name' => $b['name'], 'qty' => $b['qty'], 'sales' => $money($b['sales'])], array_slice($best, 0, 5)),
            'low' => $low,
            'by_pay' => $orders->groupBy('pay')->map->count()]);
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
                if (! is_string($key) || ! preg_match('/^[a-z0-9]{1,24}(:[a-z0-9]{1,24}(_[a-z0-9]{1,24})?)?$/i', $key)) {
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

    /* ---------- files sold as digital downloads */

    public function files(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);

        return $store ? response()->json(['ok' => true, 'files' => $store->files()->latest()->get()->map->toApi()->values()]) : $this->fail('Shop not found.', 404);
    }

    /** Upload a file to sell (multipart "file"). Kept privately; only buyers of a paid order can download it. */
    public function uploadFile(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return $this->fail('Please choose a file.', 422);
        }
        if ($file->getSize() > \App\Models\BuilderShopFile::MAX_BYTES) {
            return $this->fail('Files can be up to 25 MB.', 422);
        }
        if ($store->files()->count() >= \App\Models\BuilderShopFile::MAX_PER_STORE) {
            return $this->fail('A shop can have up to '.\App\Models\BuilderShopFile::MAX_PER_STORE.' files. Delete one first.', 422);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['php', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'exe', 'sh', 'bat', 'cmd', 'msi', 'jar', 'app', 'dmg'], true)) {
            return $this->fail('That kind of file can’t be sold here. Zip it first, or use PDF, images, audio, video or documents.', 422);
        }
        $name = trim(preg_replace('/[^\w .()\-]+/u', '', $file->getClientOriginalName())) ?: 'download'.($ext ? '.'.$ext : '');
        $item = $store->files()->make(['id' => \App\Models\BuilderShopFile::newId(), 'name' => mb_substr($name, 0, 150), 'size' => $file->getSize(),
            'mime' => mb_substr($file->getMimeType() ?: 'application/octet-stream', 0, 100)]);
        \App\Models\BuilderShopFile::disk()->putFileAs('shop-files/'.$store->id, $file, $item->id);
        $item->save();

        return response()->json(['ok' => true, 'file' => $item->toApi()]);
    }

    public function deleteFile(Request $request, string $id, string $file): JsonResponse
    {
        $store = $this->own($request, $id);
        $item = $store?->files()->find($file);
        if (! $item) {
            return $this->fail('File not found.', 404);
        }
        \App\Models\BuilderShopFile::disk()->delete($item->path());
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /* ---------- reviews and gift cards */

    public function reviews(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);
        if (! $store) {
            return $this->fail('Shop not found.', 404);
        }
        $names = collect($store->catalog ?? [])->pluck('name', 'id');

        return response()->json(['ok' => true, 'reviews' => $store->reviews()->latest()->take(200)->get()->map(fn ($r) => [
            'id' => $r->id, 'product' => $names[$r->product_id] ?? 'A product that’s gone', 'name' => $r->name, 'rating' => $r->rating,
            'text' => (string) $r->text, 'hidden' => $r->hidden, 'date' => $r->created_at?->toIso8601String()])->values()]);
    }

    /** Hide or show a review on the shop ({hidden: true|false}), or delete it ({delete: true}). */
    public function updateReview(Request $request, string $id, int $review): JsonResponse
    {
        $store = $this->own($request, $id);
        $item = $store?->reviews()->find($review);
        if (! $item) {
            return $this->fail('Review not found.', 404);
        }
        if ($request->boolean('delete')) {
            $item->delete();
        } else {
            $item->update(['hidden' => $request->boolean('hidden')]);
        }

        return response()->json(['ok' => true]);
    }

    public function giftCards(Request $request, string $id): JsonResponse
    {
        $store = $this->own($request, $id);

        return $store ? response()->json(['ok' => true, 'cards' => $store->giftCards()->latest()->take(200)->get()->map->toApi()->values()]) : $this->fail('Shop not found.', 404);
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
        \App\Models\BuilderShopFile::disk()->deleteDirectory('shop-files/'.$store->id); // its files for sale go too
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
