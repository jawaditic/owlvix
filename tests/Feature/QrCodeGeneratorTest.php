<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The free QR code generator (/qr-code-generator) and its guide: pages, menus, sitemap, share picture, privacy. */
class QrCodeGeneratorTest extends TestCase
{
    public function test_the_page_loads_with_its_builder_and_guide_link(): void
    {
        $this->get('/qr-code-generator')->assertOk()
            ->assertSee('data-qb', false)
            ->assertSee('js/qr.', false)
            ->assertSee('js/qr-builder', false)
            ->assertSee('data-wz-banner', false)
            ->assertSee(route('guide.qr'), false)
            ->assertSee('images/og/qr-code-generator.jpg', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_it_is_in_the_menu_sitemap_and_llms_file(): void
    {
        $this->get('/free-tools')->assertOk()->assertSee(route('qr-code-generator'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('qr-code-generator'), false)->assertSee(route('guide.qr'), false);
        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('https://owlvix.com/qr-code-generator', $llms);
        $this->assertStringContainsString('https://owlvix.com/how-to-make-a-qr-code', $llms);
    }

    public function test_the_guide_page_links_to_the_generator(): void
    {
        $this->get('/how-to-make-a-qr-code')->assertOk()
            ->assertSee('How to make a QR code that people actually scan')
            ->assertSee(route('qr-code-generator'), false)
            ->assertSee('"@type":"HowTo"', false);
    }

    public function test_codes_are_made_on_the_device(): void
    {
        foreach (['js/qr-builder.js', 'js/qr.js'] as $file) {
            $js = file_get_contents(public_path($file));
            $this->assertStringNotContainsString('fetch(', $js, $file);
            $this->assertStringNotContainsString('XMLHttpRequest', $js, $file);
            $this->assertStringNotContainsString('sendBeacon', $js, $file);
        }
    }
}
