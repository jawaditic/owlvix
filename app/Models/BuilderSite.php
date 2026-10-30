<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A site published from the page builder, served at https://{BUILDER_DOMAIN}/{slug} (or /p/{slug}). The HTML lives on the "builder" disk. */
class BuilderSite extends Model
{
    public const MAX_PER_ACCOUNT = 3;

    // Addresses nobody can publish at
    public const RESERVED = ['admin', 'administrator', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'builder', 'cdn',
        'contact', 'dashboard', 'docs', 'f', 'ftp', 'help', 'login', 'logout', 'mail', 'news', 'owlvix', 'p', 'pay',
        'payment', 'privacy', 'register', 'report', 'root', 'security', 'settings', 'signin', 'signup', 'static',
        'status', 'support', 'system', 'terms', 'test', 'www', 'webmail', 'cgi-bin', 'well-known', 'cpanel', 'whm', 'ftp'];

    protected $fillable = ['slug', 'name', 'pages', 'status', 'removed_by'];

    protected function casts(): array
    {
        return ['pages' => 'array', 'reported_at' => 'datetime', 'views' => 'integer', 'reports' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BuilderAccount::class, 'builder_account_id');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function url(?string $page = null): string
    {
        return self::base().$this->slug.($page && $page !== $this->homeSlug() ? '/'.$page : '');
    }

    /** Where published sites live: "https://sites.owlvix.com/" with BUILDER_DOMAIN set, else "{app}/p/". */
    public static function base(): string
    {
        $domain = config('builder.domain');
        // (with builder.public_path set, the subdomain folder is served directly by the web server)
        if (! $domain) {
            return rtrim(url('/p'), '/').'/';
        }

        return (parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.$domain.'/';
    }

    /** The address without https://, for showing to people. */
    public function displayUrl(?string $page = null): string
    {
        return preg_replace('#^https?://#', '', $this->url($page));
    }

    /** The page served at /p/{slug}: "index" if there is one, else the first page. */
    public function homeSlug(): ?string
    {
        $slugs = array_column($this->pages ?? [], 'slug');

        return in_array('index', $slugs, true) ? 'index' : ($slugs[0] ?? null);
    }

    public function hasPage(string $page): bool
    {
        return in_array($page, array_column($this->pages ?? [], 'slug'), true);
    }

    public function path(string $page): string
    {
        return $this->id.'/'.$page.'.html';
    }

    public static function disk()
    {
        return Storage::disk('builder');
    }

    public function deleteFiles(): void
    {
        self::disk()->deleteDirectory((string) $this->id);
        $this->removePublicFiles();
    }

    /* ---------- Static copies in the subdomain folder (config builder.public_path) ---------- */

    /** Marker file in every folder we create there, so we never delete anything we didn't make. */
    public const MARKER = '.owlvix-site';

    public static function publicRoot(): ?string
    {
        $root = config('builder.public_path');

        return $root ? rtrim($root, '/') : null;
    }

    /** Write the live site to the subdomain folder (or remove it there when it isn't live). */
    public function syncPublicFiles(): void
    {
        $root = self::publicRoot();
        if (! $root) {
            return;
        }
        $this->removePublicFiles();
        if (! $this->isLive()) {
            return;
        }
        \Illuminate\Support\Facades\File::ensureDirectoryExists($root);
        \Illuminate\Support\Facades\File::put($root.'/.htaccess', \App\Support\BuilderHtml::htaccess());
        $dir = $root.'/'.$this->slug;
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        \Illuminate\Support\Facades\File::put($dir.'/'.self::MARKER, (string) $this->id);
        foreach ($this->pages ?? [] as $page) {
            $html = self::disk()->get($this->path($page['slug']));
            if (! is_string($html)) {
                continue;
            }
            $target = $page['slug'] === $this->homeSlug() ? $dir : $dir.'/'.$page['slug'];
            \Illuminate\Support\Facades\File::ensureDirectoryExists($target);
            \Illuminate\Support\Facades\File::put($target.'/index.html', \App\Support\BuilderHtml::withBar($html, $this));
        }
    }

    public function removePublicFiles(): void
    {
        $root = self::publicRoot();
        $dir = $root ? $root.'/'.$this->slug : null;
        // Only folders this app created (they carry the marker) — never cgi-bin, .well-known or anything else
        if ($dir && preg_match('/^[a-z0-9-]{3,40}$/', $this->slug) && is_file($dir.'/'.self::MARKER)) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
    }

    public function toApi(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'url' => $this->url(),
            'pages' => array_map(fn ($p) => ['slug' => $p['slug'], 'title' => $p['title']], $this->pages ?? []),
            'status' => $this->isLive() ? 'live' : 'removed',
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
