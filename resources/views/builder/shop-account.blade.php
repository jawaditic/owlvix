{{-- "My orders" for a shop's customers (ShopAccountController): sign in with an emailed code, then every order. --}}
@extends('builder.layout')

@section('title', 'My orders · '.$store->name)
@section('credit', 'Shop made with the free online store builder by')

@push('styles')
<style>
    .card { max-width: 520px; text-align: left; }
    .card h1, .card > .lead { text-align: center; }
    .orders { list-style: none; margin: 16px 0 0; padding: 0; display: grid; gap: 8px; }
    .orders a { display: grid; grid-template-columns: 1fr auto; gap: 2px 10px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; background: var(--bg); color: var(--text); text-decoration: none; }
    .orders a:hover { border-color: var(--accent); }
    .orders small { color: var(--muted); grid-column: 1 / -1; }
    .tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: .74rem; font-weight: 600; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
    .err { color: var(--error); font-size: .9rem; }
    .link { background: none; border: 0; padding: 0; color: var(--muted); text-decoration: underline; cursor: pointer; font: inherit; font-size: .85rem; }
</style>
@endpush

@section('content')
    <h1>My orders</h1>
    <p class="lead">{{ $store->name }}</p>
    @if ($step === 'orders')
        <p>Signed in as <b>{{ $email }}</b>.</p>
        @if ($orders->isEmpty())
            <p>No orders yet.</p>
        @else
            <ul class="orders">
                @foreach ($orders as $o)
                    <li><a href="{{ $o->receiptUrl() }}"><b>{{ $o->label() }} · {{ $o->money($o->total) }}</b><span class="tag">{{ \App\Models\BuilderOrder::STATUS_LABELS[$o->status] ?? $o->status }}</span>
                        <small>{{ $o->created_at->format('j M Y') }} · {{ collect($o->items)->map(fn ($l) => $l['qty'].' × '.$l['name'])->implode(', ') }}</small></a></li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('shop.account.logout', $store->id) }}" style="text-align:center"><button class="link" type="submit">Sign out</button></form>
    @elseif ($step === 'code')
        <p>If <b>{{ $pending }}</b> has ordered from {{ $store->name }}, we’ve emailed it a 6-digit code.</p>
        <form method="post" action="{{ route('shop.account.verify', $store->id) }}">
            <label for="code">Code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus placeholder="123456">
            @if ($error)<p class="err">{{ $error }}</p>@endif
            <button class="btn" type="submit">See my orders</button>
        </form>
        <form method="post" action="{{ route('shop.account.logout', $store->id) }}"><button class="link" type="submit">Use another email</button></form>
    @else
        <p>Enter the email you ordered with, and we’ll send you a code — no password needed.</p>
        <form method="post" action="{{ route('shop.account.code', $store->id) }}">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required maxlength="150" autocomplete="email" placeholder="you@example.com" autofocus>
            @if ($error)<p class="err">{{ $error }}</p>@endif
            <button class="btn" type="submit">Send my code</button>
        </form>
    @endif
@endsection
