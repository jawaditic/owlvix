<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An order placed in a shop made with the online store builder. Money is in the currency's smallest unit. */
class BuilderOrder extends Model
{
    /** unpaid: card payment started but not finished. The owner moves the rest along in the Orders tab. */
    public const STATUSES = ['unpaid', 'new', 'shipped', 'done', 'cancelled'];

    /** Statuses the owner can set. */
    public const OWNER_STATUSES = ['new', 'shipped', 'done', 'cancelled'];

    protected $fillable = ['number', 'token', 'status', 'pay', 'paid_at', 'delivery', 'name', 'email', 'phone', 'address', 'country', 'note',
        'items', 'stock_taken', 'hold_until', 'currency', 'subtotal', 'discount_code', 'discount', 'gift_code', 'gift_amount', 'stripe_coupon', 'downloads', 'reminded_at', 'delivery_fee', 'tax', 'tax_included', 'total', 'refunded', 'refunded_at', 'tracking', 'shipped_at', 'stripe_mode', 'stripe_session', 'back'];

    protected $hidden = ['token', 'stripe_session'];

    protected function casts(): array
    {
        return ['items' => 'array', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'hold_until' => 'datetime', 'number' => 'integer', 'subtotal' => 'integer', 'discount' => 'integer',
            'delivery_fee' => 'integer', 'tax' => 'integer', 'tax_included' => 'boolean', 'total' => 'integer', 'refunded' => 'integer', 'refunded_at' => 'datetime', 'gift_amount' => 'integer', 'downloads' => 'integer', 'reminded_at' => 'datetime', 'stock_taken' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(BuilderStore::class, 'builder_store_id');
    }

    /** "#1001" — numbers start at 1001 so the first order doesn't look like the only one. */
    public function label(): string
    {
        return '#'.(1000 + $this->number);
    }

    public function money(int $minor): string
    {
        return Shop::format($minor, $this->currency);
    }

    /** The customer's private receipt page. */
    public function receiptUrl(): string
    {
        return route('shop.order', $this->token);
    }

    /** The tracking number or link the owner added when sending it, as a link when it is one. */
    public function trackingUrl(): ?string
    {
        return $this->tracking && preg_match('#^https?://\S+$#i', $this->tracking) ? $this->tracking : null;
    }

    public function countryName(): ?string
    {
        return $this->country ? \App\Support\Country::name($this->country) : null;
    }

    /** Lines bought as downloads or gift cards (nothing to post). */
    public function digitalLines(): array
    {
        return array_values(array_filter($this->items ?? [], fn ($l) => in_array($l['kind'] ?? 'item', ['digital', 'gift'], true)));
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public const PAY_LABELS = ['card' => 'Card (paid online)', 'cod' => 'Cash on delivery', 'bank' => 'Bank transfer'];

    public const STATUS_LABELS = ['unpaid' => 'Waiting for payment', 'new' => 'New', 'shipped' => 'Sent', 'done' => 'Completed', 'cancelled' => 'Cancelled'];

    public function toApi(): array
    {
        return [
            'number' => $this->number,
            'label' => $this->label(),
            'status' => $this->status,
            'pay' => $this->pay,
            'paid' => $this->isPaid(),
            'delivery' => $this->delivery,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'country' => $this->countryName(),
            'note' => $this->note,
            'items' => array_map(fn ($l) => $l + ['total' => $this->money($l['price'] * $l['qty']), 'price_text' => $this->money($l['price'])], $this->items ?? []),
            'subtotal' => $this->money($this->subtotal),
            'discount' => $this->discount ? '−'.$this->money($this->discount) : null,
            'discount_code' => $this->discount_code,
            'gift' => $this->gift_amount ? '−'.$this->money($this->gift_amount) : null,
            'gift_code' => $this->gift_code,
            'delivery_fee' => $this->delivery_fee ? $this->money($this->delivery_fee) : 'Free',
            'tax' => $this->tax ? $this->money($this->tax) : null,
            'tax_included' => $this->tax_included,
            'tracking' => $this->tracking,
            'total' => $this->money($this->total),
            'refunded' => $this->refunded ? $this->money($this->refunded) : null,
            // what can still be refunded, as the owner types it ("24.99"); nothing before it's paid (cash orders: once marked paid)
            'refundable' => $this->isPaid() && $this->total > $this->refunded ? \App\Support\Shop::plain($this->total - $this->refunded, $this->currency) : null,
            'test' => $this->stripe_mode === 'test',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
