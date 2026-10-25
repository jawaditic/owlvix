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

    /** The shape the mobile app's admin reads. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'country' => $this->countryName(),
            'flag' => $this->flag(),
            'service' => $this->service,
            'text' => $this->text,
            'rating' => $this->rating,
            'is_published' => $this->is_published,
            'in_email' => $this->in_email,
            'sort_order' => $this->sort_order,
        ];
    }

    /** Form rules for adding or editing a review (the on/off switches are read separately). */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'country_code' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('countries')))],
            'service' => ['nullable', 'string', 'max:60'],
            'text' => ['required', 'string', 'min:5', 'max:1000'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public const SETTINGS_RULES = [
        'reviews_profile_url' => ['required', 'url', 'max:255'],
        'reviews_rating' => ['required', 'numeric', 'between:0,5'],
        'reviews_count' => ['required', 'integer', 'min:0'],
        'reviews_five_star' => ['required', 'integer', 'min:0', 'lte:reviews_count'],
    ];

    public const SETTINGS_MESSAGES = [
        'reviews_five_star.lte' => 'Five-star reviews can\'t be more than the total number of reviews.',
    ];

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
