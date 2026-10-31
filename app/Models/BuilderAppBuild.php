<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * One Android build of a BuilderApp, made by GitHub Actions. Its APK and AAB are static files in the app's public
 * folder under a secret name, builds/{random}/{name}-{version}.apk (AppContent::dir), recorded in "files".
 */
class BuilderAppBuild extends Model
{
    /** A build that hasn't finished by then has stopped (GitHub's own limit for the job is 45 minutes). */
    public const TIMEOUT_MINUTES = 60;

    public const KINDS = ['apk', 'aab'];

    protected $fillable = ['status', 'version_code', 'version_name', 'token_hash', 'job', 'files', 'error', 'started_at', 'finished_at'];

    protected $hidden = ['token_hash', 'job'];

    protected function casts(): array
    {
        return ['job' => 'encrypted:array', 'files' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'version_code' => 'integer'];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(BuilderApp::class, 'builder_app_id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'building'], true);
    }

    /** The finished file on disk, or null. */
    public function filePath(string $kind): ?string
    {
        $path = $this->status === 'done' ? ($this->files[$kind]['path'] ?? null) : null;

        return is_string($path) && preg_match('#^builds/[a-zA-Z0-9]{32}/[a-z0-9-]+\.(apk|aab)$#', $path) ? \App\Support\AppContent::dir($this->app).'/'.$path : null;
    }

    public function fileUrl(string $kind): ?string
    {
        return $this->filePath($kind) ? \App\Support\AppContent::baseUrl($this->app).$this->files[$kind]['path'] : null;
    }

    /** Removes the APK and AAB (the newest builds' files are kept). */
    public function deleteFiles(): void
    {
        foreach (self::KINDS as $kind) {
            if ($file = $this->filePath($kind)) {
                \Illuminate\Support\Facades\File::deleteDirectory(dirname($file));
            }
        }
        $this->update(['files' => null]);
    }

    /** Marks a build that has been running too long as failed. Returns true when it did. */
    public function expireIfStuck(): bool
    {
        if ($this->isRunning() && $this->created_at && $this->created_at->lt(now()->subMinutes(self::TIMEOUT_MINUTES))) {
            $this->update(['status' => 'failed', 'error' => 'The build took too long and was stopped. Please try again.', 'token_hash' => null, 'job' => null, 'finished_at' => now()]);

            return true;
        }

        return false;
    }

    /** Download link that works without signing in (for the email and for phones), valid for $days days. */
    public function downloadUrl(string $kind, int $days = 7): ?string
    {
        if ($this->status !== 'done' || ! isset($this->files[$kind])) {
            return null;
        }

        return URL::temporarySignedRoute('builder.apps.download', now()->addDays($days), ['build' => $this->id, 'kind' => $kind]);
    }

    public function toApi(): array
    {
        $this->expireIfStuck();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'version_code' => $this->version_code,
            'version_name' => $this->version_name,
            'error' => $this->error,
            'files' => collect(self::KINDS)->filter(fn ($k) => isset($this->files[$k]))
                ->mapWithKeys(fn ($k) => [$k => ['url' => $this->downloadUrl($k, 2), 'bytes' => $this->files[$k]['bytes'] ?? null]])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
