<?php

namespace App\Http\Controllers;

use App\Support\MetaPreview;
use App\Support\SecurityCheck;
use App\Support\SpeedTest;
use App\Support\TechDetect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Free tools that read a website: tech stack detector, security & SSL checker, meta tag & social preview. */
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

    private function runTool(Request $request, string $tool, callable $run)
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500']]);
        $url = SpeedTest::normalize($data['url']);
        if (! $url) {
            return response()->json(['ok' => false, 'error' => 'Please enter a full website address, like example.com.'], 422);
        }
        $daily = "tool-$tool-day:".$request->ip();
        if (RateLimiter::tooManyAttempts($daily, 40)) {
            return response()->json(['ok' => false, 'error' => 'You have used this tool a lot today. Please come back tomorrow.'], 429);
        }
        RateLimiter::hit($daily, 86400);
        @set_time_limit(60);
        $result = $run($url);

        return response()->json($result, $result['ok'] ? 200 : 502);
    }
}
