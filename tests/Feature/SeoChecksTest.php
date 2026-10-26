<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Checks that site audits (Semrush) run, so fixed issues don't come back. */
class SeoChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_text_to_html_ratio_is_above_ten_percent(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $body = preg_replace('#<(script|style)\b.*?</\1>#s', '', $html);
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body))));

        $this->assertGreaterThan(0.10, strlen($text) / strlen($html), sprintf('Text %d bytes in %d bytes of HTML', strlen($text), strlen($html)));
        $this->assertDoesNotMatchRegularExpression('/\n[ \t]+</', $html, 'Indentation should be trimmed from HTML');
    }

    public function test_trimming_leaves_text_boxes_and_code_blocks_alone(): void
    {
        $page = "<div>\n    <p>Hello</p>\n    <textarea>line one\n    indented</textarea>\n    <pre>  code\n    more</pre>\n</div>";
        $response = (new \App\Http\Middleware\TrimHtmlWhitespace)->handle(
            \Illuminate\Http\Request::create('/'),
            fn () => response($page)->header('Content-Type', 'text/html; charset=UTF-8'),
        );

        $this->assertSame("<div>\n<p>Hello</p>\n<textarea>line one\n    indented</textarea>\n<pre>  code\n    more</pre>\n</div>", $response->getContent());
    }

    public function test_structured_data_is_an_organization(): void
    {
        $html = $this->get('/')->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = array_column(json_decode($m[1], true)['@graph'], '@type');

        $this->assertSame(['Organization', 'WebSite', 'FAQPage'], $types);
    }

    public function test_every_image_has_alt_text(): void
    {
        // Bing Site Scan counts an empty alt="" as missing
        preg_match_all('/<img\b[^>]*>/', $this->get('/')->getContent(), $m);
        foreach ($m[0] as $img) {
            $this->assertMatchesRegularExpression('/\salt="[^"]+"/', $img, "Image without alt text: {$img}");
        }
    }

    public function test_llms_txt_exists(): void
    {
        $this->assertFileExists(public_path('llms.txt'));
        $this->assertStringStartsWith('# Owlvix', file_get_contents(public_path('llms.txt')));
    }

    public function test_hsts_header_is_configured(): void
    {
        $this->assertStringContainsString('Strict-Transport-Security', file_get_contents(public_path('.htaccess')));
    }
}
