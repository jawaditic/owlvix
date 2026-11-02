<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The free invoice generator (/invoice-generator) and its guide: pages, menus, sitemap, share picture. */
class InvoiceGeneratorTest extends TestCase
{
    public function test_the_page_loads_with_its_builder_and_guide_link(): void
    {
        $this->get('/invoice-generator')->assertOk()
            ->assertSee('data-ib', false)
            ->assertSee('js/invoice-builder', false)
            ->assertSee('data-wz-banner', false)
            ->assertSee(route('guide.invoice'), false)
            ->assertSee('images/og/invoice-generator.jpg', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_it_is_in_the_menu_sitemap_and_llms_file(): void
    {
        $this->get('/free-tools')->assertOk()->assertSee(route('invoice-generator'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('invoice-generator'), false)->assertSee(route('guide.invoice'), false);
        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('https://owlvix.com/invoice-generator', $llms);
        $this->assertStringContainsString('https://owlvix.com/how-to-make-an-invoice', $llms);
    }

    public function test_the_guide_page_links_to_the_generator(): void
    {
        $this->get('/how-to-make-an-invoice')->assertOk()
            ->assertSee('How to make an invoice and get paid on time')
            ->assertSee(route('invoice-generator'), false)
            ->assertSee('"@type":"HowTo"', false);
        $this->get('/guides')->assertOk()->assertSee(route('guide.invoice'), false);
    }

    public function test_the_script_keeps_everything_on_the_device(): void
    {
        $js = file_get_contents(public_path('js/invoice-builder.js'));
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
        $this->assertStringNotContainsString('sendBeacon', $js);
    }
}
