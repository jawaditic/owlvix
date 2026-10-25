<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The website's contact form. */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'message' => '<p>Please build me a booking website</p>',
        'source' => 'contact',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.recaptcha.site_key' => null]);
    }

    public function test_form_goes_to_the_thank_you_page_and_saves(): void
    {
        $this->post('/contact', $this->form)->assertRedirectContains('/thanks/');
        $this->assertSame(1, ContactMessage::count());
    }

    /** Every page view sends a stats beacon; that must not use up the form's 5-per-minute limit. */
    public function test_page_views_do_not_block_the_form(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/t', ['path' => '/', 'new' => '1']);
        }

        $this->post('/contact', $this->form)->assertRedirectContains('/thanks/');
        $this->assertSame(1, ContactMessage::count());
    }
}
