<?php

namespace App\Http\Controllers;

use App\Mail\BuilderFormMessage;
use App\Mail\BuilderLeadMessage;
use App\Models\BuilderLead;
use App\Models\BuilderStat;
use App\Support\LeadIntegrations;
use App\Mail\BuilderSiteReported;
use App\Models\BuilderForm;
use App\Models\BuilderSite;
use App\Support\BuilderHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * The public side of the free landing page builder: contact forms posted from builder pages (hosted anywhere,
 * no JavaScript), pages published at /p/{slug}, and the "Report this page" form.
 */
class BuilderPublicController extends Controller
{
    private const FORM_PER_IP_HOUR = 5;

    private const FORM_PER_DAY = 50;

    private const MAX_LINKS = 3;

    private const LEADS_PER_IP_HOUR = 10;

    private const LEADS_PER_DAY = 500;

    private const BEACON_PER_MINUTE = 120;

    public function submit(Request $request, string $id)
    {
        $form = $this->form($id);
        if ($form->isLeads()) {
            return $this->submitLead($request, $form);
        }
        $back = $this->safeUrl($request->input('_back'));
        $sent = redirect()->route('builder.form.sent', ['id' => $form->id, 'back' => $back], 303);

        // Bots fill the hidden field: pretend it worked
        if (filled($request->input('_hp'))) {
            return $sent;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ], [
            'name.required' => 'Please enter your name.',
            'name.max' => 'Please keep your name under 100 characters.',
            'email.required' => 'Please enter your email.',
            'email.email' => 'Please enter a valid email address.',
            'message.required' => 'Please write a message.',
            'message.min' => 'Please write a message.',
            'message.max' => 'Please keep your message under 5000 characters.',
        ]);
        if ($validator->fails()) {
            return $this->problem($form, $back, $validator->errors()->all(), 422);
        }
        $data = $validator->validated();
        if (preg_match_all('#https?://|www\.#i', $data['message']) > self::MAX_LINKS) {
            return $this->problem($form, $back, ['Please include no more than '.self::MAX_LINKS.' links in your message.'], 422);
        }

        $ipKey = 'builder-form:'.$form->id.':'.$request->ip();
        $dayKey = 'builder-form-day:'.$form->id;
        if (RateLimiter::tooManyAttempts($ipKey, self::FORM_PER_IP_HOUR) || RateLimiter::tooManyAttempts($dayKey, self::FORM_PER_DAY)) {
            return $this->problem($form, $back, ['Too many messages have been sent from here. Please try again later.'], 429);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($dayKey, 86400);

        try {
            Mail::to($form->account->email)->send(new BuilderFormMessage($form, [
                'name' => trim($data['name']),
                'email' => $data['email'],
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
                'message' => trim($data['message']),
                'page' => $back,
            ]));
        } catch (\Throwable $e) {
            report($e);

            return $this->problem($form, $back, ['Your message couldn’t be sent right now. Please try again in a few minutes.'], 503);
        }

        DB::table('builder_forms')->where('id', $form->id)->update([
            'submissions' => DB::raw('submissions + 1'),
            'last_submission_at' => now(),
        ]);

        return $sent;
    }

    /**
     * A funnel's opt-in form: the lead is stored for the owner, emailed to them, counted for the step it came from,
     * and passed to the owner's email tools after the response. The visitor goes on to the funnel's next step.
     */
    private function submitLead(Request $request, BuilderForm $form)
    {
        $back = $this->safeUrl($request->input('_back'));
        $nextUrl = $this->nextUrl($request->input('_next'), $request);
        $done = $nextUrl
            ? redirect()->away($nextUrl, 303)
            : redirect()->route('builder.form.sent', ['id' => $form->id, 'back' => $back], 303);

        // Bots fill the hidden field: pretend it worked
        if (filled($request->input('_hp'))) {
            return $done;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.max' => 'Please keep your name under 100 characters.',
            'email.required' => 'Please enter your email.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Please enter a shorter email address.',
            'phone.max' => 'Please enter a shorter phone number.',
        ]);
        if ($validator->fails()) {
            return $this->problem($form, $back, $validator->errors()->all(), 422);
        }
        $data = $validator->validated();
        foreach (['name', 'email', 'phone'] as $field) {
            if (preg_match_all('#https?://|www\.#i', (string) ($data[$field] ?? '')) > self::MAX_LINKS) {
                return $this->problem($form, $back, ['Please include no more than '.self::MAX_LINKS.' links.'], 422);
            }
        }

