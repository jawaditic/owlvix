<?php

namespace App\Http\Controllers;

use App\Mail\BuilderFormMessage;
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

    public function submit(Request $request, string $id)
    {
        $form = $this->form($id);
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
        $site = BuilderSite::where('slug', $slug)->firstOrFail();
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

    private function problem(BuilderForm $form, ?string $back, array $errors, int $status)
    {
        return response()->view('builder.sent', ['form' => $form, 'back' => $back, 'errors_list' => $errors], $status)
            ->header('X-Robots-Tag', 'noindex');
    }
}
