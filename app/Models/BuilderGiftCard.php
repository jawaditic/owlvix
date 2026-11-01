<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A gift card of a shop made with the online store builder: bought as a product, spent at checkout with its code. */
class BuilderGiftCard extends Model
{
    protected $fillable = ['code', 'currency', 'initial', 'balance', 'builder_order_id', 'email'];

    protected function casts(): array
    {
        return ['initial' => 'integer', 'balance' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(BuilderStore::class, 'builder_store_id');
    }

    /** "GIFT-7KQ2-M9XD": no 0/O or 1/I, so it can be read out and typed. */
    public static function newCode(BuilderStore $store): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $pick = fn () => implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 4)));
            $code = 'GIFT-'.$pick().'-'.$pick();
        } while ($store->giftCards()->where('code', $code)->exists());

        return $code;
    }

    public function toApi(): array
    {
        return ['code' => $this->code, 'initial' => Shop::format($this->initial, $this->currency), 'balance' => Shop::format($this->balance, $this->currency),
            'email' => $this->email, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
