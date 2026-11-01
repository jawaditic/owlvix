@extends('emails.layout')

@section('preheader', 'Good news — '.$order->store->name.' has sent your order '.$order->label().'.')
@section('eyebrow', 'On its way 🚚')
@section('headline', 'Your order is on its way')
@section('subline', $order->store->name.' has sent your order '.$order->label().'.')

@section('body')
    @if ($order->tracking)
        <p style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Tracking</p>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;color:#1e293b;line-height:1.6;word-break:break-all;">
            @if ($order->trackingUrl())<a href="{{ $order->trackingUrl() }}" style="color:#17a85f;font-weight:600;">Track your parcel</a>@else{{ $order->tracking }}@endif
        </div>
    @endif
    <div style="margin-top:18px;">@include('emails.partials.order-items', ['order' => $order])</div>
    @if ($order->delivery === 'delivery' && $order->address)
        <p style="margin:18px 0 0;font-size:14px;color:#0f172a;"><b>Delivery to:</b><br>{!! nl2br(e($order->address)) !!}@if ($order->countryName())<br>{{ $order->countryName() }}@endif</p>
    @endif
    <p style="margin:24px 0 0;"><a href="{{ $order->receiptUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See your order</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Questions? Just reply to this email to reach {{ $order->store->name }}.</p>
@endsection

@section('footer_note', 'You get this because you placed an order with '.$order->store->name.', a shop made with Owlvix. Owlvix does not sell these products — please contact the shop about your order.')
