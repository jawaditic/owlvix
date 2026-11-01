<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The site's fonts come from owlvix.com (public/fonts, rules at the top of css/theme.css), not Google Fonts. */
class SelfHostedFontsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_load_our_own_fonts(): void
    {
        foreach (['/', '/guides', '/examples', '/online-store-builder', '/about', '/admin/login'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('fonts.googleapis.com', false);
        }
        $this->get('/guides')->assertSee('<link rel="preload" href="'.asset('fonts/poppins-800-latin.woff2').'" as="font" type="font/woff2" crossorigin>', false);

        $css = file_get_contents(public_path('css/theme.css'));
        preg_match_all('#url\(\.\./fonts/([a-z0-9-]+\.woff2)\)#', $css, $m);
        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $file) {
            $this->assertFileExists(public_path('fonts/'.$file));
        }
        $this->assertStringContainsString("font-family: 'Poppins Fallback'", $css);
    }
}
