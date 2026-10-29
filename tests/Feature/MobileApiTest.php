<?php

namespace Tests\Feature;

use App\Mail\EnquiryReply;
use App\Mail\NewEnquiry;
use App\Mail\QuoteReceived;
use App\Models\AdminToken;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The API used by the mobile app (routes/api.php). */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        config([
            'admin.email' => 'owner@example.com',
            'admin.password_hash' => Hash::make('secret-pass'),
            'services.recaptcha.site_key' => null,
        ]);
    }

    private function token(): string
    {
        return $this->postJson('/api/admin/login', ['email' => 'owner@example.com', 'password' => 'secret-pass', 'device' => 'Test phone'])
            ->assertOk()->json('token');
    }

    public function test_site_data_lists_published_projects_and_form_options(): void
    {
        Project::query()->delete(); // the migration adds starter projects
        Project::create(['title' => 'Shown', 'category' => 'website', 'image' => 'x.jpg', 'is_published' => true]);
        Project::create(['title' => 'Hidden', 'category' => 'website', 'image' => 'y.jpg', 'is_published' => false]);

        $this->getJson('/api/site')->assertOk()
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.title', 'Shown')
            ->assertJsonStructure(['reviews' => ['rating', 'count', 'items'], 'form' => ['services', 'budgets'], 'categories']);
    }

    public function test_quote_from_app_is_saved_emailed_and_pushed(): void
    {
        $admin = AdminToken::issue('phone');
        AdminToken::first()->update(['push_token' => 'ExponentPushToken[abc]']);

        $this->postJson('/api/contact', [
            'name' => 'Sara Khan', 'email' => 'sara@example.com', 'service' => 'Mobile app',
            'message' => "I need a booking app.\n\nWith payments.",
        ])->assertCreated()->assertJsonPath('first_name', 'Sara');

        $m = ContactMessage::first();
        $this->assertSame('app', $m->source);
        $this->assertSame("<p>I need a booking app.</p><p>With payments.</p>", $m->message);
        Mail::assertSent(QuoteReceived::class);
        Mail::assertSent(NewEnquiry::class);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'exp.host') && $r[0]['to'] === 'ExponentPushToken[abc]');
    }

    public function test_quote_validation_errors_come_back_as_json(): void
    {
        $this->postJson('/api/contact', ['name' => 'S', 'email' => 'bad', 'message' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_login_rejects_wrong_password_and_admin_routes_need_a_token(): void
    {
        $this->postJson('/api/admin/login', ['email' => 'owner@example.com', 'password' => 'nope'])->assertStatus(422);
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
        $this->withToken('made-up')->getJson('/api/admin/messages')->assertStatus(401);
    }

    public function test_admin_can_read_reply_and_delete_messages(): void
    {
        $token = $this->token();
        $m = ContactMessage::create(['name' => 'Ali', 'email' => 'ali@example.com', 'message' => '<p>Please build my shop</p>', 'source' => 'popup']);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('stats.unread', 1);
        $this->withToken($token)->getJson('/api/admin/messages?status=unread')->assertOk()->assertJsonPath('data.0.id', $m->id);
        $this->withToken($token)->getJson("/api/admin/messages/{$m->id}")->assertOk()->assertJsonPath('message_text', 'Please build my shop');
        $this->assertNotNull($m->fresh()->read_at);

        $this->withToken($token)->postJson("/api/admin/messages/{$m->id}/reply", ['subject' => 'Re: shop', 'body' => 'Happy to help'])
            ->assertOk()->assertJsonCount(1, 'replies');
        Mail::assertSent(EnquiryReply::class);

        $this->withToken($token)->patchJson("/api/admin/messages/{$m->id}/read")->assertJsonPath('unread', true);
        $this->withToken($token)->deleteJson("/api/admin/messages/{$m->id}")->assertOk();
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_admin_traffic_push_token_and_logout(): void
    {
        $token = $this->token();
        $this->withToken($token)->getJson('/api/admin/traffic?days=7')->assertOk()->assertJsonPath('days', 7);
        $this->withToken($token)->postJson('/api/admin/push-token', ['push_token' => 'ExponentPushToken[xyz]'])->assertOk();
        $this->assertSame('ExponentPushToken[xyz]', AdminToken::first()->push_token);
        $this->withToken($token)->postJson('/api/admin/push-token', ['push_token' => 'not-a-token'])->assertStatus(422);

        $this->withToken($token)->postJson('/api/admin/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    public function test_admin_manages_projects_with_image_upload(): void
    {
        $token = $this->token();
        $res = $this->withToken($token)->post('/api/admin/projects', [
            'title' => 'New App', 'category' => 'mobile', 'is_published' => '1',
            'image' => UploadedFile::fake()->image('shot.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated();
        $p = Project::find($res->json('id'));
        $this->assertFileExists(public_path($p->image));
        $webp = public_path(preg_replace('/\.jpg$/', '.webp', $p->image));
        $this->assertFileExists($webp, 'A smaller WebP copy is saved next to the upload');

        $this->withToken($token)->postJson("/api/admin/projects/{$p->id}", ['title' => 'Renamed', 'category' => 'mobile', 'is_published' => false])
            ->assertOk()->assertJsonPath('title', 'Renamed')->assertJsonPath('is_published', false);
        $this->withToken($token)->postJson("/api/admin/projects/{$p->id}/toggle")->assertJsonPath('is_published', true);
        $this->withToken($token)->deleteJson("/api/admin/projects/{$p->id}")->assertOk();
        $this->assertFileDoesNotExist(public_path($p->image));
        $this->assertFileDoesNotExist($webp);
    }

    public function test_admin_manages_reviews_and_rating_summary(): void
    {
        Testimonial::query()->delete(); // the migration adds starter reviews
        $token = $this->token();
        $a = $this->withToken($token)->postJson('/api/admin/reviews', ['name' => 'Ann', 'text' => 'Great work!', 'rating' => 5, 'is_published' => true])->assertCreated()->json('id');
        $b = $this->withToken($token)->postJson('/api/admin/reviews', ['name' => 'Bob', 'text' => 'Very good', 'rating' => 4])->assertCreated()->json('id');

        $this->withToken($token)->postJson("/api/admin/reviews/{$b}/move/up")->assertOk()->assertJsonPath('data.0.id', $b);
        $this->withToken($token)->postJson("/api/admin/reviews/{$a}/toggle/in_email")->assertJsonPath('in_email', true);
        $this->withToken($token)->putJson("/api/admin/reviews/{$a}", ['name' => 'Ann B', 'text' => 'Great work!', 'rating' => 5])->assertJsonPath('name', 'Ann B');
        $this->withToken($token)->putJson('/api/admin/reviews/settings', [
            'reviews_profile_url' => 'https://www.fiverr.com/x', 'reviews_rating' => 4.9, 'reviews_count' => 10, 'reviews_five_star' => 12,
        ])->assertStatus(422)->assertJsonValidationErrors('reviews_five_star');
        $this->withToken($token)->deleteJson("/api/admin/reviews/{$a}")->assertOk();
        $this->assertSame(1, Testimonial::count());
    }
}
