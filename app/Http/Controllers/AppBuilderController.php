<?php

namespace App\Http\Controllers;

use App\Models\BuilderAccount;
use App\Models\BuilderApp;
use App\Models\BuilderAppBuild;
use App\Support\AppBuilder;
use App\Support\AppContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * JSON API for the mobile app builder (/mobile-app-builder, public/js/app-builder.js), under the same email
 * sign-in as the page builder: save an app (its screens go live on phones straight away), prove the website is
 * yours when the app shows it, start an Android build, get the files.
 */
class AppBuilderController extends Controller
{
    private const REMOVED = 'This app was taken down because it was reported and broke our rules. If you think this is a mistake, reply to any email from us.';

    public function index(Request $request): JsonResponse
    {
        $account = $this->user($request);

        return response()->json([
            'ok' => true,
            'apps' => $account->apps()->oldest()->get()->map->toApi()->values(),
            'enabled' => AppBuilder::enabled(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $account = $this->user($request);
        $app = null;
        if ($request->filled('id')) {
            $app = $this->own($request, $request->input('id'));
            if (! $app) {
                return $this->fail('App not found. It may have been deleted, or it belongs to another email address.', 404);
            }
            if (! $app->isLive()) {
                return $this->fail(self::REMOVED, 403);
            }
        } elseif ($account->apps()->count() >= BuilderApp::MAX_PER_ACCOUNT) {
            return $this->fail('You can have up to '.BuilderApp::MAX_PER_ACCOUNT.' apps. Delete one you no longer need first.', 422);
        }

        $data = AppBuilder::clean($request->input('app'), $account, $app);
        if (is_string($data)) {
            return $this->fail($data, 422);
        }
        $images = $data['images'];
        // rev: a bigger number on every save, so phones know which copy is newest
        $data['content'] = ['rev' => max((int) floor(microtime(true) * 1000), ($app->content['rev'] ?? 0) + 1), 'tabs' => $data['tabs']];
        unset($data['images'], $data['tabs']);
        $icon = $foreground = null;
        if ($request->has('icon') || ! $app) {
            $icon = AppBuilder::png($request->input('icon'));
            $foreground = AppBuilder::png($request->input('foreground'));
            if (! $icon || ! $foreground) {
                return $this->fail('Please add an app icon.', 422);
            }
            $design = AppBuilder::iconDesign($request->input('design'));
            $logo = $design['mode'] === 'image' ? AppBuilder::logo($request->input('logo')) : null;
            $data['settings']['icon'] = $design;
            // The splash picture (drawn by the builder like the icon); without one the build uses the icon's logo
            $notification = $request->has('notification_icon') ? AppBuilder::pngOfSize($request->input('notification_icon'), 96) : null;
            $splash = $request->has('splash') ? AppBuilder::png($request->input('splash')) : null;
            if ($request->has('splash') && ! $splash) {
                return $this->fail('Please check your splash screen picture.', 422);
            }
            $splashDesign = AppBuilder::splashDesign($request->input('splash_design'));
            $splashImage = $splashDesign['mode'] === 'image' ? AppBuilder::logo($request->input('splash_image')) : null;
            if ($splashDesign['mode'] === 'image' && ! $splashImage) {
                $splashDesign['mode'] = 'icon';
            }
            $data['settings']['splash'] = $splashDesign;
        } else {
            $data['settings']['icon'] = $app->settings['icon'] ?? AppBuilder::iconDesign(null);
            $data['settings']['splash'] = $app->settings['splash'] ?? AppBuilder::splashDesign(null);
        }
        // The intro after the splash: nothing (just the colour), a full-screen picture, or a video (uploaded on its own)
        $prevBg = $app->settings['splash']['bg'] ?? 'color';
        $bgMode = $request->input('splash_bg_mode');
        $bgPhoto = null;
        if ($bgMode === 'image' && $request->filled('splash_bg')) {
            $bgPhoto = AppBuilder::photo($request->input('splash_bg'));
            if (! $bgPhoto) {
                return $this->fail('The splash background picture must be a JPG up to 4 MB, at least 300 pixels wide.', 422);
            }
        }
        $data['settings']['splash']['bg'] = match ($bgMode) {
            'color' => 'color',
            'image' => $bgPhoto || ($app && BuilderApp::disk()->exists($app->dir().'/splash-bg.jpg')) ? 'image' : $prevBg,
            'video' => $app && BuilderApp::disk()->exists($app->dir().'/splash-bg.mp4') ? 'video' : $prevBg,
            default => $prevBg,
        };
        if (! $app && RateLimiter::tooManyAttempts('app-new:'.$account->id, 10)) {
            return $this->fail('You have made a lot of apps today. Please try again tomorrow.', 429);
        }

        try {
            $app = DB::transaction(function () use ($app, $account, $data) {
                if (! $app) {
                    RateLimiter::hit('app-new:'.$account->id, 86400);
                    $app = new BuilderApp(['id' => BuilderApp::newId(), 'verify_code' => Str::lower(Str::random(24)), 'keystore_password' => Str::random(32)]);
                    $app->account()->associate($account);
                }
                $app->fill($data)->save();

                return $app;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->fail('That app ID is already used by another app. Please pick a different one.', 422);
        }
        if ($icon) {
            BuilderApp::disk()->put($app->dir().'/icon.png', $icon);
            BuilderApp::disk()->put($app->dir().'/foreground.png', $foreground);
            $logo ? BuilderApp::disk()->put($app->dir().'/logo.png', $logo) : BuilderApp::disk()->delete($app->dir().'/logo.png');
            $splash ? BuilderApp::disk()->put($app->dir().'/splash.png', $splash) : BuilderApp::disk()->delete($app->dir().'/splash.png');
            if ($notification) {
                BuilderApp::disk()->put($app->dir().'/notification.png', $notification);
            }
            $splashImage ? BuilderApp::disk()->put($app->dir().'/splash-source.png', $splashImage) : BuilderApp::disk()->delete($app->dir().'/splash-source.png');
        }
        if ($bgPhoto) {
            BuilderApp::disk()->put($app->dir().'/splash-bg.jpg', $bgPhoto);
        }
        AppBuilder::tidySplashBackground($app);
        try {
            AppContent::publish($app, $images);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Your app was saved, but we couldn’t update what phones see. Please save again in a minute.', 500);
        }

        return response()->json(['ok' => true, 'app' => $app->fresh()->toApi(), 'tag' => AppBuilder::metaTag($app)]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        if (RateLimiter::tooManyAttempts('app-verify:'.$app->id, 10)) {
            return $this->fail('Too many checks. Please wait a few minutes and try again.', 429);
        }
        RateLimiter::hit('app-verify:'.$app->id, 600);
        if ($error = AppBuilder::verify($app)) {
            return $this->fail($error, 422);
        }

        return response()->json(['ok' => true, 'app' => $app->fresh()->toApi()]);
    }

    public function build(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        if (! $app->isLive()) {
            return $this->fail(self::REMOVED, 403);
        }
        if (! AppBuilder::enabled()) {
            return $this->fail('App builds are switched off right now. Please try again later, or ask us to build it for you.', 503);
        }
        if (! AppBuilder::allowed($app)) {
            return $this->fail('First show us the website is yours: add the verify tag to it and press “Check my website”.', 422);
        }
        if (! $app->hasIcon()) {
            return $this->fail('Please add an app icon.', 422);
        }
        $running = $app->builds()->whereIn('status', ['queued', 'building'])->get()->reject->expireIfStuck();
        if ($running->isNotEmpty()) {
            return $this->fail('This app is already being built. You’ll get an email when it’s ready.', 422);
        }
        $account = $this->user($request);
        $today = BuilderAppBuild::whereIn('builder_app_id', $account->apps()->pluck('id'))->where('created_at', '>=', now()->subDay())->count();
        if ($today >= AppBuilder::DAILY_BUILDS) {
            return $this->fail('You can build up to '.AppBuilder::DAILY_BUILDS.' times a day. Please try again tomorrow.', 429);
        }
        if (AppBuilder::monthlyCount() >= config('builder.apps.monthly_limit')) {
            return $this->fail('We’ve reached this month’s free builds. Please try again next month, or ask us to build it for you.', 429);
        }

        $token = Str::random(64);
        $push = AppBuilder::pushConfig($app); // (asks Firebase the first time, so not inside the transaction)
        $build = DB::transaction(function () use ($app, $token, $push) {
            $app = BuilderApp::whereKey($app->id)->lockForUpdate()->first();
            if ($app->builds()->whereIn('status', ['queued', 'building'])->exists()) {
                return null; // another tab started one a moment ago
            }
            $code = $app->version_code + 1; // Google Play needs a bigger number for every upload

            return $app->builds()->create([
                'status' => 'queued',
                'version_code' => $code,
                'version_name' => $app->settings['version_name'],
                'token_hash' => hash('sha256', $token),
                'job' => AppBuilder::job($app, $code, $push),
                'info' => AppBuilder::buildInfo($app) + ['push' => $push !== null],
            ]);
        });
        if (! $build) {
            return $this->fail('This app is already being built. You’ll get an email when it’s ready.', 422);
        }
        if ($error = AppBuilder::dispatch($build, $token)) {
            $build->update(['status' => 'failed', 'error' => $error, 'token_hash' => null, 'job' => null, 'finished_at' => now()]);

            return $this->fail($error, 502);
        }

        return response()->json(['ok' => true, 'app' => $app->fresh()->toApi()]);
    }

    /** Sends a push notification to everyone with the app (Firebase Cloud Messaging, the app's topic). */
    public function push(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        if (! $app->isLive()) {
            return $this->fail(self::REMOVED, 403);
        }
        if (! \App\Support\Firebase::enabled()) {
            return $this->fail('Notifications are switched off right now. Please try again later.', 503);
        }
        $last = $app->builds()->where('status', 'done')->latest('id')->first();
        if (empty($last?->info['push'])) {
            return $this->fail('Build your app again first — the version on phones can’t receive notifications yet. Then upload the new .aab to Google Play.', 422);
        }
        $title = AppContent::str($request->input('title'));
        $body = AppContent::str($request->input('body'));
        $tab = $request->input('tab');
        $tabs = count($app->content['tabs'] ?? []);
        if ($title === '' || mb_strlen($title) > 65 || $body === '' || mb_strlen($body) > 240) {
            return $this->fail('Please write a title (up to 65 characters) and a message (up to 240).', 422);
        }
        $tab = $tab === null || $tab === '' ? null : filter_var($tab, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => max(0, $tabs - 1)]]);
        if ($tab === false) {
            return $this->fail('That page isn’t in your app any more.', 422);
        }
        $limit = (int) config('builder.apps.pushes_per_day');
        if (\App\Models\BuilderAppPush::where('builder_app_id', $app->id)->where('status', 'sent')->where('created_at', '>=', now()->subDay())->count() >= $limit) {
            return $this->fail("You can send up to {$limit} notifications a day — people turn notifications off when they get too many.", 429);
        }
        try {
            \App\Support\Firebase::send(\App\Support\Firebase::topic($app->id), $title, $body, $tab === null ? [] : ['tab' => (string) $tab]);
            $sent = $app->pushes()->create(['title' => $title, 'body' => $body, 'tab' => $tab, 'status' => 'sent']);
        } catch (\Throwable $e) {
            report($e);
            $app->pushes()->create(['title' => $title, 'body' => $body, 'tab' => $tab, 'status' => 'failed', 'error' => Str::limit($e->getMessage(), 290)]);

            return $this->fail('We couldn’t send it right now. Please try again in a few minutes.', 502);
        }

        return response()->json(['ok' => true, 'push' => $sent->toApi(), 'pushes' => $app->pushes()->latest('id')->take(20)->get()->map->toApi()]);
    }

    /** The app's usage over the last 30 days (anonymous daily totals sent by phones, Api\AppStatsController). */
    public function stats(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        // The 30 finished days (phones send each day's numbers the day after, so today is never complete)
        $from = now()->subDays(30)->startOfDay();
        $to = now()->subDay()->startOfDay();
        $rows = \Illuminate\Support\Facades\DB::table('builder_app_stats')->where('builder_app_id', $app->id)->where('day', '>=', $from->toDateString())->get()->keyBy(fn ($r) => substr((string) $r->day, 0, 10));
        $days = [];
        $pages = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $r = $rows[$d->toDateString()] ?? null;
            $days[] = ['day' => $d->toDateString(), 'active' => (int) ($r->active ?? 0), 'opens' => (int) ($r->opens ?? 0), 'installs' => (int) ($r->installs ?? 0)];
            foreach (json_decode((string) ($r->pages ?? ''), true) ?: [] as $i => $n) {
                $pages[$i] = ($pages[$i] ?? 0) + $n;
            }
        }
        arsort($pages);
        $labels = array_column($app->content['tabs'] ?? [], 'label');
        $week = array_slice($days, -7);

        return response()->json(['ok' => true, 'days' => $days,
            'installs' => (int) \Illuminate\Support\Facades\DB::table('builder_app_stats')->where('builder_app_id', $app->id)->sum('installs'),
            'daily_users' => round(array_sum(array_column($week, 'active')) / 7, 1),
            'opens' => array_sum(array_column($days, 'opens')),
            'pages' => collect($pages)->map(fn ($n, $i) => ['page' => $labels[$i] ?? 'Page '.($i + 1), 'views' => $n])->values()->take(10)]);
    }

    public function pushes(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }

        return response()->json(['ok' => true, 'pushes' => $app->pushes()->latest('id')->take(20)->get()->map->toApi()]);
    }

    /** The icon's and splash screen's design and pictures, to carry on editing on another device. */
    public function icon(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        $logo = BuilderApp::disk()->get($app->dir().'/logo.png');
        $splash = BuilderApp::disk()->get($app->dir().'/splash-source.png');
        $bg = $app->settings['splash']['bg'] ?? 'color';
        $bgPhoto = $bg === 'image' ? BuilderApp::disk()->get($app->dir().'/splash-bg.jpg') : null;

        return response()->json(['ok' => true, 'design' => $app->settings['icon'] ?? AppBuilder::iconDesign(null), 'logo' => $logo ? 'data:image/png;base64,'.base64_encode($logo) : null,
            'splash_design' => $app->settings['splash'] ?? AppBuilder::splashDesign(null), 'splash_image' => $splash ? 'data:image/png;base64,'.base64_encode($splash) : null,
            'splash_bg' => $bgPhoto ? 'data:image/jpeg;base64,'.base64_encode($bgPhoto) : null]);
    }

    /**
     * The splash background video, sent in parts (each under the usual 8 MB PHP post limit): ?offset= where this
     * part starts and ?total= the whole size. When the last part arrives the video replaces the old one.
     */
    public function splashVideo(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        if (! $app->isLive()) {
            return $this->fail(self::REMOVED, 403);
        }
        $offset = filter_var($request->query('offset'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $total = filter_var($request->query('total'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000, 'max_range' => AppBuilder::MAX_VIDEO_BYTES]]);
        $body = $request->getContent();
        if ($offset === false || $total === false) {
            return $this->fail('The video must be an MP4 up to '.(AppBuilder::MAX_VIDEO_BYTES / 1048576).' MB.', 422);
        }
        if ($body === '' || strlen($body) > 3 * 1048576 || $offset + strlen($body) > $total) {
            return $this->fail('That part of the video didn’t arrive right. Please try again.', 422);
        }
        $tmp = storage_path('app/app-uploads/splash-'.$app->id.'.part');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($tmp));
        if ($offset === 0) {
            if (strlen($body) < 12 || substr($body, 4, 4) !== 'ftyp') {
                return $this->fail('Please pick an MP4 video.', 422);
            }
            file_put_contents($tmp, '');
        } elseif (! is_file($tmp) || filesize($tmp) !== $offset) {
            return $this->fail('That part of the video didn’t arrive right. Please try again.', 409);
        }
        file_put_contents($tmp, $body, FILE_APPEND | LOCK_EX);
        if (filesize($tmp) < $total) {
            return response()->json(['ok' => true, 'received' => filesize($tmp)]);
        }
        BuilderApp::disk()->put($app->dir().'/splash-bg.mp4', fopen($tmp, 'rb'));
        @unlink($tmp);
        $settings = $app->settings;
        $settings['splash'] = ['bg' => 'video'] + ($settings['splash'] ?? AppBuilder::splashDesign(null));
        $app->update(['settings' => $settings]);
        AppBuilder::tidySplashBackground($app);

        return response()->json(['ok' => true, 'app' => $app->fresh()->toApi()]);
    }

    /** The upload key and its password, so the owner can keep a copy (Google Play needs the same key for updates). */
    public function keystore(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app || $app->keystore === null) {
            return $this->fail('Your signing key is made by the first build.', 404);
        }

        return response()->json(['ok' => true, 'keystore' => $app->keystore, 'password' => $app->keystore_password, 'alias' => 'upload', 'file' => Str::slug($app->name ?: 'app').'-upload.keystore']);
    }

    public function delete(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        $app->deleteFiles();
        $app->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * A finished build's APK or AAB, from a signed link (email, or the builder page). The file itself is a static
     * file at a secret address, so the web server sends it (no PHP worker busy for the whole download).
     */
    public function download(Request $request, BuilderAppBuild $build, string $kind)
    {
        $file = $build->filePath($kind);
        abort_unless($file && is_file($file), 404);

        return redirect()->away($build->fileUrl($kind))->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
    }

    /** The app's privacy policy, written from what the app does (Google Play asks every app for one). */
    public function privacy(string $id)
    {
        $app = BuilderApp::where('status', 'live')->findOrFail($id);

        return response()->view('builder.app-privacy', ['app' => $app, 'use' => AppBuilder::dataUse($app), 'p' => $app->settings['privacy'] ?? []])
            ->header('X-Robots-Tag', 'noindex');
    }

    /** Anyone can report an app (linked at the bottom of the app's ☰ menu); the admin is emailed. */
    public function reportForm(string $id)
    {
        $app = BuilderApp::findOrFail($id);

        return response()->view('builder.report-app', ['app' => $app])->header('X-Robots-Tag', 'noindex');
    }

    public function report(Request $request, string $id)
    {
        $app = BuilderApp::where('status', 'live')->findOrFail($id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'max:0'], // honeypot
        ], [
            'reason.required' => 'Please tell us what’s wrong with this app.',
            'reason.min' => 'Please tell us a little more.',
            'email.email' => 'Please enter a valid email address.',
            'website.max' => 'Something went wrong. Please try again.',
        ]);
        $key = 'app-report:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['reason' => 'Too many reports from here. Please try again later.'])->withInput();
        }
        RateLimiter::hit($key, 3600);
        DB::table('builder_apps')->where('id', $app->id)->update(['reported_at' => now(), 'reports' => DB::raw('reports + 1')]);
        try {
            if ($owner = config('admin.email')) {
                \Illuminate\Support\Facades\Mail::to($owner)->send(new \App\Mail\BuilderAppReported($app->fresh(), $data['reason'], $data['email'] ?? null));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('builder.apps.report', $app->id)->with('reported', true);
    }

    private function own(Request $request, mixed $id): ?BuilderApp
    {
        return is_string($id) && preg_match('/^[a-z0-9]{16}$/', $id) ? $this->user($request)->apps()->whereKey($id)->first() : null;
    }

    private function user(Request $request): BuilderAccount
    {
        return $request->attributes->get('builder_account');
    }

    private function fail(string $error, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error], $status);
    }
}
