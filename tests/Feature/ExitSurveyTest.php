<?php

namespace Tests\Feature;

use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The "before you go" survey: saving answers and the Admin → Feedback page. */
class ExitSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_includes_the_survey(): void
    {
        $this->get('/')->assertOk()->assertSee('What stopped you from getting a quote today?');
    }

    public function test_an_answer_is_saved(): void
    {
        $this->postJson('/survey', ['reason' => 'price', 'comment' => ' Too <b>pricey</b> ', 'email' => 'v@example.com', 'page' => '/#pricing'],
            ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148'])
            ->assertCreated();

        $r = SurveyResponse::first();
        $this->assertSame('price', $r->reason);
        $this->assertSame('Too pricey', $r->comment);
        $this->assertSame('mobile', $r->device);
        $this->assertSame('/#pricing', $r->page);
    }

    public function test_other_needs_a_comment_and_bad_input_is_rejected(): void
    {
        $this->postJson('/survey', ['reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->postJson('/survey', ['reason' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson('/survey', ['reason' => 'info', 'email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_bots_filling_the_hidden_field_are_ignored(): void
    {
        $this->postJson('/survey', ['reason' => 'price', 'website' => 'spam.example'])->assertOk();
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_admin_sees_answers_and_they_are_marked_seen(): void
    {
        SurveyResponse::create(['reason' => 'info', 'comment' => 'Wanted to see prices for Shopify', 'email' => 'shop@example.com']);
        SurveyResponse::create(['reason' => 'browsing']);

        $this->get('/admin/feedback')->assertRedirect(); // must be logged in

        $this->withSession(['is_admin' => true]);
        $this->get('/admin')->assertSee('Feedback');
        $this->get('/admin/feedback')->assertOk()
            ->assertSee('Wanted to see prices for Shopify')
            ->assertSee('shop@example.com')
            ->assertSee('Need more information');
        $this->assertSame(0, SurveyResponse::whereNull('read_at')->count());

        $this->get('/admin/feedback?reason=browsing')->assertOk()->assertDontSee('Wanted to see prices for Shopify');

        $id = SurveyResponse::where('reason', 'browsing')->value('id');
        $this->delete("/admin/feedback/{$id}")->assertRedirect();
        $this->assertSame(1, SurveyResponse::count());
    }
}
