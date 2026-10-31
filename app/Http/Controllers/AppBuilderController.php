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
        } else {
            $data['settings']['icon'] = $app->settings['icon'] ?? AppBuilder::iconDesign(null);
        }
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
        }
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
        $build = DB::transaction(function () use ($app, $token) {
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
                'job' => AppBuilder::job($app, $code),
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

    /** The icon's design and logo, to carry on editing on another device. */
    public function icon(Request $request, string $id): JsonResponse
    {
        $app = $this->own($request, $id);
        if (! $app) {
            return $this->fail('App not found.', 404);
        }
        $logo = BuilderApp::disk()->get($app->dir().'/logo.png');

        return response()->json(['ok' => true, 'design' => $app->settings['icon'] ?? AppBuilder::iconDesign(null), 'logo' => $logo ? 'data:image/png;base64,'.base64_encode($logo) : null]);
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
