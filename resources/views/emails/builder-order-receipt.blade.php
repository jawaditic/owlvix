@extends('emails.layout')
@php($s = $order->store->settings ?? [])

@section('preheader', 'Thanks for your order! Total '.$order->money($order->total).'.')
@section('eyebrow', 'Order received ✅')
@section('headline', 'Thank you, '.$order->name.'!')
@section('subline', $order->store->name.' has your order '.$order->label().'.')

@section('body')
    @include('emails.partials.order-items', ['order' => $order])

    <p style="margin:20px 0 6px;font-size:14px;color:#0f172a;">
        <b>Payment:</b> {{ \App\Models\BuilderOrder::PAY_LABELS[$order->pay] ?? $order->pay }}{{ $order->isPaid() ? ' — paid, thank you' : '' }}<br>
        @if ($order->delivery === 'digital')
            <b>Delivery:</b> nothing to post — downloads and gift cards come by email and on your order page.
        @else
        <b>{{ $order->delivery === 'pickup' ? 'Pick-up' : 'Delivery to' }}:</b>
        @if ($order->delivery === 'pickup')
            {{ $s['pickup_text'] ?? '' ?: 'The shop will tell you when it is ready to collect.' }}
        @else
            {!! nl2br(e($order->address)) !!}@if ($order->countryName())<br>{{ $order->countryName() }}@endif
        @endif
        @endif
    </p>
    @if ($order->pay === 'bank')
        <p style="margin:18px 0 0;font-size:14px;color:#0f172a;">The shop’s bank details are on your order page below. Please use <b>{{ $order->label() }}</b> as the payment reference.</p>
    @endif

    @include('emails.partials.order-extras', ['order' => $order])
    @if (! $order->isPaid() && $order->digitalLines())
        <p style="margin:18px 0 0;font-size:14px;color:#0f172a;">Your {{ collect($order->digitalLines())->contains('kind', 'gift') ? 'gift card codes and downloads arrive' : 'downloads are ready' }} once your payment is received.</p>
    @endif
    <p style="margin:24px 0 0;"><a href="{{ $order->receiptUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See your order</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Questions about your order? Just reply to this email to reach {{ $order->store->name }}.</p>
@endsection

@section('footer_note', 'You get this because you placed an order with '.$order->store->name.', a shop made with Owlvix. Owlvix does not sell these products — please contact the shop about your order.')
