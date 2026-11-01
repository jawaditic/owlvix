<?php

namespace Tests\Feature;

use App\Support\Guides;
use Tests\TestCase;

/** Step-by-step guides (App\Support\Guides): each page, its structured data, and the links to and from its builder. */
class GuidePagesTest extends TestCase
{
    public function test_every_guide_page(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $llms = file_get_contents(public_path('llms.txt'));

        foreach (Guides::ALL as $route => [$path, , $short, $builder, $ask]) {
            $url = route($route);
            $html = $this->get($path)->assertOk()
                ->assertSee('rel="canonical" href="'.$url.'"', false)
                ->assertSee(route($builder), false)
                ->getContent();

            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
            $types = array_column(json_decode($m[1], true)['@graph'], null, '@type');
            $this->assertGreaterThanOrEqual(5, count($types['HowTo']['step']), $route);
            $this->assertSame(substr_count($html, 'class="guide__n"'), count($types['HowTo']['step']), $route);
            $this->assertGreaterThanOrEqual(5, count($types['FAQPage']['mainEntity']), $route);

            foreach (Guides::ALL as $other => $o) { // "More free guides" links every other guide
                if ($other !== $route) {
                    $this->assertStringContainsString('href="'.route($other).'"', $html, $route);
                }
            }

            $this->get(route($builder))->assertSee(e($ask), false)->assertSee($url, false); // linked from its builder
            $sitemap->assertSee($url, false);
            $this->assertStringContainsString('https://owlvix.com'.$path, $llms, $route);
        }
    }

    public function test_the_selling_online_guide(): void
    {
        $this->get('/how-to-sell-online')->assertOk()
            ->assertSee('<title>How to Start Selling Online for Free', false)
            ->assertSee(route('service.shopify-development'), false);
    }

    public function test_the_guides_page_lists_every_guide(): void
    {
        $html = $this->get('/guides')->assertOk()->assertSee('<title>Free Step-by-Step Guides', false)->getContent();
        foreach (Guides::ALL as $route => $g) {
            $this->assertStringContainsString('href="'.route($route).'"', $html);
        }
        $this->get('/sitemap.xml')->assertSee(route('guides'), false);
        $this->get(route('home'))->assertSee('href="'.route('guides').'"', false); // in the menu and footer
    }

    public function test_builders_without_a_guide_have_no_guide_link(): void
    {
        $this->get(route('landing-builder'))->assertOk()->assertDontSee('lpb-guide-link', false);
    }

    public function test_the_example_designs_gallery(): void
    {
        $html = $this->get('/examples')->assertOk()
            ->assertSee('<title>Free Website, Shop &amp; Page Designs', false)
            ->assertSee('js/examples', false)->assertSee('js/builder-engine', false)
            ->getContent();
        foreach (['store', 'website', 'menu', 'event', 'portfolio', 'links', 'soon', 'landing', 'funnel'] as $kind) {
            $this->assertStringContainsString('data-examples="'.$kind.'"', $html);
        }
        $this->get('/sitemap.xml')->assertSee(route('examples'), false);
        $this->get(route('home'))->assertSee('href="'.route('examples').'"', false); // in the Free builders menu
        $this->assertStringContainsString('https://owlvix.com/examples', file_get_contents(public_path('llms.txt')));
    }
}
