<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A shop made with the online store builder (/online-store-builder). Its pages post orders to /shop/{id}
 * (ShopController); the products and settings here are copied from the editor on every publish and download
 * (StoreController::save), so the prices checkout charges are the owner's, never the page's.
 */
class BuilderStore extends Model
{
    public const MAX_PER_ACCOUNT = 3;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'ref', 'name', 'currency', 'catalog', 'settings'];

    protected $hidden = ['stripe_test_account', 'stripe_live_account'];

    protected function casts(): array
    {
        return ['catalog' => 'array', 'settings' => 'array', 'stock' => 'array', 'orders_count' => 'integer', 'disabled_at' => 'datetime',
            'stripe_test_account' => 'encrypted', 'stripe_live_account' => 'encrypted'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(BuilderOrder::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(BuilderDiscount::class);
    }

    public static function newId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (self::whereKey($id)->exists());

        return $id;
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }

    /** Where the shop's checkout form posts. */
    public function action(): string
    {
        return \App\Support\BuilderHtml::appOrigin().'/shop/'.$this->id;
    }

    public function stripeAccount(string $mode): ?string
    {
        return $mode === 'live' ? $this->stripe_live_account : ($mode === 'test' ? $this->stripe_test_account : null);
    }

    public function toApi(): array
    {
        $mode = ($this->settings['mode'] ?? 'test') === 'live' ? 'live' : 'test';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'action' => $this->action(),
            'currency' => $this->currency,
            'products' => count($this->catalog ?? []),
            'orders' => $this->orders_count,
            'new_orders' => $this->orders()->where('status', 'new')->count(),
            'disabled' => ! $this->isActive(),
            // card payments: which modes owlvix offers, which the owner connected, and whether checkout shows cards now
            'stripe' => ['test' => ['available' => \App\Support\Stripe::available('test'), 'connected' => $this->stripe_test_account !== null],
                'live' => ['available' => \App\Support\Stripe::available('live'), 'connected' => $this->stripe_live_account !== null]],
            'card_ready' => \App\Support\Shop::cardMode($this) !== null,
            // tracked stock: {"product" or "product:variant": count}
            'stock' => (object) ($this->stock ?? []),
            'card_mode' => $mode,
        ];
    }
}
