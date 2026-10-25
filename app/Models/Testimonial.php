<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A client review shown on the site (managed in Admin → Reviews). */
class Testimonial extends Model
{
    protected $fillable = ['name', 'country_code', 'service', 'text', 'rating', 'sort_order', 'is_published', 'in_email'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'in_email' => 'boolean', 'rating' => 'integer', 'sort_order' => 'integer'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function countryName(): string
    {
        return config('countries.'.$this->country_code, '');
    }

    /** Flag emoji built from the two-letter country code, e.g. "us" → 🇺🇸 */
    public function flag(): string
    {
        $code = strtoupper((string) $this->country_code);
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
