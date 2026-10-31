<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BuilderAppBuilt;
use App\Models\BuilderAppBuild;
use App\Support\AppContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Called by the build workflow on GitHub Actions (.github/workflows/build-android-app.yml) for one build:
 * fetch the job, upload the APK, AAB and new signing key in parts, then say it finished. Each build has its own
 * token (Authorization: Bearer …), which stops working once the build has finished.
 */
class AppBuildRunnerController extends Controller
{
    /** Upload part size limit (the workflow sends 4 MB parts, under the usual 8 MB PHP post limit) and file size limits. */
    private const PART_BYTES = 5 * 1024 * 1024;

    private const MAX_BYTES = ['apk' => 200 * 1024 * 1024, 'aab' => 200 * 1024 * 1024, 'keystore' => 20 * 1024];

    public function job(Request $request, BuilderAppBuild $build): JsonResponse
    {
        $this->authorizeRunner($request, $build);
        if ($build->status === 'queued') {
            $build->update(['status' => 'building', 'started_at' => now()]);
        }

        return response()->json(['ok' => true, 'job' => $build->job]);
    }

    /** The app's intro picture or video (too big to send inside the job). */
    public function asset(Request $request, BuilderAppBuild $build)
    {
        $this->authorizeRunner($request, $build);
        $file = ['image' => 'splash-bg.jpg', 'video' => 'splash-bg.mp4'][$build->job['intro'] ?? ''] ?? null;
        $path = $file ? $build->app->dir().'/'.$file : null;
        abort_unless($path && \App\Models\BuilderApp::disk()->exists($path), 404);

        return \App\Models\BuilderApp::disk()->response($path);
    }

    public function upload(Request $request, BuilderAppBuild $build): JsonResponse
    {
        $this->authorizeRunner($request, $build);
        $kind = (string) $request->query('kind');
        $part = filter_var($request->query('part'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        abort_unless(isset(self::MAX_BYTES[$kind]) && $part !== false, 422, 'Bad upload.');
        $body = $request->getContent();
        abort_if(strlen($body) === 0 || strlen($body) > self::PART_BYTES, 422, 'Bad upload part.');

        $path = $this->tmpPath($build, $kind);
        File::ensureDirectoryExists(dirname($path));
        if ($part === 0) {
            File::put($path, ''); // a retried build starts the file again
        } elseif (! is_file($path) || ((int) $request->query('offset')) !== filesize($path)) {
            abort(409, 'Parts out of order.');
        }
        abort_if(filesize($path) + strlen($body) > self::MAX_BYTES[$kind], 413, 'File too large.');
        file_put_contents($path, $body, FILE_APPEND | LOCK_EX);

        return response()->json(['ok' => true, 'bytes' => filesize($path)]);
    }

    public function finish(Request $request, BuilderAppBuild $build): JsonResponse
    {
        $this->authorizeRunner($request, $build);
        $app = $build->app;

        if (! $app->isLive()) {
            $this->finishAs($build, 'failed', 'The app was taken down.');

            return response()->json(['ok' => false, 'error' => 'app removed'], 410);
        }
        if ($request->input('status') !== 'done') {
            $detail = Str::limit((string) $request->input('error', ''), 300, '');
            report(new \RuntimeException("App build {$build->id} ({$app->package}) failed: {$detail}"));
            $this->finishAs($build, 'failed', 'The build didn’t finish. We’ve been told about it — please try again, or contact us if it keeps happening.');

            return response()->json(['ok' => true]);
        }

        // Each file must arrive whole: same SHA-256 as the build server saw
        $files = [];
        foreach (['apk', 'aab'] as $kind) {
            $tmp = $this->tmpPath($build, $kind);
            $sha = strtolower((string) $request->input("sha256.$kind"));
            if (! is_file($tmp) || ! preg_match('/^[0-9a-f]{64}$/', $sha) || ! hash_equals($sha, hash_file('sha256', $tmp))) {
                $this->finishAs($build, 'failed', 'The app files didn’t arrive in one piece. Please try again.');

                return response()->json(['ok' => false, 'error' => "$kind missing or damaged"], 422);
            }
            $files[$kind] = $tmp;
        }

        $keystore = $this->tmpPath($build, 'keystore');
        if ($app->keystore === null) {
            if (! is_file($keystore) || filesize($keystore) < 100) {
                $this->finishAs($build, 'failed', 'The build didn’t make a signing key. Please try again.');

                return response()->json(['ok' => false, 'error' => 'keystore missing'], 422);
            }
            $app->keystore = base64_encode((string) file_get_contents($keystore));
        }

        // The files go to the app's public folder under a secret name, so downloads are plain static files
        $folder = 'builds/'.Str::random(32);
        $name = (Str::slug($app->name) ?: 'app').'-'.preg_replace('/[^0-9.]/', '', $build->version_name);
        $name = trim(str_replace('.', '-', $name), '-');
        $saved = [];
        File::ensureDirectoryExists(AppContent::dir($app).'/'.$folder);
        foreach ($files as $kind => $tmp) {
            $path = "$folder/$name.$kind";
            File::move($tmp, AppContent::dir($app).'/'.$path);
            $saved[$kind] = ['bytes' => filesize(AppContent::dir($app).'/'.$path), 'path' => $path];
        }
        File::put(AppContent::root().'/.htaccess', AppContent::htaccess());
        $app->version_code = max($app->version_code, $build->version_code);
        $app->save();
        $build->forceFill(['files' => $saved])->save();
        $this->finishAs($build, 'done');

        // Keep the files of the newest 3 builds only
        foreach ($app->builds()->where('status', 'done')->latest('id')->skip(3)->take(50)->get() as $old) {
            $old->deleteFiles();
        }

        return response()->json(['ok' => true]);
    }

    private function finishAs(BuilderAppBuild $build, string $status, ?string $error = null): void
    {
        $build->update(['status' => $status, 'error' => $error, 'token_hash' => null, 'job' => null, 'finished_at' => now()]);
        File::deleteDirectory(dirname($this->tmpPath($build, 'apk')));
        try {
            Mail::to($build->app->account->email)->send(new BuilderAppBuilt($build->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function tmpPath(BuilderAppBuild $build, string $kind): string
    {
        return storage_path('app/app-uploads/'.$build->id.'/'.$kind);
    }

    private function authorizeRunner(Request $request, BuilderAppBuild $build): void
    {
        $token = (string) $request->bearerToken();
        abort_unless($build->token_hash && $build->isRunning() && $token !== '' && hash_equals($build->token_hash, hash('sha256', $token)), 403);
        abort_if($build->expireIfStuck(), 403);
    }
}
