<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One page view on the public site (see TrackController). */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visitor', 'path', 'country', 'source', 'referrer_host', 'device', 'browser', 'is_new'];

    protected function casts(): array
    {
        return ['is_new' => 'boolean', 'created_at' => 'datetime'];
    }
}
