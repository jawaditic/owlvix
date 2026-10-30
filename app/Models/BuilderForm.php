<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A form on a builder page. Static pages post to /f/{id}. Kind "contact": each message is emailed to the owner.
 * Kind "leads" (funnel opt-in): each lead is emailed, stored (BuilderLead) and sent to the owner's email tools.
 * Kind "survey" (form builder): its own fields (schema, see App\Support\SurveySchema), shown at /form/{id};
 * each answer is stored (BuilderResponse) and emailed.
 */
class BuilderForm extends Model
{
    /** Page-builder forms (contact, leads) per account. */
    public const MAX_PER_ACCOUNT = 10;

    /** Form-builder forms (survey) per account, counted separately. */
    public const MAX_SURVEYS = 20;

    public $incrementing = false;

    protected $keyType = 'string';

    public const KINDS = ['contact', 'leads', 'survey'];

    /** Kinds the page builder makes (POST /builder/forms). Surveys are made only by the form builder, with a schema. */
    public const PAGE_KINDS = ['contact', 'leads'];

    protected $fillable = ['id', 'site_name', 'kind', 'ref', 'schema'];

    protected $attributes = ['kind' => 'contact'];

    protected $hidden = ['integrations'];

    protected function casts(): array
    {
        return [
            'last_submission_at' => 'datetime', 'disabled_at' => 'datetime', 'submissions' => 'integer',
            'integrations' => 'encrypted:array', // API keys for Mailchimp / Brevo, webhook URL
            'schema' => 'array', // survey forms: title, intro, fields, button, thanks, colour
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(BuilderLead::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(BuilderResponse::class);
    }

    public function isSurvey(): bool
    {
        return $this->kind === 'survey';
    }

    /** Where a survey form is filled in. */
    public function publicUrl(): string
    {
        return url('/form/'.$this->id);
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
