<?php

namespace Tests\Feature;

use App\Mail\BuilderSignInCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Saving a project from two devices: the second save is asked about instead of quietly writing over the first. */
class BuilderProjectConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_save_based_on_an_older_copy_is_refused_until_confirmed(): void
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => 'maker@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $token = $this->postJson('/builder/account/verify', ['email' => 'maker@example.com', 'code' => $code])->json('token');
        $api = fn () => $this->withHeaders(['X-Builder-Token' => $token]);

        $first = $api()->postJson('/builder/projects', ['kind' => 'email', 'name' => 'Sale', 'data' => ['v' => 1]])->assertOk()->json('project');
        $this->travel(5)->seconds();
        // the laptop saves (based on the copy it opened)…
        $laptop = $api()->postJson('/builder/projects', ['id' => $first['id'], 'kind' => 'email', 'data' => ['v' => 2], 'base' => $first['updated']])->assertOk()->json('project');
        $this->travel(5)->seconds();
        // …then the phone, still on the first copy, is asked first
        $api()->postJson('/builder/projects', ['id' => $first['id'], 'kind' => 'email', 'data' => ['v' => 3], 'base' => $first['updated']])->assertStatus(409);
        $this->assertSame(['v' => 2], $api()->getJson('/builder/projects/'.$first['id'])->json('project.data'));
        // "Save mine over it" (no base) writes it; a save based on the newest copy goes through as normal
        $api()->postJson('/builder/projects', ['id' => $first['id'], 'kind' => 'email', 'data' => ['v' => 3]])->assertOk();
        $now = $api()->getJson('/builder/projects/'.$first['id'])->json('project');
        $api()->postJson('/builder/projects', ['id' => $first['id'], 'kind' => 'email', 'data' => ['v' => 4], 'base' => $now['updated']])->assertOk();
        $this->assertNotSame($laptop['updated'], $now['updated']);
    }
}
