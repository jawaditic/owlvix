<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'service', 'budget', 'message', 'source', 'ip'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MessageReply::class)->oldest();
    }

    /** The project description as safe HTML (it may contain formatting from the editor). */
    public function messageHtml(): string
    {
        return \App\Support\RichText::html($this->message);
    }

    /** The project description without formatting. */
    public function messageText(): string
    {
        return \App\Support\RichText::plain($this->message);
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
