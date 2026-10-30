<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One answer to a form made with the form builder (/form/{id}). Kept until the owner deletes the form. */
class BuilderResponse extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['answers'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'created_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(BuilderForm::class, 'builder_form_id');
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'answers' => (object) ($this->answers ?? []), 'created_at' => $this->created_at?->toIso8601String()];
    }
}
