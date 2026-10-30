<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A contact form on a builder page. Static pages post to /f/{id}; each message is emailed to the owner. */
class BuilderForm extends Model
{
    public const MAX_PER_ACCOUNT = 10;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'site_name'];

    protected function casts(): array
    {
        return ['last_submission_at' => 'datetime', 'disabled_at' => 'datetime', 'submissions' => 'integer'];
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
        return ['id' => $this->id, 'site_name' => $this->site_name, 'action' => $this->action()]
            + ($withCount ? ['submissions' => $this->submissions] : []);
    }
}
