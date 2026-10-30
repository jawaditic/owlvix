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

    /** Where it was sent from: "Quote popup", "Contact form", "Landing page" or "Mobile app" ($short: "Popup"). */
    public function sourceLabel(bool $short = false): string
    {
        return match ($this->source) {
            'popup' => $short ? 'Popup' : 'Quote popup',
            'app' => 'Mobile app',
            'landing' => 'Landing page',
            'seo-checker' => 'SEO checker',
            default => 'Contact form',
        };
    }

    /** The shape the mobile app reads. $full adds the message body and replies. */
    public function toApi(bool $full = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'service' => $this->service,
            'budget' => $this->budget,
            'source' => $this->source,
            'source_label' => $this->sourceLabel(),
            'excerpt' => mb_strimwidth($this->messageText(), 0, 140, '…'),
            'unread' => $this->isUnread(),
            'replies_count' => $this->replies_count ?? $this->replies()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
        if ($full) {
            $data['message_text'] = $this->messageText();
            $data['replies'] = $this->replies->map(fn ($r) => [
                'id' => $r->id,
                'subject' => $r->subject,
                'body' => $r->body,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all();
        }

        return $data;
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
