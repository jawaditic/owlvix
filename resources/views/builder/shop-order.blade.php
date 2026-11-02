{{-- A customer's order in a shop made with the online store builder (/shop/order/{token}): the private receipt,
     bank transfer details, and "Pay now" for card orders that aren't paid yet. No scripts. --}}
@extends('builder.layout')
@php
    $s = $store->settings ?? [];
    $paid = $order->isPaid();
    $waiting = $order->pay === 'card' && ! $paid && $order->status === 'unpaid';
    $back = $order->back ? $order->back.'#ordered' : null;
    $wa = $s['whatsapp'] ?? '';
@endphp

@section('title', $waiting ? 'Finish your payment' : 'Order '.$order->label())
@section('credit', 'Shop made with the free online store builder by')

@push('styles')
<style>
    .card { max-width: 520px; text-align: left; }
    .card h1, .card > p.lead { text-align: center; }
    .icon { margin-bottom: 14px; }
    .lines { width: 100%; border-collapse: collapse; margin: 18px 0 8px; font-size: .95rem; }
    .lines td { padding: 9px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    .lines td:last-child { text-align: right; white-space: nowrap; padding-left: 12px; }
    .lines small { display: block; color: var(--muted); }
    .lines tr.sum td { border: 0; padding: 4px 0; color: var(--muted); }
    .lines tr.total td { border: 0; padding-top: 8px; font-weight: 700; font-size: 1.1rem; color: var(--text); }
    .box { margin: 16px 0 0; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; background: var(--bg); }
    .box b { display: block; margin-bottom: 4px; }
    .box p { margin: 0; white-space: pre-wrap; color: var(--text); }
    .tag { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
    .tag--warn { background: color-mix(in srgb, #d97706 16%, transparent); color: #b45309; }
    .notice { margin: 0 0 14px; padding: 12px 14px; border-radius: 12px; background: color-mix(in srgb, var(--error) 10%, transparent); color: var(--error); font-size: .92rem; }
    .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 20px; }
    .actions form { margin: 0; }
    .btn--ghost { background: transparent; color: var(--text); border: 1px solid var(--line); }
    .notice--ok { background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent); }
    .box .small, .small { font-size: .82rem; color: var(--muted); }
    .box .dl { color: var(--accent); font-weight: 600; text-decoration: none; }
    .gift span { font: 700 1rem ui-monospace, Menlo, monospace; letter-spacing: 1px; margin-right: 6px; color: var(--text); }
    .review { margin: 12px 0 0; padding-top: 12px; border-top: 1px solid var(--line); }
    .review fieldset { border: 0; margin: 0; padding: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .review legend { float: left; font-weight: 600; font-size: .92rem; padding: 0; }
    .review textarea { min-height: 60px; margin: 8px 0; }
    .review .btn { margin-top: 0; padding: 8px 16px; font-size: .9rem; }
    .stars { display: inline-flex; flex-direction: row-reverse; }
    .stars input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .stars label { margin: 0; padding: 0 2px; font-size: 1.6rem; line-height: 1; color: var(--line); cursor: pointer; }
    .stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: #f59e0b; }
    .stars input:focus-visible + label { outline: 2px solid var(--accent); border-radius: 4px; }
</style>
@endpush

@section('content')
    @if ($result === 'error')
        <p class="notice">Card payment couldn’t be started right now. Your order is saved — please try again in a minute.</p>
    @elseif ($result === 'off')
        <p class="notice">This shop isn’t taking card payments at the moment. Please contact {{ $store->name }} about your order.</p>
    @elseif ($result === 'expired' || ($order->status === 'cancelled' && $order->pay === 'card' && ! $paid))
        <p class="notice">This order wasn’t paid in time, so it was cancelled and nothing was charged. Please go back to the shop to order again.</p>
    @elseif ($result === 'downloads')
        <p class="notice">This order’s downloads have all been used. Please contact {{ $store->name }} if you need the file again.</p>
    @elseif ($result === 'review-error')
        <p class="notice">Your review wasn’t saved. Please pick 1 to 5 stars and keep links out of it.</p>
    @elseif ($result === 'reviewed')
        <p class="notice notice--ok">Thanks for your review!</p>
    @elseif ($result === 'cancelled' && $waiting)
        <p class="notice">The payment wasn’t finished, so nothing was charged. You can try again below.</p>
    @endif

    @if ($waiting)
        <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
        <h1>Finish your payment</h1>
        <p class="lead">Your order {{ $order->label() }} with {{ $store->name }} is saved. It’s sent to the shop once it’s paid.</p>
    @else
        <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1>{{ $order->status === 'cancelled' ? 'Order '.$order->label().' was cancelled' : ($order->shipped_at ? 'Your order is on its way' : 'Thank you, '.$order->name.'!') }}</h1>
        <p class="lead">{{ $order->status === 'cancelled' ? 'Please contact '.$store->name.' if you have questions.' : (($s['thanks'] ?? '') ?: $store->name.' has your order '.$order->label().'.') }}</p>
    @endif

    <table class="lines">
        @foreach ($order->items as $line)
            <tr><td>{{ $line['qty'] }} × {{ $line['name'] }}@if ($line['option'])<small>{{ $line['option'] }}</small>@endif</td><td>{{ $order->money($line['price'] * $line['qty']) }}</td></tr>
        @endforeach
        <tr class="sum"><td>Subtotal</td><td>{{ $order->money($order->subtotal) }}</td></tr>
        @if ($order->discount)<tr class="sum"><td>Discount{{ $order->discount_code ? ' ('.$order->discount_code.')' : '' }}</td><td>−{{ $order->money($order->discount) }}</td></tr>@endif
        @if ($order->delivery !== 'digital')<tr class="sum"><td>{{ $order->delivery === 'pickup' ? 'Pick-up' : 'Delivery' }}</td><td>{{ $order->delivery_fee ? $order->money($order->delivery_fee) : 'Free' }}</td></tr>@endif
        @if ($order->tax && ! $order->tax_included)<tr class="sum"><td>{{ $s['tax_label'] ?? 'Tax' }}</td><td>{{ $order->money($order->tax) }}</td></tr>@endif
        @if ($order->gift_amount)<tr class="sum"><td>Gift card</td><td>−{{ $order->money($order->gift_amount) }}</td></tr>@endif
        <tr class="total"><td>Total</td><td>{{ $order->money($order->total) }}</td></tr>
        @if ($order->refunded)<tr class="sum"><td>Refunded{{ $order->refunded_at ? ' on '.$order->refunded_at->format('j M') : '' }}</td><td>−{{ $order->money($order->refunded) }}</td></tr>@endif
        @if ($order->tax && $order->tax_included)<tr class="sum"><td colspan="2" style="text-align:right">includes {{ $order->money($order->tax) }} {{ $s['tax_label'] ?? 'tax' }}</td></tr>@endif
    </table>

    <div class="box">
        <b>Payment @if ($paid)<span class="tag">Paid</span>@elseif ($order->pay !== 'cod')<span class="tag tag--warn">Not paid yet</span>@endif</b>
        <p>{{ \App\Models\BuilderOrder::PAY_LABELS[$order->pay] ?? $order->pay }}@if ($order->stripe_mode === 'test') (test — no real money)@endif</p>
    </div>
    @if ($order->pay === 'bank' && ! $paid && filled($s['bank_text'] ?? null))
        <div class="box"><b>How to pay by bank transfer</b><p>{{ $s['bank_text'] }}

Please use {{ $order->label() }} as the payment reference.</p></div>
    @endif
    @if ($order->delivery !== 'digital')
    <div class="box">
        <b>{{ $order->delivery === 'pickup' ? 'Pick-up' : 'Delivery to' }}</b>
        <p>{{ $order->delivery === 'pickup' ? (($s['pickup_text'] ?? '') ?: 'The shop will tell you when it’s ready to collect.') : $order->address.($order->countryName() ? "\n".$order->countryName() : '') }}</p>
    </div>
    @endif
    {{-- downloads and gift cards: ready once paid --}}
    @php($files = collect($order->digitalLines())->where('kind', 'digital'))
    @php($cards = $paid ? $store->giftCards()->where('builder_order_id', $order->id)->get() : collect())
    @if ($files->isNotEmpty() && $order->status !== 'cancelled')
        <div class="box"><b>Your downloads</b>
            @if ($paid)
                @foreach ($files as $f)
                    <p>@if ($f['file'])<a class="dl" href="{{ route('shop.download', [$order->token, $f['id']]) }}">{!! \App\Support\Icons::i('download') !!} {{ $f['name'] }}</a>@else{{ $f['name'] }} — {{ $store->name }} will send you the file.@endif</p>
                @endforeach
                <p class="small">You can download each file up to {{ \App\Models\BuilderShopFile::MAX_DOWNLOADS }} times.</p>
            @else
                <p>Ready here once your payment is received.</p>
            @endif
        </div>
    @endif
    @if ($cards->isNotEmpty())
        <div class="box"><b>Your gift card{{ $cards->count() > 1 ? 's' : '' }}</b>
            @foreach ($cards as $card)<p class="gift"><span>{{ $card->code }}</span> {{ \App\Support\Shop::format($card->balance, $card->currency) }} left</p>@endforeach
            <p class="small">Type the code in the discount box at checkout. Whatever isn’t spent stays on the card.</p>
        </div>
    @elseif ($order->status !== 'cancelled' && collect($order->digitalLines())->contains('kind', 'gift'))
        <div class="box"><b>Your gift card</b><p>Its code appears here (and in an email) once your payment is received.</p></div>
    @endif
    @if ($order->shipped_at)
        <div class="box"><b>On its way <span class="tag">Sent {{ $order->shipped_at->format('j M') }}</span></b>
            <p>@if ($order->trackingUrl())<a href="{{ $order->trackingUrl() }}" rel="noopener nofollow" style="color:var(--accent);font-weight:600;">Track your parcel</a>@elseif ($order->tracking)Tracking: {{ $order->tracking }}@else{{ $store->name }} has sent your order.@endif</p></div>
    @endif

    @if (\App\Http\Controllers\ShopController::canReview($order))
        @php($mine = $store->reviews()->where('builder_order_id', $order->id)->get()->keyBy('product_id'))
        <div class="box" id="review"><b>How did you like it?</b>
            <p class="small">Your review (first name only) helps other shoppers at {{ rtrim($store->name, '.') }}.</p>
            @foreach (collect($order->items)->unique('id') as $line)
                @php($r = $mine[$line['id']] ?? null)
                <form method="post" action="{{ route('shop.review', $order->token) }}" class="review">
                    <input type="hidden" name="product" value="{{ $line['id'] }}">
                    <fieldset><legend>{{ $line['name'] }}</legend>
                        <span class="stars">@for ($i = 5; $i >= 1; $i--)<input type="radio" id="r{{ $line['id'] }}{{ $i }}" name="rating" value="{{ $i }}" @checked(($r->rating ?? 0) === $i) required><label for="r{{ $line['id'] }}{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">{!! \App\Support\Icons::i('star-solid') !!}</label>@endfor</span>
                    </fieldset>
                    <textarea name="text" rows="2" maxlength="1500" placeholder="What did you think? (optional)">{{ $r->text ?? '' }}</textarea>
                    <button class="btn btn--ghost" type="submit">{{ $r ? 'Update my review' : 'Post my review' }}</button>
                </form>
            @endforeach
        </div>
    @endif

    <div class="actions">
        @if ($waiting)
            <form method="post" action="{{ route('shop.pay', $order->token) }}"><button class="btn" type="submit">Pay {{ $order->money($order->total) }} now</button></form>
        @endif
        @if ($wa && ! $waiting)
            <a class="btn btn--ghost" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hi! About my order '.$order->label().' ('.$order->name.')') }}" rel="noopener">{!! \App\Support\Icons::i('whatsapp') !!} Message the shop</a>
        @endif
        @if ($back)
            <a class="btn {{ $waiting ? 'btn--ghost' : '' }}" href="{{ $back }}">Back to {{ $store->name }}</a>
        @endif
    </div>
    <p style="margin-top:18px;font-size:.82rem;text-align:center;"><a href="{{ route('shop.account', $store->id) }}" style="color:inherit">See all my orders from {{ $store->name }}</a> · Keep this page’s link to see your order again. {{ $store->name }} is responsible for your order — Owlvix only runs the checkout.</p>
@endsection
