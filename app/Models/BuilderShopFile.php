<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A file sold as a digital download in a shop (online store builder). Kept privately, handed out only to buyers. */
class BuilderShopFile extends Model
{
    public const MAX_BYTES = 25 * 1024 * 1024;

    public const MAX_PER_STORE = 30;

    public const MAX_DOWNLOADS = 10; // per product per order

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'size', 'mime'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public static function newId(): string
    {
        return Str::lower(Str::random(24));
    }

    public static function disk()
    {
        return Storage::disk('local');
    }

    public function path(): string
    {
        return 'shop-files/'.$this->builder_store_id.'/'.$this->id;
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'size' => $this->size];
    }
}
