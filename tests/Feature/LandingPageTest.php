<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The services landing page (/services) for ads and search. */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.recaptcha.site_key' => null]);
    }

    public function test_page_shows_every_service_and_the_quote_form(): void
    {
        $this->get('/services')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('landing').'">', false)
            ->assertSee('name="source" value="landing"', false)
            ->assertSeeInOrder(['Custom Web App Development', 'Mobile App Development', 'AI Chatbot Development', 'AI Agents & Automation', 'Websites & Shopify Stores'], false);
    }

    public function test_structured_data_lists_services_and_faq(): void
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $this->get('/services')->getContent(), $m);
        $types = array_column(json_decode($m[1], true)['@graph'], '@type');

        $this->assertSame(['WebPage', 'ItemList', 'FAQPage'], $types);
    }

    public function test_every_image_has_alt_text(): void
    {
        preg_match_all('/<img\b[^>]*>/', $this->get('/services')->getContent(), $m);
        foreach ($m[0] as $img) {
            $this->assertMatchesRegularExpression('/\salt="[^"]+"/', $img, "Image without alt text: {$img}");
        }
    }

    public function test_leads_are_saved_as_landing_page(): void
    {
        $this->post('/contact', [
            'name' => 'Visitor', 'email' => 'visitor@example.com',
            'message' => '<p>Please build me an AI chatbot</p>', 'source' => 'landing',
        ])->assertRedirectContains('/thanks/');

        $this->assertSame('Landing page', ContactMessage::first()->sourceLabel());
    }

    public function test_spam_from_the_landing_page_goes_back_to_it(): void
    {
        $this->post('/contact', ['website' => 'spam', 'source' => 'landing'])
            ->assertRedirect(route('landing').'#contact');
    }

    /** Search engines find the page through links, so the homepage must point to it. */
    public function test_homepage_links_to_the_page(): void
    {
        $this->get('/')->assertSee('href="'.route('landing').'"', false);
    }

    public function test_sitemap_lists_the_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.route('landing').'</loc>', false);
    }
}
