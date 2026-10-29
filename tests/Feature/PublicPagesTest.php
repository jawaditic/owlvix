<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Privacy policy, the 404 page and the /index.php redirect. */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_is_linked_from_every_form_and_footer(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee(config('mail.from.address'));

        foreach (['/', '/services'] as $page) {
            $html = $this->get($page)->getContent();
            // contact/landing form + quote popup + footer
            $this->assertGreaterThanOrEqual(3, substr_count($html, 'href="'.route('privacy').'"'), $page);
        }
        $this->get('/sitemap.xml')->assertSee('<loc>'.route('privacy').'</loc>', false);
    }

    public function test_missing_pages_get_a_helpful_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('href="'.route('landing').'"', false);
    }

    /** Apache/LiteSpeed rule: /index.php typed in the address bar redirects to / (duplicate homepage). */
    public function test_index_php_redirect_rule_exists(): void
    {
        $this->assertStringContainsString('RewriteRule ^index\.php(?:/(.*))?$ /$1 [L,R=301,NE]', file_get_contents(public_path('.htaccess')));
    }

    public function test_service_pages_render_with_their_own_schema_and_form(): void
    {
        foreach (config('service_pages') as $slug => $page) {
            $html = $this->get('/'.$slug)->assertOk()
                ->assertSee('<link rel="canonical" href="'.route('service.'.$slug).'">', false)
                ->assertSee('<h1>'.e($page['h1']).'</h1>', false)
                ->assertSee('<option value="'.e($page['service']).'" selected>', false)
                ->getContent();

            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
            $this->assertSame(['Organization', 'WebPage', 'Service', 'FAQPage'], array_column(json_decode($m[1], true)['@graph'], '@type'));

            preg_match_all('/<img\b[^>]*>/', $html, $imgs);
            foreach ($imgs[0] as $img) {
                $this->assertMatchesRegularExpression('/\salt="[^"]+"/', $img);
            }
            // Linked from the homepage and /services, and listed in the sitemap
            $this->get('/')->assertSee('href="'.route('service.'.$slug).'"', false);
            $this->get('/services')->assertSee('href="'.route('service.'.$slug).'"', false);
            $this->get('/sitemap.xml')->assertSee('<loc>'.route('service.'.$slug).'</loc>', false);
        }
    }

    public function test_about_page_names_the_founder_and_is_linked_everywhere(): void
    {
        $html = $this->get('/about')->assertOk()->assertSee('Jawad Ahmad')->assertSee('Anjtec')->assertSee('Pakistan')->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $org = json_decode($m[1], true)['@graph'][1];
        $this->assertSame(['Organization', 'Jawad Ahmad', 'Anjtec', 'PK'], [$org['@type'], $org['founder']['name'], $org['alternateName'], $org['address']['addressCountry']]);

        foreach (['/', '/services', '/ai-chatbot-development', '/privacy'] as $page) {
            $this->get($page)->assertSee('href="'.route('about').'"', false);
        }
        $this->get('/')->assertSee('id="about"', false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.route('about').'</loc>', false);
    }

    /** Every public page has the same main menu, so visitors can always reach About, Work, Pricing... */
    public function test_every_page_has_the_same_main_menu(): void
    {
        foreach (['/', '/services', '/ai-chatbot-development', '/about', '/privacy'] as $page) {
            $html = $this->get($page)->getContent();
            preg_match('#<nav class="nav__links".*?</nav>#s', $html, $m);
            $this->assertNotEmpty($m, $page);
            foreach (['Services', 'Work', 'Process', 'Pricing', 'FAQ', 'About', 'Get a free quote'] as $label) {
                $this->assertStringContainsString('>'.$label.'</a>', $m[0], "$page menu is missing $label");
            }
        }
    }
}