        // Limits are counted by a hash of the IP; the IP itself is never stored
        $ipKey = 'builder-lead:'.$form->id.':'.sha1($request->ip().'|'.config('app.key'));
        $dayKey = 'builder-lead-day:'.$form->id;
        if (RateLimiter::tooManyAttempts($ipKey, self::LEADS_PER_IP_HOUR) || RateLimiter::tooManyAttempts($dayKey, self::LEADS_PER_DAY)) {
            return $this->problem($form, $back, ['Too many sign-ups have been sent from here. Please try again later.'], 429);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($dayKey, 86400);

        $step = $request->input('_step');
        $step = is_string($step) && preg_match('/^[a-z0-9-]{1,40}$/', $step) ? $step : null;
        $variant = in_array($request->input('_v'), ['a', 'b'], true) ? $request->input('_v') : null;
        $slug = $request->input('_site');
        $site = is_string($slug) && preg_match('/^[a-z0-9-]{3,40}$/', $slug)
            ? BuilderSite::where('slug', $slug)->where('status', 'live')->where('builder_account_id', $form->builder_account_id)->first()
            : null;

        $lead = $form->leads()->create([
            'builder_site_id' => $site?->id,
            'step' => $step,
            'variant' => $variant,
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
            'email' => trim($data['email']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
        ]);
        if ($site && $step && $site->hasPage($step)) {
            BuilderStat::bump($site->id, $step, $variant ?? 'a', 'leads');
        }
        DB::table('builder_forms')->where('id', $form->id)->update([
            'submissions' => DB::raw('submissions + 1'),
            'last_submission_at' => now(),
        ]);

        // The owner's email and the email tools run after the visitor has been sent on — no waiting on SMTP
        $ownerEmail = $form->account->email;
        \Illuminate\Support\defer(function () use ($ownerEmail, $form, $lead, $site, $back) {
            try {
                Mail::to($ownerEmail)->send(new BuilderLeadMessage($form, $lead, $site, $back));
            } catch (\Throwable $e) {
                report($e); // the lead is saved either way
            }
        });

        try {
            $hasIntegrations = (bool) $form->integrations;
        } catch (\Throwable $e) { // e.g. APP_KEY rotated: the saved keys can't be read — never fail the visitor
            report($e);
            $hasIntegrations = false;
        }
        if ($hasIntegrations) {
            $payload = [
                'email' => $lead->email, 'name' => $lead->name, 'phone' => $lead->phone,
                'funnel' => $site?->slug, 'step' => $step, 'variant' => $variant,
                'created_at' => $lead->created_at->toIso8601String(),
            ];
            \Illuminate\Support\defer(fn () => LeadIntegrations::send($form, $payload));
        }

        return $done;
    }

    /**
     * Stats beacon from published funnel pages (navigator.sendBeacon, body {"s": site, "p": step, "v": "a"|"b"}).
     * Only counts; nothing about the visitor is stored. Always answers 204.
     */
    public function beacon(Request $request)
    {
        $res = response()->noContent()->header('Access-Control-Allow-Origin', '*');
        $raw = $request->getContent();
        $ua = (string) $request->userAgent();
        if ($raw === '' || strlen($raw) > 300 || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed/i', $ua)) {
            return $res;
        }
        $key = 'builder-fs:'.sha1($request->ip().'|'.config('app.key'));
        if (RateLimiter::tooManyAttempts($key, self::BEACON_PER_MINUTE)) {
            return $res;
        }
        RateLimiter::hit($key, 60);

        $in = json_decode($raw, true);
        if (! is_array($in) || ! is_string($in['s'] ?? null) || ! is_string($in['p'] ?? null)
            || ! preg_match('/^[a-z0-9-]{3,40}$/', $in['s']) || ! preg_match('/^[a-z0-9-]{1,40}$/', $in['p'])) {
            return $res;
        }
        $variant = ($in['v'] ?? 'a') === 'b' ? 'b' : 'a';

        $site = BuilderSite::where('slug', $in['s'])->where('status', 'live')->first(['id', 'pages']);
        if ($site && $site->hasPage($in['p'])) {
            try {
                BuilderStat::bump($site->id, $in['p'], $variant, 'views');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $res;
    }

    public function sent(Request $request, string $id)
    {
        $form = $this->form($id);

        return response()->view('builder.sent', ['form' => $form, 'back' => $this->safeUrl($request->query('back'))])
            ->header('X-Robots-Tag', 'noindex');
    }

    public function show(string $slug, ?string $page = null)
    {
        $site = BuilderSite::where('slug', $slug)->where('status', 'live')->first();
        abort_unless($site, 404);
        $page ??= $site->homeSlug();
        abort_unless($page && $site->hasPage($page), 404);

        $html = BuilderSite::disk()->get($site->path($page));
        abort_unless(is_string($html), 404);

        DB::table('builder_sites')->where('id', $site->id)->increment('views');

        return response(BuilderHtml::withBar($html, $site), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => BuilderHtml::csp(),
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    public function reportForm(string $slug)
    {
        $site = BuilderSite::where('slug', $slug)->firstOrFail();

        return response()->view('builder.report', ['site' => $site])->header('X-Robots-Tag', 'noindex');
    }

    public function report(Request $request, string $slug)
    {
        $site = BuilderSite::where('slug', $slug)->where('status', 'live')->firstOrFail();
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'max:0'], // honeypot
        ], [
            'reason.required' => 'Please tell us what’s wrong with this page.',
            'reason.min' => 'Please tell us a little more.',
            'email.email' => 'Please enter a valid email address.',
            'website.max' => 'Something went wrong. Please try again.',
        ]);

        $key = 'builder-report:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['reason' => 'Too many reports from here. Please try again later.'])->withInput();
        }
        RateLimiter::hit($key, 3600);

        DB::table('builder_sites')->where('id', $site->id)->update(['reported_at' => now(), 'reports' => DB::raw('reports + 1')]);
        $site->refresh();

        try {
            if ($owner = config('admin.email')) {
                Mail::to($owner)->send(new BuilderSiteReported($site, $data['reason'], $data['email'] ?? null));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('builder.report', $site->slug)->with('reported', true);
    }

    private function form(string $id): BuilderForm
    {
        $form = BuilderForm::whereKey($id)->whereNull('disabled_at')->first();
        abort_unless($form, 404);

        return $form;
    }

    /** Only http(s) addresses are linked back to. */
    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 2000 || ! preg_match('#^https?://[^\s/]+#i', $url)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * The funnel's next step, if it's somewhere we'll send people: http(s) on the builder sites domain, this app, or the
     * host the browser says the form was posted from (Referer). Never the client-supplied _back host — both fields come
     * from the same request, so trusting it would make /f/{id} an open redirect.
     */
    private function nextUrl(mixed $url, Request $request): ?string
    {
        $url = $this->safeUrl($url);
        if (! $url) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = array_filter([
            strtolower((string) config('builder.domain')),
            strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST)),
            strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)),
            strtolower(request()->getHost()),
        ]);

        return $host !== '' && in_array($host, $allowed, true) ? $url : null;
    }

    private function problem(BuilderForm $form, ?string $back, array $errors, int $status)
    {
        return response()->view('builder.sent', ['form' => $form, 'back' => $back, 'errors_list' => $errors], $status)
            ->header('X-Robots-Tag', 'noindex');
    }
}
