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

    /** The shape the mobile app reads. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'summary' => $this->summary,
            'image_url' => $this->imageUrl(),
            'url' => $this->url,
            'ios_url' => $this->ios_url,
            'android_url' => $this->android_url,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
        ];
    }

    /** Form rules for adding (image required) or editing a project. */
    public static function rules(bool $creating): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', \Illuminate\Validation\Rule::in(array_keys(self::CATEGORIES))],
            'summary' => ['nullable', 'string', 'max:300'],
            'url' => ['nullable', 'url', 'max:255'],
            'ios_url' => ['nullable', 'url', 'max:255'],
            'android_url' => ['nullable', 'url', 'max:255'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public const RULE_MESSAGES = [
        'image.required' => 'Please add a screenshot or mockup of the project.',
        'image.max' => 'The image must be 5 MB or smaller.',
    ];

    /** Save an uploaded screenshot to public/uploads/portfolio and return its path. */
    public static function storeImage(\Illuminate\Http\UploadedFile $file, string $title): string
    {
        $dir = public_path('uploads/portfolio');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = \Illuminate\Support\Str::slug($title).'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)).'.'.$file->extension();
        $file->move($dir, $name);

        return 'uploads/portfolio/'.$name;
    }

    /** Uploaded images live in public/uploads/portfolio; the starter images ship with the site. */
    public function deleteUploadedImage(): void
    {
        if ($this->image && str_starts_with($this->image, 'uploads/portfolio/')) {
            @unlink(public_path($this->image));
        }
    }
}
