@extends('emails.layout')

@section('preheader', $order->store->name.' has refunded '.$order->money($amount).'.')
@section('eyebrow', 'Refund 💸')
@section('headline', 'Your refund of '.$order->money($amount))
@section('subline', $order->store->name.' has refunded '.($order->refunded >= $order->total ? 'your order ' : 'part of your order ').$order->label().'.')

@section('body')
    <p style="margin:0 0 14px;font-size:15px;color:#0f172a;line-height:1.6;">
        @if ($order->pay === 'card')
            The money goes back to the card you paid with. It usually shows on your statement within 5–10 working days.
        @else
            The shop is paying it back to you the way you agreed.
        @endif
    </p>
    @include('emails.partials.order-items', ['order' => $order])
    <p style="margin:24px 0 0;"><a href="{{ $order->receiptUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See your order</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Questions? Just reply to this email to reach {{ $order->store->name }}.</p>
@endsection

@section('footer_note', 'You get this because you placed an order with '.$order->store->name.', a shop made with Owlvix. Owlvix does not sell these products — please contact the shop about your order.')
