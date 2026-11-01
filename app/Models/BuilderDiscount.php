<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A discount code for a shop made with the online store builder (e.g. SAVE10). Kept here, never in the shop's pages. */
class BuilderDiscount extends Model
{
    public const KINDS = ['percent', 'amount', 'shipping'];

    public const MAX_PER_STORE = 50;

    protected $fillable = ['code', 'kind', 'value', 'min_total', 'ends_at', 'max_uses', 'active'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'min_total' => 'integer', 'ends_at' => 'datetime', 'max_uses' => 'integer', 'uses' => 'integer', 'active' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(BuilderStore::class, 'builder_store_id');
    }

    /** "10% off", "$5 off", "Free delivery" */
    public function label(string $currency): string
    {
        return match ($this->kind) {
            'percent' => $this->value.'% off',
            'amount' => Shop::format($this->value, $currency).' off',
            default => 'Free delivery',
        };
    }

    /** Why it can't be used on an order of this subtotal, or null when it can. */
    public function problem(int $subtotal, string $currency): ?string
    {
        if (! $this->active || ($this->ends_at && $this->ends_at->isPast())) {
            return 'The code '.$this->code.' has expired.';
        }
        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return 'The code '.$this->code.' has been used up.';
        }
        if ($subtotal < $this->min_total) {
            return 'The code '.$this->code.' needs an order of at least '.Shop::format($this->min_total, $currency).'.';
        }

        return null;
    }

    public function toApi(string $currency): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'kind' => $this->kind, 'value' => $this->kind === 'amount' ? Shop::plain($this->value, $currency) : (string) $this->value,
            'min' => $this->min_total ? Shop::plain($this->min_total, $currency) : '', 'ends' => $this->ends_at?->format('Y-m-d') ?? '',
            'max_uses' => $this->max_uses, 'uses' => $this->uses, 'active' => $this->active, 'label' => $this->label($currency)];
    }
}
