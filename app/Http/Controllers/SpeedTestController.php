<?php

namespace App\Http\Controllers;

use App\Support\SpeedTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Free website speed test: the page and the JSON endpoint its form calls. */
class SpeedTestController extends Controller
{
    public function show(Request $request)
    {
        return view('speed-test', [
            'url' => (string) $request->query('url', ''),
            'strategy' => $request->query('strategy') === 'desktop' ? 'desktop' : 'mobile',
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'strategy' => ['nullable', 'in:mobile,desktop'],
        ]);

        $url = SpeedTest::normalize($data['url']);
        if (! $url) {
            return response()->json(['ok' => false, 'error' => 'Please enter a full website address, like example.com.'], 422);
        }

        // Besides the per-minute route limit: at most 30 tests a day per visitor
        $daily = 'speedtest-day:'.$request->ip();
        if (RateLimiter::tooManyAttempts($daily, 30)) {
            return response()->json(['ok' => false, 'error' => 'You have run a lot of tests today. Please come back tomorrow.'], 429);
        }
        RateLimiter::hit($daily, 86400);

        $result = SpeedTest::run($url, $data['strategy'] ?? 'mobile');

        return response()->json($result, $result['ok'] ? 200 : 502);
    }

    /** The report as a branded A4 PDF, built on the server (dompdf) from the cached test result. */
    public function pdf(Request $request)
    {
        $url = SpeedTest::normalize((string) $request->query('url', ''));
        $strategy = $request->query('strategy') === 'desktop' ? 'desktop' : 'mobile';
        abort_unless($url, 404);

        $report = SpeedTest::cached($url, $strategy);
        if (! $report) {
            // Older than an hour: test again (counts towards the daily limit)
            $daily = 'speedtest-day:'.$request->ip();
            abort_if(RateLimiter::tooManyAttempts($daily, 30), 429);
            RateLimiter::hit($daily, 86400);
            $result = SpeedTest::run($url, $strategy);
            abort_unless($result['ok'], 502, $result['error'] ?? 'The test failed.');
            $report = $result['report'];
        }

        $options = new \Dompdf\Options();
        $options->setChroot(public_path());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml(view('speed-report-pdf', ['r' => $report])->render());
        $pdf->setPaper('A4');
        $pdf->render();
        // Footer with page numbers on every page
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(40, 815, 'owlvix.com  ·  Free website speed test  ·  '.parse_url($url, PHP_URL_HOST), $font, 7.5, [0.45, 0.5, 0.58]);
        $canvas->page_text(510, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.45, 0.5, 0.58]);

        $name = 'speed-report-'.\Illuminate\Support\Str::slug(parse_url($url, PHP_URL_HOST)).'-'.$strategy.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
