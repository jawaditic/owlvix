<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\DB;

/** Views and leads of one funnel step (page) and A/B variant on one day. No visitor data, only counts. */
class BuilderStat extends Model
{
    use Prunable;

    public $timestamps = false;

    public const KEEP_DAYS = 400;

    protected $fillable = ['builder_site_id', 'step', 'variant', 'day', 'views', 'leads'];

    protected function casts(): array
    {
        return ['views' => 'integer', 'leads' => 'integer'];
    }

    /** Add one to today's "views" or "leads" (safe when two requests race: the row is created once, then incremented). */
    public static function bump(int $siteId, string $step, string $variant, string $column): void
    {
        $key = ['builder_site_id' => $siteId, 'step' => $step, 'variant' => $variant, 'day' => now()->toDateString()];
        DB::table('builder_stats')->insertOrIgnore($key + ['views' => 0, 'leads' => 0]);
        DB::table('builder_stats')->where($key)->increment($column === 'leads' ? 'leads' : 'views');
    }

    public function prunable(): Builder
    {
        return static::where('day', '<', now()->subDays(self::KEEP_DAYS)->toDateString());
    }
}
