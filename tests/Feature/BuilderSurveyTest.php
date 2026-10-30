<?php

namespace Tests\Feature;

use App\Mail\BuilderSignInCode;
use App\Mail\BuilderSurveyResponse;
use App\Models\BuilderForm;
use App\Models\BuilderResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Form builder (/form-builder): saving forms, the public form page, answers, CSV and deleting. */
class BuilderSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $email = 'maker@example.com'): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => $email])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use ($email, &$code) {
            $code = $m->code;

            return $m->hasTo($email);
        });

        return $this->postJson('/builder/account/verify', ['email' => $email, 'code' => $code])->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['X-Builder-Token' => $token]);
    }

    private function schema(array $extra = []): array
    {
        return $extra + [
            'title' => 'Party RSVP', 'intro' => 'Join us!', 'button' => 'Send', 'thanks' => 'See you there', 'color' => '#ff0000',
            'fields' => [
                ['id' => 'name1', 'type' => 'text', 'label' => 'Your name', 'required' => true],
                ['id' => 'mail1', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['id' => 'sec1', 'type' => 'heading', 'label' => 'Details'],
                ['id' => 'come1', 'type' => 'radio', 'label' => 'Coming?', 'required' => true, 'options' => ['Yes', 'No', 'Yes']],
                ['id' => 'food1', 'type' => 'checkbox', 'label' => 'Food', 'options' => ['Fish', 'Veg']],
                ['id' => 'rate1', 'type' => 'rating', 'label' => 'Excited?'],
                ['id' => 'bad', 'type' => 'script', 'label' => 'Not a real type'],
            ],
        ];
    }

    private function publish(string $token, array $schema = []): array
    {
        return $this->api($token)->postJson('/builder/surveys', ['schema' => $schema ?: $this->schema()])->assertOk()->json('form');
    }

    public function test_page_loads_and_saving_needs_sign_in(): void
    {
        $this->get(route('form-builder'))->assertOk()->assertSee('js/form-builder', false);
        $this->postJson('/builder/surveys', ['schema' => $this->schema()])->assertStatus(401);
    }

    public function test_a_form_is_cleaned_saved_and_shown(): void
    {
        $form = $this->publish($this->signIn());

        $this->assertSame('Party RSVP', $form['title']);
        $this->assertCount(6, $form['schema']['fields']); // unknown type dropped
        $this->assertSame(['Yes', 'No'], $form['schema']['fields'][3]['options']); // duplicates removed
        $this->assertFalse($form['schema']['fields'][2]['required']);
        $this->assertStringEndsWith('/form/'.$form['id'], $form['url']);

        $page = $this->get('/form/'.$form['id'])->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Party RSVP')->assertSee('name="food1[]"', false)->assertSee('--c: #ff0000', false);
        $this->assertStringContainsString('frame-ancestors *', $page->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $page->headers->get('Content-Security-Policy')); // no scripts allowed
        $this->assertStringNotContainsString('script-src', $page->headers->get('Content-Security-Policy'));
        $this->get('/form/'.$form['id'].'?embed=1')->assertOk()->assertSee('name="_embed"', false);
    }

    public function test_forms_cannot_ask_for_passwords_or_card_details(): void
    {
        $token = $this->signIn();
        foreach (['Your password', 'Card number', 'CVV', 'Enter the verification code'] as $label) {
            $this->api($token)->postJson('/builder/surveys', ['schema' => ['title' => 'Login', 'fields' => [['id' => 'abc', 'type' => 'text', 'label' => $label]]]])
                ->assertStatus(422)->assertJsonPath('ok', false);
        }
        $this->api($token)->postJson('/builder/surveys', ['schema' => ['title' => 'Empty', 'fields' => [['id' => 'h1', 'type' => 'heading', 'label' => 'Hi']]]])
            ->assertStatus(422)->assertJsonPath('error', 'Add at least one question.');
        $this->api($token)->postJson('/builder/surveys', ['schema' => ['fields' => [['id' => 'c1', 'type' => 'select', 'label' => 'Pick', 'options' => []]]]])
            ->assertStatus(422);
    }

    public function test_answers_are_checked_stored_and_emailed(): void
    {
        $form = $this->publish($this->signIn());
        Mail::fake();

        // Missing required answers and a choice that isn't offered: shown again with the errors, nothing stored
        $this->post('/f/'.$form['id'], ['name1' => 'Ann', 'come1' => 'Maybe'])
            ->assertStatus(422)->assertSee('Please answer “Email”')->assertSee('Please check your answer to “Coming?”')->assertSee('value="Ann"', false);
        $this->assertSame(0, BuilderResponse::count());

        $this->post('/f/'.$form['id'], ['name1' => 'Ann', 'mail1' => 'ann@example.com', 'come1' => 'Yes', 'food1' => ['Veg', 'Steak'], 'rate1' => '5', 'evil' => 'x'])
            ->assertRedirect(route('builder.survey', ['id' => $form['id'], 'sent' => 1]));
        $r = BuilderResponse::sole();
        $this->assertSame(['name1' => 'Ann', 'mail1' => 'ann@example.com', 'come1' => 'Yes', 'food1' => ['Veg'], 'rate1' => '5'], $r->answers);
        Mail::assertSent(BuilderSurveyResponse::class, fn ($m) => $m->hasTo('maker@example.com') && $m->hasReplyTo('ann@example.com'));
        $this->get('/form/'.$form['id'].'?sent=1')->assertOk()->assertSee('See you there');

        // Bots filling the hidden field get the thank-you page, but nothing is stored
        $this->post('/f/'.$form['id'], ['_hp' => 'bot', 'name1' => 'x', 'mail1' => 'x@example.com', 'come1' => 'No'])->assertRedirect();
        $this->assertSame(1, BuilderResponse::count());
    }

    public function test_owner_reads_answers_and_downloads_csv_others_cannot(): void
    {
        $token = $this->signIn();
        $form = $this->publish($token);
        $this->post('/f/'.$form['id'], ['name1' => '=HYPERLINK("x")', 'mail1' => 'ann@example.com', 'come1' => 'Yes', 'food1' => ['Fish', 'Veg']]);

        $this->api($token)->getJson('/builder/surveys/'.$form['id'].'/responses')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('responses.0.answers.mail1', 'ann@example.com');
        $csv = $this->api($token)->get('/builder/surveys/'.$form['id'].'/responses?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Date,"Your name",Email,Coming?,Food,Excited?', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // formulas made safe
        $this->assertStringContainsString('"Fish, Veg"', $csv);
        $this->api($token)->getJson('/builder/surveys')->assertOk()->assertJsonPath('forms.0.responses', 1);

        $other = $this->signIn('other@example.com');
        $this->api($other)->getJson('/builder/surveys/'.$form['id'].'/responses')->assertStatus(404);
        $this->api($other)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => $this->schema(['title' => 'Hacked'])])->assertStatus(403);
        $this->api($other)->postJson('/builder/surveys/'.$form['id'].'/delete')->assertStatus(404);
        $this->assertSame('Party RSVP', BuilderForm::find($form['id'])->site_name);
    }

    public function test_owner_edits_and_deletes_a_form(): void
    {
        $token = $this->signIn();
        $form = $this->publish($token);
        $this->api($token)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => $this->schema(['title' => 'Dinner RSVP'])])
            ->assertOk()->assertJsonPath('form.id', $form['id']);
        $this->get('/form/'.$form['id'])->assertSee('Dinner RSVP');

        $this->post('/f/'.$form['id'], ['name1' => 'Ann', 'mail1' => 'ann@example.com', 'come1' => 'Yes']);
        $this->api($token)->postJson('/builder/surveys/'.$form['id'].'/delete')->assertOk();
        $this->assertSame(0, BuilderResponse::count());
        $this->get('/form/'.$form['id'])->assertNotFound();
    }

    public function test_page_builder_cannot_make_a_survey_without_questions(): void
    {
        $token = $this->signIn();
        $this->api($token)->postJson('/builder/forms', ['site_name' => 'x', 'kind' => 'survey'])->assertStatus(422);
        $empty = \App\Models\BuilderAccount::first()->forms()->create(['id' => BuilderForm::newId(), 'site_name' => 'x', 'kind' => 'survey']);
        $this->get('/form/'.$empty->id)->assertNotFound();
        $this->post('/f/'.$empty->id, ['a' => 'b'])->assertNotFound();
    }

    public function test_answers_to_removed_questions_still_show(): void
    {
        $token = $this->signIn();
        $form = $this->publish($token);
        $this->post('/f/'.$form['id'], ['name1' => 'Ann', 'mail1' => 'ann@example.com', 'come1' => 'Yes', 'rate1' => '4']);

        $schema = $this->schema();
        $schema['fields'] = array_values(array_filter($schema['fields'], fn ($f) => $f['id'] !== 'rate1'));
        $saved = $this->api($token)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => $schema])->json('form.schema');
        $this->assertSame([['id' => 'rate1', 'label' => 'Excited?']], $saved['removed']);

        $csv = $this->api($token)->get('/builder/surveys/'.$form['id'].'/responses?format=csv')->streamedContent();
        $this->assertStringContainsString('"Excited? (removed)"', $csv);
        $this->assertStringEndsWith(",4\n", $csv);

        // Adding it back takes it off the removed list
        $saved = $this->api($token)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => $this->schema()])->json('form.schema');
        $this->assertSame([], $saved['removed']);
    }

    public function test_dropdown_options_keep_their_exact_text_and_options_are_checked_for_phishing(): void
    {
        $token = $this->signIn();
        $form = $this->publish($token, ['title' => 'Order', 'fields' => [['id' => 'box1', 'type' => 'select', 'label' => 'Box', 'required' => true, 'options' => ['Large  box', 'Small']]]]);
        $this->get('/form/'.$form['id'])->assertSee('<option value="Large  box"', false);
        $this->post('/f/'.$form['id'], ['box1' => 'Large  box'])->assertRedirect();

        $this->api($token)->postJson('/builder/surveys', ['schema' => ['title' => 'Check', 'fields' => [['id' => 'q1', 'type' => 'radio', 'label' => 'Account', 'options' => ['Enter your password below']]]]])->assertStatus(422);
        $this->api($token)->postJson('/builder/surveys', ['schema' => ['title' => 'Check', 'button' => 'Verify credit card', 'fields' => [['id' => 'q1', 'type' => 'text', 'label' => 'Name']]]])->assertStatus(422);
    }

    public function test_survey_and_page_form_limits_are_separate(): void
    {
        $token = $this->signIn();
        $account = \App\Models\BuilderAccount::first();
        for ($i = 0; $i < BuilderForm::MAX_PER_ACCOUNT; $i++) {
            $account->forms()->create(['id' => BuilderForm::newId(), 'site_name' => 'Site '.$i, 'kind' => 'contact']);
        }
        $this->api($token)->postJson('/builder/forms', ['site_name' => 'One more'])->assertStatus(422);
        $this->publish($token); // surveys have their own limit
    }

    public function test_rsvp_from_a_builder_page_thanks_the_guest_and_links_back(): void
    {
        $token = $this->signIn();
        // The questions the page builder's RSVP widget sends (builder-engine.js rsvpSchema)
        $form = $this->api($token)->postJson('/builder/surveys', ['schema' => ['title' => 'RSVP — Emma & James', 'thanks' => 'Can’t wait to see you!', 'fields' => [
            ['id' => 'rname', 'type' => 'text', 'label' => 'Your name', 'required' => true],
            ['id' => 'remail', 'type' => 'email', 'label' => 'Email'],
            ['id' => 'rcome', 'type' => 'radio', 'label' => 'Will you come?', 'required' => true, 'options' => ['Yes, I will be there', 'Sorry, I can’t make it']],
            ['id' => 'rguests', 'type' => 'number', 'label' => 'How many guests (including you)?'],
        ]]])->assertOk()->json('form');
        $this->assertStringEndsWith('/f/'.$form['id'], $form['action']);

        $page = 'https://sites.owlvix.com/emma-and-james';
        $this->post($form['action'], ['rname' => 'Ann', 'rcome' => 'Yes, I will be there', 'rguests' => '2', '_back' => $page])
            ->assertRedirect(route('builder.form.sent', ['id' => $form['id'], 'back' => $page]));
        $this->assertSame(['rname' => 'Ann', 'rcome' => 'Yes, I will be there', 'rguests' => '2'], BuilderResponse::sole()->answers);
        $this->get(route('builder.form.sent', ['id' => $form['id'], 'back' => $page]))->assertOk()
            ->assertSee('Can’t wait to see you!')->assertSee('href="'.$page.'"', false);
    }

    public function test_rsvp_forms_are_managed_by_their_page_and_errors_keep_the_link_back(): void
    {
        $token = $this->signIn();
        $form = $this->api($token)->postJson('/builder/surveys', ['schema' => ['managed' => true, 'title' => 'RSVP — Party', 'fields' => [
            ['id' => 'rname', 'type' => 'text', 'label' => 'Your name', 'required' => true],
            ['id' => 'rguests', 'type' => 'number', 'label' => 'Guests', 'min' => 1, 'max' => 20],
        ]]])->assertOk()->json('form');
        $this->assertTrue($form['schema']['managed']);

        // The form builder (no "managed" flag) can't overwrite it; the page (managed) can
        $this->api($token)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => ['title' => 'Mine now', 'fields' => [['id' => 'q1', 'type' => 'text', 'label' => 'Name']]]])->assertStatus(422);
        $this->api($token)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => ['managed' => true, 'title' => 'RSVP — Party!', 'fields' => $form['schema']['fields']]])->assertOk();

        // Guest numbers must be sensible
        $page = 'https://sites.owlvix.com/party';
        foreach (['-5', '0', '21', '1e9', '0x10'] as $bad) {
            $this->post($form['action'], ['rname' => 'Ann', 'rguests' => $bad, '_back' => $page])->assertStatus(422)
                ->assertSee('Please check your answer to “Guests”')
                ->assertSee('name="_back" value="'.$page.'"', false); // fixing the error still ends back on the page
        }
        $this->post($form['action'], ['rname' => 'Ann', 'rguests' => '3', '_back' => $page])->assertRedirect();
        $this->get(route('builder.form.sent', ['id' => $form['id'], 'back' => $page]))->assertSee('<title>Thank you</title>', false)->assertSee('Back to the page');
    }

    public function test_number_answers_accept_what_number_boxes_send(): void
    {
        $form = $this->publish($this->signIn(), ['title' => 'Budget', 'fields' => [['id' => 'num1', 'type' => 'number', 'label' => 'Amount']]]);
        foreach (['.5', '1e3', '1234567890123', '-2,5'] as $ok) {
            $this->post('/f/'.$form['id'], ['num1' => $ok])->assertRedirect();
        }
        foreach (['abc', '0x10', '1e400'] as $bad) {
            $this->post('/f/'.$form['id'], ['num1' => $bad])->assertStatus(422);
        }
    }

    public function test_number_limits_keep_fractions_and_impossible_limits_are_dropped(): void
    {
        $token = $this->signIn();
        $f = $this->publish($token, ['title' => 'N', 'fields' => [['id' => 'n1', 'type' => 'number', 'label' => 'A', 'min' => 0.5, 'max' => 2]]])['schema']['fields'][0];
        $this->assertSame([0.5, 2], [$f['min'], $f['max']]);
        $f = $this->publish($token, ['title' => 'N', 'fields' => [['id' => 'n1', 'type' => 'number', 'label' => 'A', 'min' => 10, 'max' => 5]]])['schema']['fields'][0];
        $this->assertArrayNotHasKey('min', $f);
    }

    public function test_another_accounts_form_is_refused_with_a_clear_message_not_reported_missing(): void
    {
        $form = $this->publish($this->signIn());
        $other = $this->signIn('other@example.com');
        $this->api($other)->postJson('/builder/surveys', ['id' => $form['id'], 'schema' => $this->schema()])
            ->assertStatus(403)->assertJsonPath('error', fn ($e) => str_contains($e, 'another email'));
        $this->api($other)->postJson('/builder/surveys', ['id' => 'zzzzzzzzzzzzzzzz', 'schema' => $this->schema()])->assertStatus(404);
    }

    public function test_answers_from_downloaded_pages_link_back_to_that_site(): void
    {
        $form = $this->publish($this->signIn());
        $answer = ['name1' => 'Ann', 'mail1' => 'ann@example.com', 'come1' => 'Yes'];
        $this->withHeader('Referer', 'https://emma-and-james.com/')->post('/f/'.$form['id'], $answer)
            ->assertRedirect(route('builder.form.sent', ['id' => $form['id'], 'back' => 'https://emma-and-james.com/']));
        // Answers sent from our own form page have nowhere else to go back to
        $this->withHeader('Referer', url('/form/'.$form['id']))->post('/f/'.$form['id'], $answer)
            ->assertRedirect(route('builder.survey', ['id' => $form['id'], 'sent' => 1]));
    }

    public function test_disabled_forms_and_other_kinds_are_not_shown(): void
    {
        $token = $this->signIn();
        $form = $this->publish($token);
        BuilderForm::whereKey($form['id'])->update(['disabled_at' => now()]);
        $this->get('/form/'.$form['id'])->assertNotFound();
        $this->post('/f/'.$form['id'], ['name1' => 'Ann'])->assertNotFound();

        $contact = $this->api($token)->postJson('/builder/forms', ['site_name' => 'Shop'])->json('form');
        $this->get('/form/'.$contact['id'])->assertNotFound();
    }
}
