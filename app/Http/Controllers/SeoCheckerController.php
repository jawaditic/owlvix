<?php

namespace App\Http\Controllers;

use App\Mail\AuditRequested;
use App\Mail\NewEnquiry;
use App\Mail\SeoTips;
use App\Models\ContactMessage;
use App\Support\Push;
use App\Support\SeoCheck;
use App\Support\SpeedTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** Free SEO checker: the page, the check itself, and "email me these tips" (which also saves a lead). */
class SeoCheckerController extends Controller
{
    public function show(Request $request)
    {
        return view('seo-checker', ['url' => (string) $request->query('url', '')]);
    }

    public function run(Request $request)
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500']]);
        $url = SpeedTest::normalize($data['url']);
        if (! $url) {
            return response()->json(['ok' => false, 'error' => 'Please enter a full website address, like example.com.'], 422);
        }
        $daily = 'seocheck-day:'.$request->ip();
        if (RateLimiter::tooManyAttempts($daily, 40)) {
            return response()->json(['ok' => false, 'error' => 'You have run a lot of checks today. Please come back tomorrow.'], 429);
        }
        RateLimiter::hit($daily, 86400);

        $result = SeoCheck::run($url);

        return response()->json($result, $result['ok'] ? 200 : 502);
    }

    public function email(Request $request)
    {
        // Hidden "website" field: real visitors leave it empty
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'email' => ['required', 'email', 'max:150'],
            'name' => ['nullable', 'string', 'max:100'],
        ], ['email.required' => 'Please enter your email.', 'email.email' => 'Please enter a valid email.']);

        $url = SpeedTest::normalize($data['url']);
        $report = $url ? SeoCheck::cached($url) : null;
        if (! $report) {
            return response()->json(['ok' => false, 'error' => 'Please run the check again, then send the tips.'], 422);
        }

        $issues = array_values(array_filter($report['checks'], fn ($c) => $c['status'] !== 'pass'));
        $lead = ContactMessage::create([
            'name' => $data['name'] ?: strtok($data['email'], '@'),
            'email' => $data['email'],
            'service' => 'SEO',
            'budget' => null,
            'message' => '<p>Requested the free SEO tips for <strong>'.e($report['final_url']).'</strong> — score '.$report['score'].'/100, '
                .$report['counts']['fail'].' critical and '.$report['counts']['warn'].' warnings.</p><ul>'
                .implode('', array_map(fn ($c) => '<li>'.e($c['title']).': '.e($c['found']).'</li>', array_slice($issues, 0, 10))).'</ul>',
            'source' => 'seo-checker',
            'ip' => $request->ip(),
        ]);

        try {
            Mail::to($lead->email, $data['name'] ?: null)->send(new SeoTips($report, $data['name'] ?? null));
            if ($owner = config('admin.email')) {
                Mail::to($owner)->send(new NewEnquiry($lead));
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'We could not send the email right now. Please try again later.'], 502);
        }
        Push::toAdmins('SEO checker lead: '.$lead->email, $report['final_url'].' · score '.$report['score'], ['type' => 'enquiry', 'id' => $lead->id]);

        return response()->json(['ok' => true]);
    }

    /** "Get a full SEO audit": saves an "SEO audit request" lead; the owner runs Claude SEO and emails the report. */
    public function audit(Request $request)
    {
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'email' => ['required', 'email', 'max:150'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Please enter your name.', 'email.required' => 'Please enter your email.', 'email.email' => 'Please enter a valid email.',
        ]);
        $url = SpeedTest::normalize($data['url']);
        if (! $url) {
            return response()->json(['ok' => false, 'error' => 'Please run the free check first, then request the full audit.'], 422);
        }
        // One request per site per email per day is enough
        $once = 'ai-audit:'.sha1(strtolower($data['email']).'|'.$url);
        if (RateLimiter::tooManyAttempts($once, 1)) {
            return response()->json(['ok' => true, 'already' => true]);
        }
        RateLimiter::hit($once, 86400);

        $report = SeoCheck::cached($url);
        $lead = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'service' => 'SEO',
            'message' => '<p><strong>Full SEO audit requested</strong> for <a href="'.e($url).'">'.e($url).'</a>'
                .($report ? ' — free checker score '.$report['score'].'/100 ('.$report['counts']['fail'].' critical, '.$report['counts']['warn'].' warnings)' : '').'.</p>'
                .(filled($data['notes'] ?? null) ? '<p>What matters most to them: '.e($data['notes']).'</p>' : '')
                .'<p>To do: open Claude Code on your Mac and run <code>/seo audit '.e($url).'</code>, then email them the report (PDF) within 2 business days.</p>',
            'source' => 'ai-audit',
            'ip' => $request->ip(),
        ]);

        try {
            Mail::to($lead->email, $lead->name)->send(new AuditRequested($lead, $url));
            if ($owner = config('admin.email')) {
                Mail::to($owner)->send(new NewEnquiry($lead));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        Push::toAdmins('SEO audit requested: '.parse_url($url, PHP_URL_HOST), $lead->name.' · '.$lead->email, ['type' => 'enquiry', 'id' => $lead->id]);

        return response()->json(['ok' => true]);
    }
}
