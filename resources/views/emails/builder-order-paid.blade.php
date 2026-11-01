@extends('emails.layout')

@section('preheader', 'Payment received — your order '.$order->label().' is ready.')
@section('eyebrow', 'Payment received ✅')
@section('headline', 'Your order is ready')
@section('subline', $order->store->name.' has received your payment for order '.$order->label().'.')

@section('body')
    @include('emails.partials.order-extras', ['order' => $order])
    <p style="margin:24px 0 0;"><a href="{{ $order->receiptUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">Open your order</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Questions? Just reply to this email to reach {{ $order->store->name }}.</p>
@endsection

@section('footer_note', 'You get this because you placed an order with '.$order->store->name.', a shop made with Owlvix. Owlvix does not sell these products — please contact the shop about your order.')
