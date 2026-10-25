<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'service', 'budget', 'message', 'source', 'ip'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MessageReply::class)->oldest();
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->name;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
