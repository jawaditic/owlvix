<?php

namespace App\Http\Controllers;

use App\Mail\BuilderSignInCode;
use App\Models\BuilderAccount;
use App\Models\BuilderForm;
use App\Models\BuilderSite;
use App\Models\BuilderStat;
use App\Support\BuilderHtml;
use App\Support\LeadIntegrations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * JSON API for the free landing page builder (/landing-page-builder): email sign-in, contact forms and
 * publishing to /p/{slug}. Every answer is {ok: true, ...} or {ok: false, error}.
 */
class BuilderController extends Controller
{
    private const CODE_MINUTES = 15;

    private const CODE_ATTEMPTS = 5;

    public function sendCode(Request $request): JsonResponse
    {
        $data = $this->check($request, ['email' => ['required', 'email', 'max:150']], [
            'email.required' => 'Please enter your email.', 'email.email' => 'Please enter a valid email.',
        ]);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $email = strtolower(trim($data['email']));

        foreach (['builder-code-ip:'.$request->ip(), 'builder-code:'.sha1($email)] as $key) {
            if (RateLimiter::tooManyAttempts($key, 3)) {
                return $this->fail('Too many codes requested. Please wait a few minutes and try again.', 429);
            }
        }
        RateLimiter::hit('builder-code-ip:'.$request->ip(), 600);
        RateLimiter::hit('builder-code:'.sha1($email), 600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->codeKey($email), ['hash' => hash('sha256', $code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));
        Cache::forget($this->codeKey($email).':tries'); // a new code starts a fresh attempt count

        try {
            Mail::to($email)->send(new BuilderSignInCode($code));
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('We couldn’t send the email. Please check the address and try again.', 500);
        }

        return response()->json(['ok' => true]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $this->check($request, ['email' => ['required', 'email', 'max:150'], 'code' => ['required', 'string', 'max:10']], [
            'email.required' => 'Please enter your email.', 'email.email' => 'Please enter a valid email.', 'code.required' => 'Please enter the code from the email.',
        ]);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        if (RateLimiter::tooManyAttempts('builder-verify:'.$request->ip(), 20)) {
            return $this->fail('Too many tries. Please wait a few minutes and try again.', 429);
        }
        RateLimiter::hit('builder-verify:'.$request->ip(), 600);

        $email = strtolower(trim($data['email']));
        // Per email too, so guesses spread over many IPs still hit a wall
        if (RateLimiter::tooManyAttempts('builder-verify-email:'.sha1($email), 10)) {
            return $this->fail('Too many tries. Please wait a few minutes and try again.', 429);
        }
        RateLimiter::hit('builder-verify-email:'.sha1($email), 3600);
        $key = $this->codeKey($email);
        $saved = Cache::get($key);
        if (! $saved) {
            return $this->fail('That code has expired. Please ask for a new one.', 422);
        }
        // Count the attempt atomically before comparing, so parallel guesses can't all read "0 tries so far"
        $tries = Cache::increment($key.':tries');
        if ($tries === 1) {
            Cache::put($key.':tries', 1, now()->addMinutes(self::CODE_MINUTES));
        }
        if ($tries > self::CODE_ATTEMPTS) {
            Cache::forget($key);

            return $this->fail('Too many wrong codes. Please ask for a new one.', 422);
        }
        $code = preg_replace('/\D/', '', $data['code']);
        if (! hash_equals($saved['hash'], hash('sha256', $code))) {
            if ($tries >= self::CODE_ATTEMPTS) { // the last allowed try used up the code
                Cache::forget($key);

                return $this->fail('Too many wrong codes. Please ask for a new one.', 422);
            }

            return $this->fail('That code isn’t right. Please check the email and try again.', 422);
        }
        Cache::forget($key);
        Cache::forget($key.':tries');

        $account = BuilderAccount::firstOrCreate(['email' => $email]);
        $account->update(['last_login_at' => now()]);

        return response()->json(['ok' => true, 'token' => $account->issueToken(), 'email' => $account->email]);
    }

    public function account(Request $request): JsonResponse
    {
        $account = $this->user($request);

        return response()->json([
            'ok' => true,
            'email' => $account->email,
            'sites' => $account->sites()->orderByDesc('updated_at')->get()->map->toApi()->values(),
            'forms' => $account->forms()->withCount('leads')->oldest()->get()->map->toApi(true)->values(),
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        $data = $this->check($request, [
            'site_name' => ['required', 'string', 'max:100'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', BuilderForm::KINDS)],
            'ref' => ['nullable', 'string', 'regex:/^[a-z0-9]{6,20}$/'],
        ], [
            'site_name.required' => 'Please give your site a name first.', 'site_name.max' => 'Please use a site name under 100 characters.',
            'kind.in' => 'Unknown form type.',
        ]);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $account = $this->user($request);
        $name = trim($data['site_name']);
        $kind = $data['kind'] ?? 'contact';

        // One form per builder project (ref) and kind; older clients without a ref are matched by site name
        $ref = $data['ref'] ?? null;
        $form = $account->forms()->where('kind', $kind)
            ->when($ref, fn ($q) => $q->where('ref', $ref), fn ($q) => $q->where('site_name', $name)->whereNull('ref'))->first();
        if (! $form) {
            if ($account->forms()->count() >= BuilderForm::MAX_PER_ACCOUNT) {
                return $this->fail('You can have up to '.BuilderForm::MAX_PER_ACCOUNT.' forms.', 422);
            }
            $form = $account->forms()->create(['id' => BuilderForm::newId(), 'site_name' => $name, 'kind' => $kind, 'ref' => $ref]);
        } elseif ($form->site_name !== $name) {
            $form->update(['site_name' => $name]); // the project was renamed
        }

        return response()->json(['ok' => true, 'form' => $form->toApi()]);
    }

    public function publish(Request $request): JsonResponse
    {
        $account = $this->user($request);
        if (RateLimiter::tooManyAttempts('builder-publish:'.$account->id, 30)) {
            return $this->fail('You’ve published a lot in the last hour. Please wait a little and try again.', 429);
        }

        $data = $this->check($request, [
            'slug' => ['required', 'string', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/'],
            'name' => ['required', 'string', 'max:100'],
            'pages' => ['required', 'array', 'min:1', 'max:10'],
            'pages.*.slug' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9-]{1,40}$/', 'not_in:report'],
            'pages.*.title' => ['required', 'string', 'max:80'],
            'pages.*.html' => ['required', 'string'],
        ], [
            'slug.required' => 'Please choose an address for your site.',
            'slug.regex' => 'The address can use 3–40 lowercase letters, numbers and dashes, and can’t start or end with a dash.',
            'name.required' => 'Please give your site a name.',
            'name.max' => 'Please use a site name under 100 characters.',
            'pages.required' => 'Your site needs at least one page.',
            'pages.min' => 'Your site needs at least one page.',
            'pages.max' => 'A site can have up to 10 pages.',
            'pages.*.slug.required' => 'Every page needs an address.',
            'pages.*.slug.distinct' => 'Two pages have the same address. Please rename one.',
            'pages.*.slug.regex' => 'Page addresses can use lowercase letters, numbers and dashes (up to 40).',
            'pages.*.slug.not_in' => '“report” can’t be used as a page address.',
            'pages.*.title.required' => 'Every page needs a title.',
            'pages.*.title.max' => 'Please keep page titles under 80 characters.',
            'pages.*.html.required' => 'One of your pages is empty.',
        ]);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $slug = $data['slug'];
        if (in_array($slug, BuilderSite::RESERVED, true)) {
            return $this->fail('That address is reserved. Please choose another one.', 422);
        }

        $site = BuilderSite::where('slug', $slug)->first();
        if ($site && (int) $site->builder_account_id !== (int) $account->id) {
            return $this->fail('That address is taken. Please choose another one.', 422);
        }
        if ($site && $site->removed_by === 'admin') {
            return $this->fail('This site was taken down by Owlvix. Please contact us if you think this is a mistake.', 422);
        }
        $others = $account->sites()->when($site, fn ($q) => $q->whereKeyNot($site->id))
            ->where(fn ($q) => $q->where('status', 'live')->orWhere('removed_by', 'admin'))->count();
        if ($others >= BuilderSite::MAX_PER_ACCOUNT) {
            return $this->fail('You can publish up to '.BuilderSite::MAX_PER_ACCOUNT.' sites. Unpublish one first.', 422);
        }

        $formIds = $account->forms()->pluck('id')->all();
        foreach ($data['pages'] as $page) {
            if (strlen($page['html']) > BuilderHtml::MAX_BYTES) {
                return $this->fail('The page “'.$page['title'].'” is too big (over 2.5 MB). Try smaller images.', 422);
            }
            if ($problem = BuilderHtml::problem($page['html'], $formIds)) {
                return $this->fail('“'.$page['title'].'”: '.$problem, 422);
            }
        }
        RateLimiter::hit('builder-publish:'.$account->id, 3600);

        $pages = array_map(fn ($p) => ['slug' => $p['slug'], 'title' => trim($p['title'])], array_values($data['pages']));
        $site = DB::transaction(function () use ($site, $account, $slug, $data, $pages) {
            $site ??= new BuilderSite(['slug' => $slug]);
            $site->builder_account_id = $account->id;
            $site->fill(['name' => trim($data['name']), 'pages' => $pages, 'status' => 'live', 'removed_by' => null]);
            $site->updated_at = now(); // republishing the same pages still counts as an update
            $site->save();

            return $site;
        });

        // Replace the files: new pages written, old pages that were dropped deleted
        $disk = BuilderSite::disk();
        $keep = [];
        foreach ($data['pages'] as $page) {
            $disk->put($site->path($page['slug']), $page['html']);
            $keep[] = $site->path($page['slug']);
        }
        foreach ($disk->files((string) $site->id) as $file) {
            if (! in_array($file, $keep, true)) {
                $disk->delete($file);
            }
        }
        try {
            $site->syncPublicFiles(); // static copy on the sites subdomain, when configured
        } catch (\RuntimeException $e) {
            report($e);

            return $this->fail('That web address can’t be used. Please choose another one.', 422);
        }

        return response()->json(['ok' => true, 'url' => $site->url(), 'slug' => $site->slug]);
    }

    public function unpublish(Request $request): JsonResponse
    {
        $data = $this->check($request, ['slug' => ['required', 'string', 'max:40']]);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $site = $this->user($request)->sites()->where('slug', $data['slug'])->first();
        if (! $site) {
            return $this->fail('Site not found.', 404);
        }
        if ($site->isLive()) {
            $site->update(['status' => 'removed', 'removed_by' => 'owner']);
            $site->deleteFiles();
        }

        return response()->json(['ok' => true]);
    }

    /** A lead form's leads, newest first: JSON for the builder, or a CSV download with ?format=csv. */
    public function leads(Request $request)
    {
        $form = $this->ownForm($request, $request->query('form'));
        if (! $form) {
            return $this->fail('Form not found.', 404);
        }
        if ($request->query('format') === 'csv') {
            $name = 'leads-'.(Str::slug($form->site_name) ?: 'form').'-'.now()->toDateString().'.csv';

            return response()->streamDownload(function () use ($form) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the file as UTF-8
                fputcsv($out, ['Date', 'Name', 'Email', 'Phone', 'Step', 'Variant'], ',', '"', '');
                foreach ($form->leads()->latest('created_at')->latest('id')->lazy(500) as $lead) { // every lead, not just the newest 1,000
                    fputcsv($out, array_map([self::class, 'csvCell'], [
                        $lead->created_at?->format('Y-m-d H:i:s'), $lead->name, $lead->email, $lead->phone, $lead->step, $lead->variant,
                    ]), ',', '"', '');
                }
                fclose($out);
            }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }

        $leads = $form->leads()->latest('created_at')->latest('id')->limit(1000)->get();

        return response()->json(['ok' => true, 'total' => $form->leads()->count(), 'leads' => $leads->map->toApi()->values()]);
    }

    /** Spreadsheet apps run cells that start with = + - @ as formulas: make them plain text. */
    public static function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** Views and leads of each step and A/B version of one of your sites over the last 30 days. */
    public function stats(Request $request): JsonResponse
    {
        $slug = $request->query('slug');
        $site = is_string($slug) ? $this->user($request)->sites()->where('slug', $slug)->first() : null;
        if (! $site) {
            return $this->fail('Site not found.', 404);
        }
        $order = array_flip(array_column($site->pages ?? [], 'slug'));
        $steps = BuilderStat::where('builder_site_id', $site->id)
            ->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('step, variant, sum(views) as views, sum(leads) as leads')
            ->groupBy('step', 'variant')->get()
            ->map(fn ($r) => ['step' => $r->step, 'variant' => $r->variant, 'views' => (int) $r->views, 'leads' => (int) $r->leads])
            ->sortBy(fn ($r) => sprintf('%05d%s%s', $order[$r['step']] ?? 99999, $r['step'], $r['variant']))
            ->values();

        return response()->json(['ok' => true, 'days' => 30, 'steps' => $steps]);
    }

    public function integrations(Request $request, string $id): JsonResponse
    {
        $form = $this->ownForm($request, $id);
        if (! $form) {
            return $this->fail('Form not found.', 404);
        }

        return response()->json(['ok' => true, 'integrations' => LeadIntegrations::forApi($form->integrations)]);
    }

    public function saveIntegrations(Request $request, string $id): JsonResponse
    {
        $form = $this->ownForm($request, $id);
        if (! $form) {
            return $this->fail('Form not found.', 404);
        }
        $key = 'builder-integrations:'.$form->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return $this->fail('Too many changes. Please wait a few minutes and try again.', 429);
        }
        RateLimiter::hit($key, 600);

        $settings = LeadIntegrations::merge($form->integrations, $request->only(['mailchimp', 'brevo', 'webhook']));
        if (is_string($settings)) {
            return $this->fail($settings, 422);
        }
        $form->integrations = $settings ?: null;
        $form->save();

        return response()->json(['ok' => true, 'integrations' => LeadIntegrations::forApi($form->integrations)]);
    }

    private function ownForm(Request $request, mixed $id): ?BuilderForm
    {
        return is_string($id) && preg_match('/^[a-z0-9]{16}$/', $id) ? $this->user($request)->forms()->whereKey($id)->first() : null;
    }

    private function user(Request $request): BuilderAccount
    {
        return $request->attributes->get('builder_account');
    }

    private function codeKey(string $email): string
    {
        return 'builder-signin:'.sha1($email);
    }

    /** Validated data, or the first error as {ok: false, error}. */
    private function check(Request $request, array $rules, array $messages = []): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        return $validator->validated();
    }

    private function fail(string $error, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $error], $status);
    }
}
