<?php

namespace Tests\Feature;

use App\Mail\BuilderSignInCode;
use App\Models\BuilderSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Made with Owlvix": owners ask to be featured when they publish, the admin approves, /examples shows the site. */
class BuilderShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('builder');
    }

    private function signIn(): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => 'maker@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use (&$code) { $code = $m->code; return true; });

        return $this->postJson('/builder/account/verify', ['email' => 'maker@example.com', 'code' => $code])->assertOk()->json('token');
    }

    private function publish(string $token, array $extra = [])
    {
        $html = '<!DOCTYPE html><html><head><title>Hi</title><style>:root{--primary:#D97706;--c:#d97706}</style></head><body><h1>Golden Crust</h1></body></html>';

        return $this->withHeaders(['X-Builder-Token' => $token])->postJson('/builder/publish', ['slug' => 'golden-crust', 'name' => 'Golden Crust Bakery', 'pages' => [['slug' => 'index', 'title' => 'Home', 'html' => $html]]] + $extra);
    }

    public function test_asking_approving_and_showing_on_the_gallery(): void
    {
        $token = $this->signIn();
        $this->publish($token, ['kind' => 'store', 'showcase' => true])->assertOk();
        $site = BuilderSite::firstWhere('slug', 'golden-crust');
        $this->assertSame(['store', '#d97706', 'asked'], [$site->kind, $site->color, $site->showcase]);
        $this->get('/examples')->assertDontSee('Made with Owlvix'); // not until the admin says yes

        $this->post("/admin/builder/sites/{$site->id}/showcase", ['act' => 'approve'])->assertRedirect(); // admins only
        $this->assertSame('asked', $site->fresh()->showcase);
        $this->withSession(['is_admin' => true])->get('/admin/builder')->assertSee('Wants to be featured')->assertSee('Feature it');
        $this->withSession(['is_admin' => true])->post("/admin/builder/sites/{$site->id}/showcase", ['act' => 'approve'])->assertRedirect();
        $this->get('/examples')->assertSee('Made with Owlvix')->assertSee('Golden Crust Bakery')->assertSee('Online store')->assertSee($site->url(), false);

        // publishing again (new content) sends it back to the admin to look at, and off the gallery until then
        $this->publish($token, ['kind' => 'store'])->assertOk();
        $this->assertSame('asked', $site->fresh()->showcase);
        $this->get('/examples')->assertDontSee('Golden Crust Bakery');
        // unticking takes it out of the queue
        $this->publish($token, ['kind' => 'store', 'showcase' => false])->assertOk();
        $this->assertNull($site->fresh()->showcase);
        $this->get('/examples')->assertDontSee('Made with Owlvix');
    }

    public function test_a_declined_site_does_not_ask_again_and_offline_sites_are_hidden(): void
    {
        $token = $this->signIn();
        $this->publish($token, ['showcase' => true])->assertOk();
        $site = BuilderSite::firstWhere('slug', 'golden-crust');
        $this->withSession(['is_admin' => true])->post("/admin/builder/sites/{$site->id}/showcase", ['act' => 'decline']);
        $this->publish($token, ['showcase' => true])->assertOk();
        $this->assertSame('no', $site->fresh()->showcase);

        $site->forceFill(['showcase' => 'yes'])->save();
        $this->get('/examples')->assertSee('Golden Crust Bakery');
        $site->update(['status' => 'removed']);
        $this->get('/examples')->assertDontSee('Golden Crust Bakery');
    }

    public function test_bad_kinds_are_refused_and_the_account_shows_the_choice(): void
    {
        $token = $this->signIn();
        $this->publish($token, ['kind' => 'nope'])->assertStatus(422);
        $this->publish($token, ['showcase' => true])->assertOk();
        $this->withHeaders(['X-Builder-Token' => $token])->getJson('/builder/account')->assertJsonPath('sites.0.showcase', 'asked');
    }
}
