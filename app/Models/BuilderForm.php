<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A form on a builder page. Static pages post to /f/{id}. Kind "contact": each message is emailed to the owner.
 * Kind "leads" (funnel opt-in): each lead is emailed, stored (BuilderLead) and sent to the owner's email tools.
 */
class BuilderForm extends Model
{
    public const MAX_PER_ACCOUNT = 10;

    public $incrementing = false;

    protected $keyType = 'string';

    public const KINDS = ['contact', 'leads'];

    protected $fillable = ['id', 'site_name', 'kind', 'ref'];

    protected $attributes = ['kind' => 'contact'];

    protected $hidden = ['integrations'];

    protected function casts(): array
    {
        return [
            'last_submission_at' => 'datetime', 'disabled_at' => 'datetime', 'submissions' => 'integer',
            'integrations' => 'encrypted:array', // API keys for Mailchimp / Brevo, webhook URL
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(BuilderLead::class);
    }

    public function isLeads(): bool
    {
        return $this->kind === 'leads';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    public static function newId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (self::whereKey($id)->exists());

        return $id;
    }

    public function action(): string
    {
        return url('/f/'.$this->id);
    }

    public function toApi(bool $withCount = false): array
    {
        return ['id' => $this->id, 'site_name' => $this->site_name, 'action' => $this->action(), 'kind' => $this->kind ?? 'contact']
            + ($withCount ? ['submissions' => $this->submissions, 'leads' => (int) ($this->leads_count ?? $this->leads()->count())] : []);
    }
}
