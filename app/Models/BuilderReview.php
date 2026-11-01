<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product review in a shop made with the online store builder. Only buyers can write one (from their order page). */
class BuilderReview extends Model
{
    protected $fillable = ['builder_order_id', 'product_id', 'name', 'rating', 'text', 'hidden'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'hidden' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(BuilderStore::class, 'builder_store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BuilderOrder::class, 'builder_order_id');
    }

    /** What shoppers see: first name and initial only. */
    public function shownName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [''];

        return trim($parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '')) ?: 'Customer';
    }

    public function toPublic(): array
    {
        return ['name' => $this->shownName(), 'rating' => $this->rating, 'text' => (string) $this->text, 'date' => $this->created_at?->format('Y-m-d')];
    }
}
