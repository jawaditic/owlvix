<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A booking page made with the booking page builder: https://owlvix.com/book/{id}. Settings: App\Support\BookingSchema. */
class BuilderBookingPage extends Model
{
    public const MAX_PER_ACCOUNT = 5;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'builder_account_id', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'disabled_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    public function bookingsList(): HasMany
    {
        return $this->hasMany(BuilderBooking::class);
    }

    public static function newId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (self::whereKey($id)->exists());

        return $id;
    }

    public function publicUrl(): string
    {
        return url('/book/'.$this->id);
    }

    public function name(): string
    {
        return (string) ($this->settings['name'] ?? '') ?: 'Booking page';
    }

    public function tz(): string
    {
        return (string) ($this->settings['tz'] ?? 'UTC');
    }

    /** What the editor gets back. */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'cfg' => $this->settings, 'url' => $this->publicUrl(), 'disabled' => (bool) $this->disabled_at,
            'upcoming' => $this->bookingsList()->where('status', 'confirmed')->where('starts_at', '>=', now())->count(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
