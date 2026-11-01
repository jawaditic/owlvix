<?php

namespace Tests\Feature;

use App\Models\BuilderAccount;
use App\Models\BuilderProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Projects saved to a builder account by the page builders and the email builder. */
class BuilderProjectsTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $email = 'maker@example.com')
    {
        return $this->withHeaders(['X-Builder-Token' => BuilderAccount::firstOrCreate(['email' => $email])->issueToken()]);
    }

    private function project(): array
    {
        return ['v' => 3, 'site' => ['name' => 'Bean Bros', 'colors' => ['primary' => '#2563eb']], 'pages' => [['id' => 'p1', 'slug' => 'index', 'title' => 'Home', 'sections' => []]]];
    }

    public function test_save_list_open_update_and_delete(): void
    {
        $this->postJson('/builder/projects', ['kind' => 'website', 'name' => 'x', 'data' => $this->project()])->assertStatus(401);

        $api = $this->as();
        $r = $api->postJson('/builder/projects', ['kind' => 'website', 'name' => 'Bean Bros', 'data' => $this->project()])->assertOk()->json('project');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $r['id']);
        $this->assertSame('Bean Bros', $r['name']);

        $list = $api->getJson('/builder/projects?kind=website')->assertOk()->json('projects');
        $this->assertCount(1, $list);
        $this->assertSame([], $api->getJson('/builder/projects?kind=portfolio')->json('projects'));
        $api->getJson('/builder/projects?kind=nope')->assertStatus(422);

        $open = $api->getJson('/builder/projects/'.$r['id'])->assertOk()->json('project');
        $this->assertSame('#2563eb', $open['data']['site']['colors']['primary']);

        $data = $this->project();
        $data['site']['name'] = 'Bean Brothers';
        $api->postJson('/builder/projects', ['id' => $r['id'], 'kind' => 'website', 'name' => 'Bean Brothers', 'data' => $data])->assertOk();
        $this->assertSame(1, BuilderProject::count());
        $this->assertSame('Bean Brothers', $api->getJson('/builder/projects/'.$r['id'])->json('project.data.site.name'));

        // a project can't change builder
        $api->postJson('/builder/projects', ['id' => $r['id'], 'kind' => 'email', 'name' => 'x', 'data' => $data])->assertStatus(422);

        $api->postJson('/builder/projects/'.$r['id'].'/delete')->assertOk();
        $this->assertSame(0, BuilderProject::count());
        $api->getJson('/builder/projects/'.$r['id'])->assertStatus(404);
    }

    public function test_other_accounts_cannot_see_or_change_a_project(): void
    {
        $id = $this->as('a@example.com')->postJson('/builder/projects', ['kind' => 'email', 'name' => 'Mine', 'data' => ['blocks' => []]])->json('project.id');

        $other = $this->as('b@example.com');
        $other->getJson('/builder/projects/'.$id)->assertStatus(404);
        $other->postJson('/builder/projects', ['id' => $id, 'kind' => 'email', 'name' => 'Stolen', 'data' => ['x' => 1]])->assertStatus(404);
        $other->postJson('/builder/projects/'.$id.'/delete')->assertStatus(404);
        $this->assertSame([], $other->getJson('/builder/projects?kind=email')->json('projects'));
        $this->assertSame('Mine', BuilderProject::find($id)->name);
    }

    public function test_limits(): void
    {
        $api = $this->as();
        // names are trimmed and plain text
        $this->assertSame('Hi', $api->postJson('/builder/projects', ['kind' => 'links', 'name' => ' <b>Hi</b> ', 'data' => ['a' => 1]])->json('project.name'));
        // too big
        $api->postJson('/builder/projects', ['kind' => 'links', 'name' => 'Big', 'data' => ['pic' => str_repeat('a', BuilderProject::MAX_BYTES)]])->assertStatus(413);
        // how many per builder
        for ($i = BuilderProject::count(); $i < BuilderProject::MAX_PER_KIND; $i++) {
            BuilderProject::forceCreate(['id' => str_pad((string) $i, 16, 'x'), 'builder_account_id' => BuilderAccount::first()->id, 'kind' => 'links', 'name' => 'n', 'data' => '{}']);
        }
        $api->postJson('/builder/projects', ['kind' => 'links', 'name' => 'One more', 'data' => ['a' => 1]])->assertStatus(422);
        $api->postJson('/builder/projects', ['kind' => 'soon', 'name' => 'Other builder', 'data' => ['a' => 1]])->assertOk();
    }
}
