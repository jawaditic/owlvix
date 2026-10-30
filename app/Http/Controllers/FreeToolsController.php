<?php

namespace App\Http\Controllers;

use App\Support\BrokenLinks;
use App\Support\DomainCheck;
use App\Support\EmailCheck;
use App\Support\MetaPreview;
use App\Support\RedirectCheck;
use App\Support\RobotsCheck;
use App\Support\SecurityCheck;
use App\Support\SpeedTest;
use App\Support\TechDetect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Free tools that read a website or domain: tech stack, security, meta preview, broken links, email, redirects, robots.txt, domain. */
class FreeToolsController extends Controller
{
    public function tech(Request $request)
    {
        return $this->runTool($request, 'tech', fn ($url) => TechDetect::run($url));
    }

    public function security(Request $request)
    {
        return $this->runTool($request, 'security', fn ($url) => SecurityCheck::run($url));
    }

    public function meta(Request $request)
    {
        return $this->runTool($request, 'meta', fn ($url) => MetaPreview::run($url));
    }

    public function links(Request $request)
    {
        return $this->runTool($request, 'links', fn ($url) => BrokenLinks::run($url), perDay: 15, seconds: 100);
    }

    public function email(Request $request)
    {
        return $this->runTool($request, 'email', fn ($domain) => EmailCheck::run($domain), [EmailCheck::class, 'domain'],
            'Please enter a domain or email address, like example.com or you@example.com.');
    }

    public function redirects(Request $request)
    {
        return $this->runTool($request, 'redirects', fn ($url) => RedirectCheck::run($url));
    }

    public function robots(Request $request)
    {
        return $this->runTool($request, 'robots', fn ($url) => RobotsCheck::run($url));
    }

    public function domain(Request $request)
    {
        return $this->runTool($request, 'domain', fn ($domain) => DomainCheck::run($domain), [DomainCheck::class, 'domain'],
            'Please enter a domain name, like example.com.');
    }

    private function runTool(Request $request, string $tool, callable $run, ?callable $normalize = null, string $invalid = 'Please enter a full website address, like example.com.', int $perDay = 40, int $seconds = 60)
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500']]);
        $url = ($normalize ?? [SpeedTest::class, 'normalize'])($data['url']);
        if (! $url) {
            return response()->json(['ok' => false, 'error' => $invalid], 422);
        }
        $daily = "tool-$tool-day:".$request->ip();
        if (RateLimiter::tooManyAttempts($daily, $perDay)) {
            return response()->json(['ok' => false, 'error' => 'You have used this tool a lot today. Please come back tomorrow.'], 429);
        }
        RateLimiter::hit($daily, 86400);
        @set_time_limit($seconds);
        $result = $run($url);

        return response()->json($result, $result['ok'] ? 200 : 502);
    }
}
