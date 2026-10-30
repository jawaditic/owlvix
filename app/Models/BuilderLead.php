<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who filled in a funnel's lead form (/f/{id} with kind "leads"). Kept for the funnel owner to read and download. */
class BuilderLead extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** Leads of sites that were deleted or unpublished are removed after this many days. */
    public const KEEP_DAYS_AFTER_REMOVAL = 90;

    protected $fillable = ['builder_site_id', 'step', 'variant', 'name', 'email', 'phone'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(BuilderForm::class, 'builder_form_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(BuilderSite::class, 'builder_site_id');
    }

    /** Old leads whose site is gone (deleted, unpublished or taken down). */
    public function prunable(): Builder
    {
        // Leads of funnels that have been unpublished or taken down for 90+ days. Leads of live funnels, and leads not
        // linked to a site (e.g. a downloaded funnel hosted elsewhere), stay until the owner's form is deleted.
        return static::whereIn('builder_site_id', BuilderSite::select('id')
            ->where('status', '!=', 'live')->where('updated_at', '<', now()->subDays(self::KEEP_DAYS_AFTER_REMOVAL)));
    }

    public function toApi(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'step' => $this->step,
            'variant' => $this->variant,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
