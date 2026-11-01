@extends('emails.layout')

@section('preheader', 'Your order at '.$order->store->name.' wasn’t finished — nothing was charged.')
@section('eyebrow', 'Still thinking? 🛒')
@section('headline', 'You left something behind')
@section('subline', 'Your payment at '.$order->store->name.' wasn’t finished, so nothing was charged.')

@section('body')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($order->items as $line)
            <tr><td style="padding:12px 18px;font-size:14px;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}"><b>{{ $line['qty'] }} × {{ $line['name'] }}</b>@if ($line['option'])<br><span style="color:#64748b;font-size:13px;">{{ $line['option'] }}</span>@endif</td></tr>
        @endforeach
    </table>
    @if ($order->back)
        <p style="margin:24px 0 0;"><a href="{{ $order->back }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">Go back to {{ $order->store->name }}</a></p>
    @endif
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Had a problem paying? Just reply to this email to reach {{ $order->store->name }}. We send this reminder only once.</p>
@endsection

@section('footer_note', 'You get this because you started an order with '.$order->store->name.', a shop made with Owlvix.')
