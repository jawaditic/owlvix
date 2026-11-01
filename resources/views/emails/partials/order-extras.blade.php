{{-- Gift card codes and downloads of a paid shop order, for the order emails. --}}
@php($cards = $order->isPaid() ? $order->store->giftCards()->where('builder_order_id', $order->id)->get() : collect())
@if ($cards->isNotEmpty())
    <p style="margin:22px 0 8px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Your gift card{{ $cards->count() > 1 ? 's' : '' }}</p>
    @foreach ($cards as $card)
        <div style="margin:0 0 8px;background:#f0fdf4;border:1px dashed #17a85f;border-radius:14px;padding:14px 18px;color:#0f172a;">
            <span style="font:700 18px/1.3 ui-monospace,Menlo,monospace;letter-spacing:1px;">{{ $card->code }}</span><br>
            <span style="font-size:13px;color:#475569;">Worth {{ \App\Support\Shop::format($card->initial, $card->currency) }} at {{ $order->store->name }} — type the code in the discount box at checkout.</span>
        </div>
    @endforeach
@endif
@if ($order->isPaid() && collect($order->digitalLines())->contains('kind', 'digital'))
    <p style="margin:18px 0 0;font-size:14px;color:#0f172a;">⬇️ <b>Your downloads are ready</b> on your order page.</p>
@endif
