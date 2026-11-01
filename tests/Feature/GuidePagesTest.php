<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Guides: "How to start selling online for free" (links to the online store builder). */
class GuidePagesTest extends TestCase
{
    public function test_the_selling_online_guide(): void
    {
        $html = $this->get('/how-to-sell-online')->assertOk()
            ->assertSee('<title>How to Start Selling Online for Free', false)
            ->assertSee('rel="canonical" href="'.route('guide.sell-online').'"', false)
            ->assertSee(route('store-builder'), false)->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = array_column(json_decode($m[1], true)['@graph'], null, '@type');
        $this->assertCount(8, $types['HowTo']['step']);
        $this->assertCount(6, $types['FAQPage']['mainEntity']);
        $this->get(route('store-builder'))->assertSee(route('guide.sell-online'), false); // linked from the builder
        $this->get('/sitemap.xml')->assertSee(route('guide.sell-online'), false);
        $this->assertStringContainsString('https://owlvix.com/how-to-sell-online', file_get_contents(public_path('llms.txt')));
    }
}
