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
            // Linked from /services (and the homepage, except the hire pages) and listed in the sitemap
            if (! str_starts_with($slug, 'hire-')) {
                $this->get('/')->assertSee('href="'.route('service.'.$slug).'"', false);
            }
            $this->get('/services')->assertSee('href="'.route('service.'.$slug).'"', false);
            $this->get('/sitemap.xml')->assertSee('<loc>'.route('service.'.$slug).'</loc>', false);
        }
    }

    public function test_about_page_names_the_founder_and_is_linked_everywhere(): void
    {
        $html = $this->get('/about')->assertOk()->assertSee('Jawad Ahmad')->assertSee('@anjtec')->assertSee('Pakistan')->assertDontSee('formerly', false)->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $org = json_decode($m[1], true)['@graph'][1];
        $this->assertSame(['Organization', 'Jawad Ahmad', 'anjtec', 'PK'], [$org['@type'], $org['founder']['name'], $org['founder']['alternateName'], $org['address']['addressCountry']]);
        $this->assertArrayNotHasKey('alternateName', $org, 'Anjtec is the Fiverr username, not a former company name');

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
            foreach (['Services', 'Work', 'Pricing', 'Get a free quote'] as $label) {
                $this->assertStringContainsString('>'.$label.'</a>', $m[0], "$page menu is missing $label");
            }
            // Less-used pages are in the More (☰) menu
            foreach (['Process', 'FAQ', 'About'] as $label) {
                $this->assertStringContainsString('<strong>'.$label.'</strong>', $m[0], "$page More menu is missing $label");
            }
            $this->assertStringContainsString('aria-label="More pages"', $m[0]);
        }
    }

    /** Owl and portfolio images are served as WebP (with the original as fallback). */
    public function test_images_have_webp_versions(): void
    {
        // Owls are the animated vector owl everywhere on the site now (no PNG)
        $this->get('/about')->assertDontSee('images/owl.png', false)->assertSee('data-owl-idle', false);

        foreach (glob(public_path('images/portfolio/*.jpg')) as $jpg) {
            $this->assertFileExists(preg_replace('/\.jpg$/', '.webp', $jpg));
        }
    }

    /** The rating line shows "207 of 217 reviews are 5 stars" (Fiverr's 4.5 headline is a different measure). */
    public function test_rating_line_shows_five_star_share_not_an_average(): void
    {
        \App\Models\Setting::put(['reviews_profile_url' => 'https://www.fiverr.com/anjtec', 'reviews_rating' => '4.5', 'reviews_count' => '217', 'reviews_five_star' => '207']);
        \App\Support\Testimonials::flush();

        foreach (['/', '/services', '/ai-chatbot-development'] as $page) {
            $this->get($page)->assertSee('<strong>207 of 217</strong> reviews are 5 stars on Fiverr', false)
                ->assertDontSee('<strong>4.5</strong>', false);
        }
    }

    /** Every "what we build" card on the service pages has an icon from the icon library. */
    public function test_service_cards_use_known_icons(): void
    {
        foreach (config('service_pages') as $slug => $page) {
            foreach ($page['types']['items'] as [$name, $text, $icon]) {
                $this->assertContains($icon, \App\Support\Icons::names(), "$slug: $name");
            }
            $this->get('/'.$slug)->assertSee('class="svc-icon', false);
        }
    }

    /** "See how we charge" and the menu's "Pricing" open the pricing popup on pages without a pricing section. */
    public function test_pricing_popup_is_on_every_page(): void
    {
        foreach (['/', '/services', '/about', '/web-app-development'] as $page) {
            $this->get($page)->assertSee('id="pricing-modal"', false);
        }
        $this->get('/web-app-development')->assertSee('data-pricing>See how we charge', false);
        $this->get('/about')->assertSee('data-pricing >Pricing</a>', false);
        $this->get('/')->assertDontSee('data-pricing >Pricing</a>', false); // the homepage scrolls to its own pricing
    }

    /** Every quote form (page form and popup) offers every service, in the same groups. */
    public function test_every_quote_form_lists_all_services(): void
    {
        $count = count(\App\Support\Enquiries::SERVICES);
        foreach (['/', '/services', '/landing-page-design', '/about'] as $page) {
            $html = $this->get($page)->getContent();
            preg_match_all('#<select name="service"[^>]*>(.*?)</select>#s', $html, $selects);
            $this->assertNotEmpty($selects[1], $page);
            foreach ($selects[1] as $select) {
                $this->assertSame($count, substr_count($select, '<option'), $page);
                $this->assertStringContainsString('<optgroup label="Websites &amp; stores">', $select);
            }
        }
    }

    /** "Hire a developer" links live on /services, not on the homepage. */
    public function test_hire_group_only_on_services_page(): void
    {
        $this->get('/')->assertDontSee('Hire a developer</p>', false);
        $this->get('/services')->assertSee('Hire a developer</p>', false)->assertSee('href="'.route('service.hire-ai-developer').'"', false);
    }
}
