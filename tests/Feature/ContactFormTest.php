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

    public function test_odd_input_is_turned_down_not_a_server_error(): void
    {
        $this->post('/contact', ['message' => ['x']] + $this->form)->assertSessionHasErrors('message');
        $this->get('/admin/messages?q[]=x')->assertRedirect(); // not logged in, but no 500 either
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_honeypot_does_not_count_as_a_lead(): void
    {
        $this->post('/contact', ['website' => 'spam.example'] + $this->form)->assertSessionHas('sent_fake')->assertSessionMissing('sent');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_rejected_popup_quote_reopens_on_the_same_page(): void
    {
        $this->from('/about')->post('/contact', ['source' => 'popup', 'email' => 'bad'] + $this->form)->assertRedirect('/about');
        $this->get('/about')->assertSee('data-reopen', false)->assertSee('value="Visitor"', false);
    }

    public function test_confirmation_email_has_no_links_from_the_visitor(): void
    {
        $this->post('/contact', ['message' => '<p>Pay your invoice at <a href="https://evil.example/x">https://evil.example/x</a> today</p>'] + $this->form);
        Mail::assertSent(\App\Mail\QuoteReceived::class, function ($mail) {
            $html = $mail->render();
            $this->assertStringNotContainsString('evil.example', $html);
            $this->assertStringContainsString('[link]', $html);

            return true;
        });
    }

    public function test_the_csv_export_cannot_run_formulas(): void
    {
        ContactMessage::create(['name' => '=HYPERLINK("https://evil.example","x")', 'email' => 'a@example.com', 'message' => '<p>Hello there friend</p>', 'source' => 'contact']);
        $csv = $this->withSession(['is_admin' => true])->get('/admin/messages/export')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
    }
}
