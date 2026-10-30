<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Free website speed test (/website-speed-test) — Google PageSpeed Insights is faked. */
class SpeedTestTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(int $status = 200): void
    {
        $audit = fn ($v, $display, $score = 0.5) => ['numericValue' => $v, 'displayValue' => $display, 'score' => $score];
        Http::fake(['www.googleapis.com/*' => Http::response($status === 200 ? [
            'lighthouseResult' => [
                'finalDisplayedUrl' => 'https://example.com/',
                'categories' => ['performance' => ['score' => 0.62]],
                'audits' => [
                    'largest-contentful-paint' => $audit(3100, '3.1 s'),
                    'first-contentful-paint' => $audit(1200, '1.2 s', 0.95),
                    'total-blocking-time' => $audit(450, '450 ms'),
                    'cumulative-layout-shift' => $audit(0.02, '0.02', 1),
                    'speed-index' => $audit(4200, '4.2 s'),
                    'server-response-time' => $audit(300, '300 ms', 1),
                    'uses-optimized-images' => ['title' => 'Efficiently encode images', 'description' => 'Optimized images load faster. [Learn more](https://web.dev/x).', 'score' => 0.2, 'details' => ['overallSavingsMs' => 1500]],
                    'render-blocking-resources' => ['title' => 'Eliminate render-blocking resources', 'description' => 'Resources are blocking the first paint.', 'score' => 0.4, 'details' => ['overallSavingsMs' => 600]],
                    'tiny-win' => ['title' => 'Tiny win', 'description' => 'x', 'score' => 0.5, 'details' => ['overallSavingsMs' => 10]],
                    'final-screenshot' => ['details' => ['data' => 'data:image/jpeg;base64,AAAA']],
                ],
            ],
            'loadingExperience' => ['overall_category' => 'AVERAGE', 'metrics' => [
                'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2900, 'category' => 'AVERAGE'],
                'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 180, 'category' => 'FAST'],
                'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 5, 'category' => 'FAST'],
            ]],
        ] : ['error' => ['message' => 'Lighthouse returned error: FAILED_DOCUMENT_REQUEST']], $status)]);
    }

    public function test_page_loads_and_is_linked(): void
    {
        $this->get('/website-speed-test')->assertOk()->assertSee('Free website', false)->assertSee('id="speed-form"', false);
        $this->get('/')->assertSee('href="'.route('speed-test').'"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.route('speed-test').'</loc>', false);
    }

    public function test_report_is_built_from_pagespeed_results(): void
    {
        Cache::flush();
        $this->fakeGoogle();

        $r = $this->postJson('/website-speed-test', ['url' => 'example.com', 'strategy' => 'mobile'])->assertOk()->json('report');

        $this->assertSame(62, $r['score']);
        $this->assertSame('average', $r['rating']);
        $this->assertSame('https://example.com', $r['url']);
        $this->assertSame(['3.1 s', 'average'], [$r['metrics'][0]['value'], $r['metrics'][0]['rating']]); // LCP
        $this->assertCount(3, $r['field']);
        $this->assertSame(['Efficiently encode images', 'Eliminate render-blocking resources'], array_column($r['fixes'], 'title'));
        $this->assertSame('Optimized images load faster. Learn more.', $r['fixes'][0]['description']); // markdown link removed
        Http::assertSent(fn ($req) => str_contains($req->url(), 'strategy=mobile') && str_contains($req->url(), 'category=performance'));

        // Second test of the same page comes from the cache
        $this->postJson('/website-speed-test', ['url' => 'https://example.com', 'strategy' => 'mobile'])->assertJsonPath('report.cached', true);
        Http::assertSentCount(1);
    }

    public function test_bad_addresses_are_rejected_without_calling_google(): void
    {
        Http::fake();
        foreach (['', 'not a url', 'localhost', 'http://127.0.0.1', 'intranet'] as $bad) {
            $this->postJson('/website-speed-test', ['url' => $bad])->assertStatus(422);
        }
        Http::assertNothingSent();
    }

    public function test_unreachable_site_shows_a_friendly_error(): void
    {
        Cache::flush();
        $this->fakeGoogle(500);
        $this->postJson('/website-speed-test', ['url' => 'down.example.com'])
            ->assertStatus(502)->assertJsonPath('error', 'We could not load that website. Check the address and that the site is online.');
    }

    public function test_pdf_report_is_generated_on_the_server(): void
    {
        Cache::flush();
        $this->fakeGoogle();
        $this->postJson('/website-speed-test', ['url' => 'example.com'])->assertOk();

        $res = $this->get('/website-speed-test/report.pdf?url=example.com&strategy=mobile')->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('speed-report-examplecom-mobile.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        Http::assertSentCount(1); // built from the cached result, no second Google call
    }

    public function test_pdf_rejects_bad_addresses(): void
    {
        $this->get('/website-speed-test/report.pdf?url=localhost')->assertNotFound();
    }
}
