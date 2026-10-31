<?php

namespace App\Support;

use App\Models\BuilderAccount;
use App\Models\BuilderApp;
use App\Models\BuilderAppBuild;
use App\Models\BuilderSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mobile app builder (/mobile-app-builder): checks an app's settings (its screens are checked by AppContent), checks
 * the owner may show their website in it, and starts Android builds on GitHub Actions
 * (.github/workflows/build-android-app.yml → app-shell/).
 */
class AppBuilder
{
    public const COLORS = ['primary', 'bar', 'splash', 'icon'];

    /** Builds one account can start per day. */
    public const DAILY_BUILDS = 5;

    /** Tab icon names (the same drawings are used in the app and in the builder preview). */
    public static function icons(): array
    {
        static $icons;

        return $icons ??= json_decode((string) file_get_contents(base_path('app-shell/icons.json')), true) ?: [];
    }

    /** Builds can run: GitHub repo and token are set. */
    public static function enabled(): bool
    {
        return (bool) config('builder.apps.github_repo') && (bool) config('builder.apps.github_token');
    }

    /**
     * Checks and tidies the app settings sent by the builder.
     *
     * @return array{name: string, package: string, url: ?string, tabs: array, images: array, settings: array}|string the clean data, or an error
     */
    public static function clean(mixed $in, BuilderAccount $account, ?BuilderApp $app = null): array|string
    {
        if (! is_array($in)) {
            return 'Please fill in your app details.';
        }
        $str = fn ($v, $max) => is_string($v) ? trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $v) ?? '') : '';

        $name = $str($in['name'] ?? '', 30);
        if ($name === '' || mb_strlen($name) > 30) {
            return 'Please give your app a name (up to 30 characters).';
        }

        $package = strtolower($str($in['package'] ?? '', 100));
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,5}$/', $package) || strlen($package) > 100) {
            return 'The app ID should look like com.yourbusiness.app — small letters, numbers and dots, starting with a letter.';
        }
        if (preg_match('/^(com\.owlvix|com\.example|com\.android|com\.google|android|java)\./', $package.'.')) {
            return 'That app ID is reserved. Use your own business name, e.g. com.yourbusiness.app.';
        }
        if (BuilderApp::where('package', $package)->when($app, fn ($q) => $q->whereKeyNot($app->id))->exists()) {
            return 'That app ID is already used by another app. Please pick a different one.';
        }
        if ($app && $app->version_code > 0 && $app->package !== $package) {
            return 'The app ID can’t change after the first build — Google Play treats a new ID as a different app.';
        }

        // The website is optional: it's needed only for tabs that show a page of it
        $url = null;
        $own = null;
        if (($raw = $str($in['url'] ?? '', 500)) !== '') {
            $url = self::httpsUrl($raw);
            if (! $url) {
                return 'Your website address must start with https://';
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $own = self::ownSite($url, $account);
            if (! $own && self::isBuilderHost($host)) {
                return 'That page isn’t one of your published sites. Pick one of your sites, or sign in with the email you published it with.';
            }
        }
        $webUrl = function (string $tabUrl) use ($url, $own) {
            $tabUrl = self::httpsUrl($tabUrl);

            return $url && $tabUrl && self::sameSite($tabUrl, $url, $own) ? $tabUrl : null;
        };
        $content = (new AppContent($app))->clean($in['tabs'] ?? null, $webUrl);
        if (is_string($content)) {
            return $content;
        }

        $colors = [];
        foreach (self::COLORS as $key) {
            $c = $in['colors'][$key] ?? null;
            if (! is_string($c) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
                return 'Please pick all the app colours.';
            }
            $colors[$key] = strtolower($c);
        }

        $version = $str($in['version_name'] ?? '1.0.0', 20) ?: '1.0.0';
        if (! preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $version)) {
            return 'The version should look like 1.0.0.';
        }

        $offline = [];
        foreach (['title' => [60, 'You’re offline'], 'text' => [200, 'Check your internet connection and try again.'], 'button' => [30, 'Try again']] as $key => [$max, $default]) {
            $v = $str($in['offline'][$key] ?? '', $max);
            if (mb_strlen($v) > $max) {
                return 'The offline message is too long.';
            }
            $offline[$key] = $v !== '' ? $v : $default;
        }

        // For the app's privacy policy page (/app-privacy/{id}), which Google Play asks for
        $privacy = ['owner' => $str($in['privacy']['owner'] ?? '', 100), 'email' => strtolower($str($in['privacy']['email'] ?? '', 150)),
            'extra' => is_string($in['privacy']['extra'] ?? null) ? AppContent::str($in['privacy']['extra'], true) : ''];
        if (mb_strlen($privacy['owner']) > 100 || mb_strlen($privacy['extra']) > 3000) {
            return 'The privacy policy details are too long.';
        }
        if ($privacy['email'] !== '' && ! filter_var($privacy['email'], FILTER_VALIDATE_EMAIL)) {
            return 'The privacy contact email doesn’t look right.';
        }

        return [
            'name' => $name,
            'package' => $package,
            'url' => $url,
            'tabs' => $content['tabs'],
            'images' => $content['images'],
            'settings' => [
                'version_name' => $version,
                'colors' => $colors,
                'status_bar' => ($in['status_bar'] ?? '') === 'light' ? 'light' : 'dark',
                'external_links' => ($in['external_links'] ?? '') === 'app' ? 'app' : 'browser',
                // The ☰ menu at the top: shown (listing the pages ticked for it) or not at all
                'menu' => ($in['menu'] ?? '') === 'hide' ? 'hide' : 'show',
                'offline' => $offline,
                'privacy' => $privacy,
            ],
        ];
    }

    /** What the app does, for its privacy policy and Google Play's data safety answers. */
    public static function dataUse(BuilderApp $app): array
    {
        $blocks = collect($app->content['tabs'] ?? [])->flatMap(fn ($t) => $t['blocks'] ?? []);
        $actions = $blocks->flatMap(fn ($b) => array_filter([$b['action']['kind'] ?? null, $b['button']['action']['kind'] ?? null,
            ...array_map(fn ($i) => $i['action']['kind'] ?? null, $b['items'] ?? [])]));

        return [
            'website' => self::needsWebsite($app) ? $app->host() : null,
            'youtube' => $blocks->contains('type', 'video'),
            'social' => $blocks->contains('type', 'social'),
            'contact' => $blocks->contains('type', 'contact') || $actions->intersect(['tel', 'email', 'whatsapp', 'map'])->isNotEmpty(),
            'links' => $actions->contains('url'),
        ];
    }

    /**
     * A 1024×1024 PNG from a data: URL (icons are drawn in the browser).
     *
     * @return string|null the PNG bytes
     */
    public static function png(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image/png;base64,') || strlen($dataUrl) > 3_000_000) {
            return null;
        }
        $bytes = base64_decode(substr($dataUrl, 22), true);
        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        $size = @getimagesizefromstring($bytes);

        return $size && $size[0] === 1024 && $size[1] === 1024 && $size[2] === IMAGETYPE_PNG ? $bytes : null;
    }

    /** The uploaded logo the icon is drawn from (any PNG up to 1024 px a side), kept so the app can be edited on another device. */
    public static function logo(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image/png;base64,') || strlen($dataUrl) > 4_000_000) {
            return null;
        }
        $bytes = base64_decode(substr($dataUrl, 22), true);
        $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        return $size && $size[2] === IMAGETYPE_PNG && $size[0] <= 1024 && $size[1] <= 1024 && $size[0] >= 16 && $size[1] >= 16 ? $bytes : null;
    }

    /** How the icon was made in the builder: letters or a logo, and its size. */
    public static function iconDesign(mixed $in): array
    {
        $in = is_array($in) ? $in : [];
        $letter = is_string($in['letter'] ?? null) ? mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $in['letter']) ?? ''), 0, 2) : '';
        $zoom = filter_var($in['zoom'] ?? 70, FILTER_VALIDATE_INT, ['options' => ['min_range' => 40, 'max_range' => 100]]);

        return ['mode' => ($in['mode'] ?? '') === 'image' ? 'image' : 'letter', 'letter' => $letter, 'zoom' => $zoom === false ? 70 : $zoom, 'fill' => ! empty($in['fill'])];
    }

    /**
     * Version of the app in app-shell/: raise it when phones need a new build to show something new (2: ☰ menu, 15
     * pages, slider/cards/reviews/FAQ/social/notice/space blocks, splash picture, intro). Apps built with an older
     * one are flagged in the builder.
     */
    public const SHELL_VERSION = 2;

    /** What a build is made from, besides the content (which reaches phones without a build). */
    public static function buildInfo(BuilderApp $app): array
    {
        $disk = BuilderApp::disk();
        $hash = fn (string $file) => $disk->exists($app->dir().'/'.$file) ? md5((string) $disk->get($app->dir().'/'.$file)) : null;
        $s = $app->settings;

        return [
            'shell' => self::SHELL_VERSION,
            'name' => $app->name,
            'icon' => md5($hash('icon.png').$hash('foreground.png')),
            'splash' => md5($hash('splash.png').'|'.($s['colors']['splash'] ?? '').'|'.($s['splash']['size'] ?? '')),
            'intro' => match ($s['splash']['bg'] ?? 'color') { 'image' => $hash('splash-bg.jpg'), 'video' => $hash('splash-bg.mp4'), default => null },
        ];
    }

    /**
     * What changed since the newest finished build that phones only get from a new build.
     *
     * @return list<string> e.g. ['icon', 'splash'], empty when up to date or never built
     */
    public static function outOfDate(BuilderApp $app): array
    {
        $last = $app->builds()->where('status', 'done')->latest('id')->first();
        if (! $last) {
            return [];
        }
        $was = $last->info ?? [];
        $now = self::buildInfo($app);
        $changed = [];
        if (($was['shell'] ?? 1) < $now['shell']) {
            $changed[] = 'features';
        }
        foreach (['name', 'icon', 'splash', 'intro'] as $key) {
            if (array_key_exists($key, $was) && $was[$key] !== $now[$key]) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /** Largest splash background video. */
    public const MAX_VIDEO_BYTES = 15 * 1048576;

    /** A full-screen JPEG from the builder (splash background): the bytes, or null. */
    public static function photo(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image/jpeg;base64,') || strlen($dataUrl) > 5_600_000) {
            return null;
        }
        $bytes = base64_decode(substr($dataUrl, 23), true);
        $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        return $size && $size[2] === IMAGETYPE_JPEG && min($size[0], $size[1]) >= 300 && max($size[0], $size[1]) <= 3000 ? $bytes : null;
    }

    /** Removes splash background files the app no longer uses. */
    public static function tidySplashBackground(BuilderApp $app): void
    {
        $bg = $app->settings['splash']['bg'] ?? 'color';
        foreach (['image' => 'splash-bg.jpg', 'video' => 'splash-bg.mp4'] as $kind => $file) {
            if ($bg !== $kind) {
                BuilderApp::disk()->delete($app->dir().'/'.$file);
            }
        }
    }

    /** Splash screen sizes: the picture's width on the phone in Android dp (app-shell/app.config.js imageWidth). */
    public const SPLASH_SIZES = ['small' => 120, 'medium' => 180, 'large' => 260];

    /** How the splash screen was made in the builder: the app icon, the logo alone or another picture, and its size. */
    public static function splashDesign(mixed $in): array
    {
        $in = is_array($in) ? $in : [];

        return ['mode' => in_array($in['mode'] ?? '', ['icon', 'logo', 'image'], true) ? $in['mode'] : 'icon',
            'size' => array_key_exists($in['size'] ?? '', self::SPLASH_SIZES) ? $in['size'] : 'medium',
            'bg' => in_array($in['bg'] ?? '', ['color', 'image', 'video'], true) ? $in['bg'] : 'color'];
    }

    /** https URL without user name, password or odd port; null when it isn't one. */
    public static function httpsUrl(string $url): ?string
    {
        if ($url === '' || strlen($url) > 500 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $p = parse_url($url);
        if (strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) {
            return null;
        }
        if (filter_var($p['host'], FILTER_VALIDATE_IP) || ! str_contains($p['host'], '.')) {
            return null; // a real website name, not an IP address or "localhost"
        }

        return $url;
    }

    /** The live builder site of this account that $url is on, if any. */
    public static function ownSite(string $url, BuilderAccount $account): ?BuilderSite
    {
        foreach ($account->sites()->where('status', 'live')->get() as $site) {
            $base = $site->url();
            if ($url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?') || str_starts_with($url, $base.'#')) {
                return $site;
            }
        }

        return null;
    }

    /** sites.owlvix.com (or this app's /p/ address locally): shared by every builder site. */
    public static function isBuilderHost(string $host): bool
    {
        return $host === strtolower((string) parse_url(BuilderSite::base(), PHP_URL_HOST)) || $host === strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
    }

    /** A tab URL on the same website as the app (for builder sites: the same site, not just the same host). */
    private static function sameSite(string $tabUrl, string $url, ?BuilderSite $own): bool
    {
        if ($own) {
            $base = $own->url();

            return $tabUrl === $base || str_starts_with($tabUrl, $base.'/') || str_starts_with($tabUrl, $base.'?') || str_starts_with($tabUrl, $base.'#');
        }

        return strtolower((string) parse_url($tabUrl, PHP_URL_HOST)) === strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /** The app shows pages of a website (it has "web page" tabs), so the owner must show the website is theirs. */
    public static function needsWebsite(BuilderApp $app): bool
    {
        return $app->url && collect($app->content['tabs'] ?? [])->contains('type', 'web');
    }

    /**
     * The owner may build this app: it has only native screens, or the website it shows is one of their builder
     * sites, or their website carries the verify tag.
     */
    public static function allowed(BuilderApp $app): bool
    {
        if (! self::needsWebsite($app)) {
            return true;
        }
        if (self::isBuilderHost($app->host())) {
            return self::ownSite($app->url, $app->account) !== null;
        }

        return $app->isVerified();
    }

    /** The tag the owner adds to their website to prove it is theirs. */
    public static function metaTag(BuilderApp $app): string
    {
        return '<meta name="owlvix-app" content="'.$app->verify_code.'">';
    }

    /**
     * Looks for the verify tag on the app's website.
     *
     * @return string|null an error, or null when the site is verified
     */
    public static function verify(BuilderApp $app): ?string
    {
        if (! $app->url) {
            return 'Add your website address under “More settings” first.';
        }
        if (self::isBuilderHost($app->host())) {
            return self::allowed($app) ? null : 'That page isn’t one of your published sites.';
        }
        $res = SafeFetch::get($app->url, 12);
        if (! $res['ok']) {
            return $res['error'] ?? 'We couldn’t open your website.';
        }
        if (($res['status'] ?? 0) >= 400) {
            return 'Your website answered with error '.$res['status'].'. Check the address and try again.';
        }
        $finalHost = strtolower((string) parse_url($res['url'] ?? $app->url, PHP_URL_HOST));
        if ($finalHost !== $app->host()) {
            return 'Your website sends visitors on to '.$finalHost.'. Use that address for your app instead.';
        }
        $found = false;
        if (preg_match_all('/<meta\b[^>]*>/i', (string) ($res['body'] ?? ''), $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\bname\s*=\s*["\']?owlvix-app["\'\s>]/i', $tag) && preg_match('/\bcontent\s*=\s*["\']?([a-z0-9]+)/i', $tag, $m) && hash_equals($app->verify_code, $m[1])) {
                    $found = true;
                    break;
                }
            }
        }
        if (! $found) {
            return 'We couldn’t find the tag on '.$app->host().'. Add it inside <head> on your home page, publish, then try again (clear your site’s cache if it has one).';
        }
        $app->update(['verified_host' => $app->host()]);

        return null;
    }

    /** Builds started by GitHub this calendar month (all accounts). */
    public static function monthlyCount(): int
    {
        return BuilderAppBuild::where('created_at', '>=', now()->startOfMonth())->count();
    }

    /** What the build server needs to make the app: the build-time settings, and the content at build time (so the app works offline from the start). */
    public static function job(BuilderApp $app, int $versionCode): array
    {
        $s = $app->settings;
        $disk = BuilderApp::disk();

        return [
            'name' => $app->name,
            'package' => $app->package,
            'versionName' => $s['version_name'],
            'versionCode' => $versionCode,
            'colors' => $s['colors'],
            'statusBar' => $s['status_bar'],
            'contentUrl' => AppContent::baseUrl($app).'content.json',
            'content' => AppContent::document($app),
            'icon' => base64_encode((string) $disk->get($app->dir().'/icon.png')),
            'foreground' => base64_encode((string) $disk->get($app->dir().'/foreground.png')),
            'splash' => $disk->exists($app->dir().'/splash.png') ? base64_encode((string) $disk->get($app->dir().'/splash.png')) : null,
            'splashWidth' => self::SPLASH_SIZES[$s['splash']['size'] ?? 'medium'] ?? 180,
            // A full-screen picture or video shown after the splash; the build server downloads it (AppBuildRunnerController::asset)
            'intro' => match ($s['splash']['bg'] ?? 'color') {
                'image' => $disk->exists($app->dir().'/splash-bg.jpg') ? 'image' : null,
                'video' => $disk->exists($app->dir().'/splash-bg.mp4') ? 'video' : null,
                default => null,
            },
            'keystore' => $app->keystore,
            'keystorePassword' => $app->keystore_password,
        ];
    }

    /**
     * Asks GitHub to run the build workflow.
     *
     * @return string|null an error, or null when GitHub accepted it
     */
    public static function dispatch(BuilderAppBuild $build, string $token): ?string
    {
        $repo = config('builder.apps.github_repo');
        $workflow = config('builder.apps.workflow');
        $api = rtrim(config('builder.apps.callback_url') ?: config('app.url'), '/').'/api/app-builds/'.$build->id;
        try {
            $res = Http::withToken(config('builder.apps.github_token'))
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(15)
                ->post("https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches", [
                    'ref' => config('builder.apps.ref'),
                    'inputs' => ['build_id' => (string) $build->id, 'api' => $api, 'token' => $token],
                ]);
        } catch (\Throwable $e) {
            report($e);

            return 'We couldn’t reach the build server. Please try again in a few minutes.';
        }
        if (! $res->successful()) {
            report(new \RuntimeException('App build dispatch failed: '.$res->status().' '.Str::limit($res->body(), 300)));

            return 'The build server didn’t accept the build. Please try again in a few minutes.';
        }

        return null;
    }
}
