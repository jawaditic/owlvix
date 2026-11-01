{{-- The lines and totals of a shop order (online store builder), for the order emails. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
    @foreach ($order->items as $line)
        <tr>
            <td style="padding:12px 18px;font-size:14px;color:#0f172a;border-bottom:1px solid #f1f5f9;">
                <b>{{ $line['qty'] }} × {{ $line['name'] }}</b>@if ($line['option'])<br><span style="color:#64748b;font-size:13px;">{{ $line['option'] }}</span>@endif
            </td>
            <td align="right" style="padding:12px 18px;font-size:14px;color:#0f172a;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $order->money($line['price'] * $line['qty']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="padding:10px 18px 4px;font-size:13px;color:#64748b;">Subtotal</td>
        <td align="right" style="padding:10px 18px 4px;font-size:13px;color:#64748b;">{{ $order->money($order->subtotal) }}</td>
    </tr>
    @if ($order->discount)
        <tr>
            <td style="padding:4px 18px;font-size:13px;color:#17a85f;">Discount{{ $order->discount_code ? ' ('.$order->discount_code.')' : '' }}</td>
            <td align="right" style="padding:4px 18px;font-size:13px;color:#17a85f;">−{{ $order->money($order->discount) }}</td>
        </tr>
    @endif
    <tr>
        <td style="padding:4px 18px;font-size:13px;color:#64748b;">{{ $order->delivery === 'pickup' ? 'Pick-up' : 'Delivery' }}</td>
        <td align="right" style="padding:4px 18px;font-size:13px;color:#64748b;">{{ $order->delivery_fee ? $order->money($order->delivery_fee) : 'Free' }}</td>
    </tr>
    @if ($order->tax && ! $order->tax_included)
        <tr>
            <td style="padding:4px 18px;font-size:13px;color:#64748b;">{{ $order->store->settings['tax_label'] ?? 'Tax' }}</td>
            <td align="right" style="padding:4px 18px;font-size:13px;color:#64748b;">{{ $order->money($order->tax) }}</td>
        </tr>
    @endif
    <tr>
        <td style="padding:8px 18px 14px;font-size:16px;font-weight:700;color:#0f172a;">Total</td>
        <td align="right" style="padding:8px 18px 14px;font-size:16px;font-weight:700;color:#0f172a;">{{ $order->money($order->total) }}@if ($order->tax && $order->tax_included)<br><span style="font-size:12px;font-weight:400;color:#64748b;">includes {{ $order->money($order->tax) }} {{ $order->store->settings['tax_label'] ?? 'tax' }}</span>@endif</td>
    </tr>
</table>
