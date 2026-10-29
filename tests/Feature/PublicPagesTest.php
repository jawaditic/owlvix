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
}
