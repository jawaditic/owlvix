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
}
