<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A landing page builder user. Signs in with a code sent by email, then uses a token (see BuilderToken). */
class BuilderAccount extends Model
{
    public const MAX_TOKENS = 10;

    protected $fillable = ['email', 'last_login_at'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(BuilderToken::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(BuilderSite::class);
    }

    public function apps(): HasMany
    {
        return $this->hasMany(BuilderApp::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(BuilderForm::class);
    }

    /** A new token for one device. Older devices stay signed in (up to MAX_TOKENS). */
    public function issueToken(): string
    {
        $token = Str::random(64);
        $this->tokens()->create(['token_hash' => hash('sha256', $token), 'last_used_at' => now()]);

        $keep = $this->tokens()->latest('id')->take(self::MAX_TOKENS)->pluck('id');
        $this->tokens()->whereNotIn('id', $keep)->delete();

        return $token;
    }

    public static function forToken(?string $token): ?self
    {
        if (! is_string($token) || strlen($token) < 48 || strlen($token) > 128) {
            return null;
        }
        $row = BuilderToken::where('token_hash', hash('sha256', $token))->first();
        if (! $row) {
            return null;
        }
        if (! $row->last_used_at || $row->last_used_at->lt(now()->subHour())) {
            $row->forceFill(['last_used_at' => now()])->save();
        }

        return $row->account;
    }
}
