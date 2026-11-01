<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A project saved from one of the free builders to the visitor's builder account (the whole editable project, as JSON). */
class BuilderProject extends Model
{
    /** The builders that save here: the page builder's modes and the email builder. */
    public const KINDS = ['landing', 'funnel', 'website', 'portfolio', 'links', 'soon', 'event', 'menu', 'store', 'email'];

    public const MAX_PER_KIND = 20;

    /** Projects carry pictures, so they can be big — but not unlimited. */
    public const MAX_BYTES = 6_000_000;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kind', 'name', 'data'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    /** Name, kind and time — what the "My sites" list shows. */
    public function toListItem(): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'name' => $this->name, 'updated' => $this->updated_at?->toIso8601String()];
    }
}
