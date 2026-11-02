<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The free business card maker (/business-card-maker) and its guide: pages, menus, sitemap, share picture, privacy. */
class BusinessCardMakerTest extends TestCase
{
    public function test_the_page_loads_with_its_builder_and_guide_link(): void
    {
        $this->get('/business-card-maker')->assertOk()
            ->assertSee('data-cb', false)
            ->assertSee('js/qr.', false)
            ->assertSee('js/card-builder', false)
            ->assertSee('data-wz-banner', false)
            ->assertSee(route('guide.business-card'), false)
            ->assertSee('images/og/business-card-maker.jpg', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_it_is_in_the_menu_sitemap_and_llms_file(): void
    {
        $this->get('/free-tools')->assertOk()->assertSee(route('business-card-maker'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('business-card-maker'), false)->assertSee(route('guide.business-card'), false);
        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('https://owlvix.com/business-card-maker', $llms);
        $this->assertStringContainsString('https://owlvix.com/how-to-design-a-business-card', $llms);
    }

    public function test_the_guide_page_links_to_the_generator(): void
    {
        $this->get('/how-to-design-a-business-card')->assertOk()
            ->assertSee('How to design a business card people keep')
            ->assertSee(route('business-card-maker'), false)
            ->assertSee('"@type":"HowTo"', false);
    }

    public function test_codes_are_made_on_the_device(): void
    {
        foreach (['js/card-builder.js', 'js/qr.js'] as $file) {
            $js = file_get_contents(public_path($file));
            $this->assertStringNotContainsString('fetch(', $js, $file);
            $this->assertStringNotContainsString('XMLHttpRequest', $js, $file);
            $this->assertStringNotContainsString('sendBeacon', $js, $file);
        }
    }
}
