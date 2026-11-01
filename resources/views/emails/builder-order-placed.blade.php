@extends('emails.layout')
@php
    $paid = $order->isPaid();
    $rows = [
        'Customer' => e($order->name),
        'Email' => '<a href="mailto:'.e($order->email).'" style="color:#17a85f;font-weight:600;">'.e($order->email).'</a>',
        'Phone' => filled($order->phone) ? '<a href="tel:'.e(preg_replace('/[^\d+]/', '', $order->phone)).'" style="color:#17a85f;">'.e($order->phone).'</a>' : '—',
        $order->delivery === 'pickup' ? 'Pick-up' : ($order->delivery === 'digital' ? 'Delivery' : 'Deliver to') => match ($order->delivery) {
            'pickup' => 'The customer will collect it',
            'digital' => 'Nothing to post: downloads and gift cards are sent once the order is paid',
            default => nl2br(e($order->address)).($order->countryName() ? '<br>'.e($order->countryName()) : ''),
        },
        'Payment' => e(\App\Models\BuilderOrder::PAY_LABELS[$order->pay] ?? $order->pay).($paid ? ' — <b style="color:#17a85f;">paid</b>' : ' — not paid yet').($order->stripe_mode === 'test' ? ' (test payment)' : ''),
        'Placed' => $order->created_at->format('d M Y, H:i').' UTC',
    ];
@endphp

@section('preheader', $order->name.' ordered '.count($order->items).' item'.(count($order->items) === 1 ? '' : 's').' — '.$order->money($order->total))
@section('eyebrow', 'New order 🛍️')
@section('headline', 'Order '.$order->label().' from '.$order->name)
@section('subline', 'Placed in your shop '.$order->store->name.'.')

@section('body')
    @include('emails.partials.order-items', ['order' => $order])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td width="110" style="padding:12px 18px;font-size:13px;color:#64748b;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{{ $label }}</td>
                <td style="padding:12px 18px 12px 0;font-size:14px;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{!! $value !!}</td>
            </tr>
        @endforeach
    </table>

    @if (filled($order->note))
        <p style="margin:22px 0 10px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Note from the customer</p>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;color:#1e293b;line-height:1.6;white-space:pre-wrap;">{{ $order->note }}</div>
    @endif

    <p style="margin:22px 0 0;font-size:14px;color:#64748b;">Reply to this email to write to {{ $order->name }}. See all your orders and mark them as sent in the <a href="{{ route('store-builder') }}" style="color:#17a85f;">online store builder</a> (Orders tab).</p>
@endsection

@section('footer_note', 'You get this because your shop was made with the Owlvix online store builder. Want a custom online store? Reply to any Owlvix email.')
