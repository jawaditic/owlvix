<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A login from the mobile app. The app keeps the plain token; we keep only its hash. */
class AdminToken extends Model
{
    protected $fillable = ['token_hash', 'device', 'push_token', 'last_used_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** Create a login and return the plain token (shown to the app once). */
    public static function issue(?string $device): string
    {
        $plain = Str::random(64);
        static::create(['token_hash' => hash('sha256', $plain), 'device' => $device, 'last_used_at' => now()]);

        return $plain;
    }

    public static function findByPlain(?string $plain): ?self
    {
        return $plain ? static::where('token_hash', hash('sha256', $plain))->first() : null;
    }
}
