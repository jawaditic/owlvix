<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An app made with the mobile app builder (/mobile-app-builder): native screens built from blocks, and optionally
 * pages of the owner's website, in the Android app in app-shell/. Its icon is kept on the "local" disk under
 * apps/{id}/; its live content and finished APK/AAB files are static files in the public folder (AppContent).
 */
class BuilderApp extends Model
{
    public const MAX_PER_ACCOUNT = 3;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'package', 'url', 'settings', 'content', 'status', 'reported_at', 'reports', 'verify_code', 'verified_host', 'version_code', 'keystore', 'keystore_password'];

    protected $hidden = ['keystore', 'keystore_password', 'verify_code'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'content' => 'array', 'reported_at' => 'datetime', 'reports' => 'integer', 'version_code' => 'integer', 'keystore' => 'encrypted', 'keystore_password' => 'encrypted'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    public function pushes(): HasMany
    {
        return $this->hasMany(BuilderAppPush::class);
    }

    public function builds(): HasMany
    {
        return $this->hasMany(BuilderAppBuild::class);
    }

    public static function newId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (self::whereKey($id)->exists());

        return $id;
    }

    public static function disk()
    {
        return Storage::disk('local');
    }

    public function dir(): string
    {
        return 'apps/'.$this->id;
    }

    public function isLive(): bool
    {
        return ($this->status ?? 'live') === 'live';
    }

    /** Where people report an app (linked at the bottom of the app's ☰ menu). */
    public function reportUrl(): string
    {
        return route('builder.apps.report', $this->id);
    }

    /** The app's privacy policy page (for its Google Play listing). */
    public function privacyUrl(): string
    {
        return route('builder.apps.privacy', $this->id);
    }

    public function host(): string
    {
        return strtolower((string) parse_url((string) $this->url, PHP_URL_HOST));
    }

    public function isVerified(): bool
    {
        return $this->verified_host !== null && $this->verified_host === $this->host();
    }

    public function hasIcon(): bool
    {
        return self::disk()->exists($this->dir().'/icon.png') && self::disk()->exists($this->dir().'/foreground.png');
    }

    public function deleteFiles(): void
    {
        self::disk()->deleteDirectory($this->dir());
        \App\Support\AppContent::remove($this);
    }

    public function toApi(): array
    {
        $latest = $this->builds()->latest('id')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'package' => $this->package,
            'url' => $this->url,
            'settings' => $this->settings,
            'tabs' => $this->content['tabs'] ?? [],
            'translations' => (object) ($this->content['translations'] ?? []),
            'content_url' => \App\Support\AppContent::baseUrl($this).'content.json',
            'allowed' => \App\Support\AppBuilder::allowed($this),
            'needs_website' => \App\Support\AppBuilder::needsWebsite($this),
            'own_site' => $this->url !== null && \App\Support\AppBuilder::isBuilderHost($this->host()),
            'tag' => \App\Support\AppBuilder::metaTag($this),
            'has_icon' => $this->hasIcon(),
            'version_code' => $this->version_code,
            'has_keystore' => $this->keystore !== null,
            'build' => $latest?->toApi(),
            'out_of_date' => \App\Support\AppBuilder::outOfDate($this),
            'removed' => ! $this->isLive(),
            'privacy_url' => $this->privacyUrl(),
            // Push notifications: switched on for the builder, and the app on phones can receive them
            'push' => ['available' => \App\Support\Firebase::enabled(), 'ready' => (bool) ($latest?->status === 'done' ? ($latest->info['push'] ?? false) : ($this->builds()->where('status', 'done')->latest('id')->first()?->info['push'] ?? false))],
            'has_notification_icon' => self::disk()->exists($this->dir().'/notification.png'),
            'data_use' => \App\Support\AppBuilder::dataUse($this),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
