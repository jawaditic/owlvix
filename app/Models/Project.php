<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A portfolio project shown in "Our work" (managed in Admin → Portfolio). */
class Project extends Model
{
    public const CATEGORIES = [
        'website' => 'Website',
        'webapp' => 'Web app',
        'mobile' => 'Mobile app',
        'shopify' => 'Shopify',
        'ai' => 'AI',
    ];

    protected $fillable = ['title', 'category', 'summary', 'image', 'url', 'ios_url', 'android_url', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }

    /** Uploaded images live in public/uploads/portfolio; the starter images ship with the site. */
    public function deleteUploadedImage(): void
    {
        if ($this->image && str_starts_with($this->image, 'uploads/portfolio/')) {
            @unlink(public_path($this->image));
        }
    }
}
