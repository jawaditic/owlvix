<?php

namespace Tests\Feature;

use App\Mail\BuilderAppBuilt;
use App\Mail\BuilderSignInCode;
use App\Models\BuilderApp;
use App\Models\BuilderAppBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mobile app builder (/mobile-app-builder): saving apps and their native screens, the static content file phones
 * read, the website check (for website tabs), starting builds and the build server's calls.
 */
class AppBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->public = storage_path('framework/testing/app-data-'.bin2hex(random_bytes(4)));
        config([
            'builder.apps.public_dir' => $this->public,
            'app.url' => 'https://owlvix.test',
            'builder.domain' => 'sites.owlvix.test',
            'builder.apps.github_repo' => 'owlvix/owlvix',
            'builder.apps.github_token' => 'gh-token',
            'builder.apps.monthly_limit' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        parent::tearDown();
    }

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

    private function publishSite(string $token, string $slug = 'joes-bakery'): string
    {
        $html = '<!DOCTYPE html><html><head><title>Hi</title></head><body><h1>Hello</h1></body></html>';
        $this->api($token)->postJson('/builder/publish', ['slug' => $slug, 'name' => "Joe's Bakery", 'pages' => [['slug' => 'index', 'title' => 'Home', 'html' => $html]]])->assertOk();

        return 'https://sites.owlvix.test/'.$slug;
    }

    private function png(int $size = 1024): string
    {
        $img = imagecreatetruecolor($size, $size);
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    private function jpeg(int $w = 200, int $h = 100, int $color = 0x3366cc): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, $color);
        ob_start();
        imagejpeg($img);

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    /** A native app: three screens of blocks, no website. */
    private function app(array $extra = []): array
    {
        return array_replace_recursive([
            'name' => 'Joe’s Bakery',
            'package' => 'com.joesbakery.app',
            'url' => '',
            'version_name' => '1.0.0',
            'external_links' => 'browser',
            'status_bar' => 'dark',
            'colors' => ['primary' => '#16A34A', 'bar' => '#ffffff', 'icon' => '#16a34a', 'splash' => '#ffffff'],
            'offline' => ['title' => '', 'text' => 'No internet', 'button' => ''],
            'tabs' => [
                ['label' => 'Home', 'icon' => 'home', 'type' => 'screen', 'blocks' => [
                    ['type' => 'hero', 'title' => 'Fresh bread', 'text' => 'Every morning', 'image' => null, 'button' => ['label' => 'Our menu', 'action' => ['kind' => 'tab', 'value' => 1]]],
                    ['type' => 'heading', 'text' => '', 'align' => 'left'], // empty: dropped
                    ['type' => 'text', 'text' => "Line one\nLine two", 'align' => 'center'],
                    ['type' => 'button', 'label' => 'Call us', 'style' => 'outline', 'action' => ['kind' => 'tel', 'value' => '+1 555 123 4567']],
                    ['type' => 'video', 'url' => 'https://youtu.be/aqz-KE-bpKQ', 'title' => 'Our bakery'],
                    ['type' => 'divider'],
                ]],
                ['label' => 'Menu', 'icon' => 'food', 'type' => 'screen', 'blocks' => [
                    ['type' => 'list', 'title' => 'Bread', 'items' => [
                        ['title' => 'Sourdough', 'text' => 'Slow rise', 'price' => '$6', 'image' => null, 'action' => ['kind' => '', 'value' => '']],
                        ['title' => '', 'text' => '', 'price' => '', 'image' => null], // empty: dropped
                    ]],
                ]],
                ['label' => 'Odd', 'icon' => 'not-an-icon', 'type' => 'screen', 'blocks' => [
                    ['type' => 'contact', 'title' => 'Find us', 'phone' => '+1 555 123 4567', 'whatsapp' => '+44 7700 900123', 'email' => 'joe@example.com', 'address' => '1 Bread St', 'website' => ''],
                    ['type' => 'hours', 'title' => 'Open', 'rows' => [['day' => 'Mon', 'time' => '9–5'], ['day' => '', 'time' => '']]],
                ]],
            ],
        ], $extra);
    }

    /** An app with tabs that show pages of a website. */
    private function webApp(string $url = 'https://sites.owlvix.test/joes-bakery', array $extra = []): array
    {
        $app = $this->app(['url' => $url] + $extra);
        $app['tabs'] = [
            ['label' => 'Home', 'icon' => 'home', 'type' => 'web', 'url' => $url],
            ['label' => 'Menu', 'icon' => 'food', 'type' => 'web', 'url' => $url.'/menu'],
            ['label' => 'Info', 'icon' => 'info', 'type' => 'screen', 'blocks' => [['type' => 'text', 'text' => 'Hi']]],
        ];

        return $app;
    }

    private function contentFile(string $id): array
    {
        return json_decode(File::get($this->public."/$id/content.json"), true);
    }

    private function save(string $token, array $app = [], bool $icon = true)
    {
        return $this->api($token)->postJson('/builder/apps', ['app' => $app ?: $this->app()] + ($icon ? ['icon' => $this->png(), 'foreground' => $this->png()] : []));
    }

    public function test_page_loads_and_is_listed(): void
    {
        $this->get(route('app-builder'))->assertOk()->assertSee('js/app-builder', false)->assertSee('id="ab-icons"', false);
        $this->get(route('free-tools'))->assertOk()->assertSee(route('app-builder'));
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('app-builder'));
        $this->postJson('/builder/apps', [])->assertStatus(401);
    }

    public function test_saving_a_native_app_publishes_its_screens_as_a_static_file(): void
    {
        $token = $this->signIn();

        $r = $this->save($token)->assertOk()->json('app');
        $this->assertTrue($r['allowed']); // no website: nothing to check
        $this->assertFalse($r['needs_website']);
        $this->assertFalse($r['own_site']);
        $this->assertTrue($r['has_icon']);
        $this->assertSame('#16a34a', $r['settings']['colors']['primary']);
        $this->assertSame('You’re offline', $r['settings']['offline']['title']);
        $this->assertSame('home', $r['tabs'][2]['icon']); // unknown icon names fall back
        $this->assertSame("https://owlvix.test/app-data/{$r['id']}/content.json", $r['content_url']);

        // Blocks are cleaned: empty ones dropped, actions and videos tidied
        $home = $r['tabs'][0]['blocks'];
        $this->assertSame(['hero', 'text', 'button', 'video', 'divider'], array_column($home, 'type'));
        $this->assertSame(['label' => 'Our menu', 'action' => ['kind' => 'tab', 'value' => 1]], $home[0]['button']);
        $this->assertSame("Line one\nLine two", $home[1]['text']);
        $this->assertSame('center', $home[1]['align']);
        $this->assertSame('outline', $home[2]['style']);
        $this->assertSame('aqz-KE-bpKQ', $home[3]['id']);
        $this->assertCount(1, $r['tabs'][1]['blocks'][0]['items']);
        $this->assertArrayNotHasKey('action', $r['tabs'][1]['blocks'][0]['items'][0]);
        $this->assertSame('447700900123', $r['tabs'][2]['blocks'][0]['whatsapp']);
        $this->assertCount(1, $r['tabs'][2]['blocks'][1]['rows']);

        // What phones read: a static file with everything that can change without a new build
        $doc = $this->contentFile($r['id']);
        $this->assertSame(1, $doc['v']);
        $this->assertGreaterThan(0, $doc['rev']);
        $this->assertSame("https://owlvix.test/app-data/{$r['id']}/", $doc['base']);
        $this->assertSame($r['tabs'], $doc['tabs']);
        $this->assertSame([], $doc['hosts']);
        $this->assertSame(['primary' => '#16a34a', 'bar' => '#ffffff'], $doc['colors']);
        $this->assertStringContainsString('Require all denied', File::get($this->public.'/.htaccess'));

        $app = BuilderApp::find($r['id']);
        Storage::disk('local')->assertExists($app->dir().'/icon.png');
        $this->assertNull($app->keystore);
        $this->assertNotEmpty($app->keystore_password);
        $this->assertArrayNotHasKey('keystore_password', $r);

        // Updating keeps the icon when none is sent, and the content's number goes up
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $this->app(['name' => 'Joe'])])->assertOk()->assertJsonPath('app.name', 'Joe')->assertJsonPath('app.has_icon', true);
        $this->assertGreaterThan($doc['rev'], $this->contentFile($r['id'])['rev']);
    }

    public function test_pictures_are_stored_beside_the_content_and_tidied(): void
    {
        $token = $this->signIn();
        $photo = $this->jpeg(400, 200);
        $app = $this->app();
        $app['tabs'][0]['blocks'][0]['image'] = ['src' => $photo];
        $app['tabs'][1]['blocks'][] = ['type' => 'gallery', 'columns' => 3, 'images' => [['src' => $photo], ['src' => $this->jpeg(100, 100, 0xff0000)]]];
        $r = $this->save($token, $app)->assertOk()->json('app');

        // Named like the builder names it: the first 20 hex of its SHA-256
        $name = substr(hash('sha256', base64_decode(substr($photo, 23))), 0, 20).'.jpg';
        $this->assertSame(['src' => $name, 'w' => 400, 'h' => 200], $r['tabs'][0]['blocks'][0]['image']);
        $gallery = $r['tabs'][1]['blocks'][1];
        $this->assertSame(3, $gallery['columns']);
        $this->assertSame($name, $gallery['images'][0]['src']); // the same picture is stored once
        $this->assertFileExists($this->public."/{$r['id']}/img/$name");
        $this->assertCount(2, File::files($this->public."/{$r['id']}/img"));

        // Saved pictures are sent back by name; width and height come from the file, not the builder
        $app['tabs'][0]['blocks'][0]['image'] = ['src' => $name, 'w' => 1, 'h' => 1];
        unset($app['tabs'][1]['blocks'][1]); // gallery removed: its other picture is deleted
        $r = $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $app])->assertOk()->json('app');
        $this->assertSame(['src' => $name, 'w' => 400, 'h' => 200], $r['tabs'][0]['blocks'][0]['image']);
        $this->assertCount(1, File::files($this->public."/{$r['id']}/img"));

        // A name that isn't one of this app's pictures is refused
        foreach ([str_repeat('a', 20).'.jpg', '../../.env', 'x.php'] as $bad) {
            $app['tabs'][0]['blocks'][0]['image'] = ['src' => $bad];
            $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $app])->assertStatus(422);
        }
        // Something that isn't a picture, or isn't what it says it is, is refused
        $app['tabs'][0]['blocks'][0]['image'] = ['src' => 'data:image/jpeg;base64,'.base64_encode('<?php echo 1;')];
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $app])->assertStatus(422);
        $app['tabs'][0]['blocks'][0]['image'] = ['src' => str_replace('image/png', 'image/jpeg', $this->png(64))];
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $app])->assertStatus(422);
        $this->assertCount(1, File::files($this->public."/{$r['id']}/img"));
    }

    public function test_blocks_are_checked(): void
    {
        $token = $this->signIn();
        $with = function (array $block) {
            $app = $this->app();
            $app['tabs'][0]['blocks'] = [$block];

            return $app;
        };
        $this->save($token, $with(['type' => 'script', 'text' => 'x']))->assertStatus(422);
        $this->save($token, $with(['type' => 'button', 'label' => 'Go', 'action' => ['kind' => 'url', 'value' => 'http://example.com']]))->assertStatus(422)
            ->assertJsonFragment(['error' => '“Home”, block 1: links must start with https://']);
        $this->save($token, $with(['type' => 'button', 'label' => 'Go', 'action' => ['kind' => 'url', 'value' => 'javascript:alert(1)']]))->assertStatus(422);
        $this->save($token, $with(['type' => 'button', 'label' => 'Go', 'action' => ['kind' => 'tab', 'value' => 7]]))->assertStatus(422);
        $this->save($token, $with(['type' => 'button', 'label' => 'Go', 'action' => ['kind' => 'tel', 'value' => '']]))->assertStatus(422)
            ->assertJsonFragment(['error' => '“Home”, block 1: a button needs both a label and what it does.']);
        $this->save($token, $with(['type' => 'button', 'label' => 'Go', 'action' => ['kind' => 'tel', 'value' => 'call me']]))->assertStatus(422);
        $this->save($token, $with(['type' => 'contact', 'email' => 'not-an-email']))->assertStatus(422);
        $this->save($token, $with(['type' => 'video', 'url' => 'https://vimeo.com/123']))->assertStatus(422);
        $this->save($token, $with(['type' => 'text', 'text' => str_repeat('a', 3001)]))->assertStatus(422);
        $this->save($token, $with(['type' => 'list', 'items' => array_fill(0, 51, ['title' => 'x'])]))->assertStatus(422);
        $app = $this->app();
        $app['tabs'][0]['blocks'] = array_fill(0, 41, ['type' => 'divider']);
        $this->save($token, $app)->assertStatus(422);
        $app = $this->app();
        $app['tabs'] = array_fill(0, 6, ['label' => 'A', 'icon' => 'home', 'type' => 'screen', 'blocks' => []]);
        $this->save($token, $app)->assertStatus(422);
        $this->save($token, ['tabs' => []] + $this->app())->assertStatus(422);
        $this->assertSame(0, BuilderApp::count());

        // A map action keeps the address
        $r = $this->save($token, $with(['type' => 'image', 'image' => ['src' => $this->jpeg()], 'action' => ['kind' => 'map', 'value' => '1 Bread St, Leeds'], 'round' => true]))->assertOk()->json('app');
        $this->assertSame(['kind' => 'map', 'value' => '1 Bread St, Leeds'], $r['tabs'][0]['blocks'][0]['action']);
        $this->assertTrue($r['tabs'][0]['blocks'][0]['round']);
    }

    public function test_settings_are_checked(): void
    {
        $token = $this->signIn();
        $this->publishSite($token);

        $this->save($token, $this->app(['package' => 'Bakery']))->assertStatus(422);
        $this->save($token, $this->app(['package' => 'com.owlvix.mine']))->assertStatus(422)->assertJsonFragment(['error' => 'That app ID is reserved. Use your own business name, e.g. com.yourbusiness.app.']);
        $this->save($token, $this->webApp('http://sites.owlvix.test/joes-bakery'))->assertStatus(422);
        $this->save($token, $this->webApp('https://192.168.1.10'))->assertStatus(422);
        $this->save($token, $this->app(['version_name' => 'v1']))->assertStatus(422);
        $this->save($token, $this->app(['colors' => ['bar' => 'red']]))->assertStatus(422);
        // A website tab off the website, or with no website at all
        $app = $this->webApp();
        $app['tabs'][1]['url'] = 'https://evil.example.com/';
        $this->save($token, $app)->assertStatus(422);
        $app = $this->webApp();
        $app['url'] = '';
        $this->save($token, $app)->assertStatus(422);
        // A tab on someone else's builder site
        $app = $this->webApp();
        $app['tabs'][1]['url'] = 'https://sites.owlvix.test/other-site';
        $this->save($token, $app)->assertStatus(422);
        // Icon must be a 1024×1024 PNG
        $this->api($token)->postJson('/builder/apps', ['app' => $this->app(), 'icon' => $this->png(500), 'foreground' => $this->png()])->assertStatus(422);
        $this->api($token)->postJson('/builder/apps', ['app' => $this->app()])->assertStatus(422);
        $this->assertSame(0, BuilderApp::count());

        // Website tabs on your own builder site are fine and need no tag
        $r = $this->save($token, $this->webApp())->assertOk()->json('app');
        $this->assertTrue($r['needs_website']);
        $this->assertTrue($r['allowed']);
        $this->assertTrue($r['own_site']);
        $this->assertSame(['sites.owlvix.test'], $this->contentFile($r['id'])['hosts']);
        $this->assertSame(['label' => 'Menu', 'icon' => 'food', 'type' => 'web', 'url' => 'https://sites.owlvix.test/joes-bakery/menu'], $r['tabs'][1]);
    }

    public function test_you_cant_wrap_someone_elses_builder_site_or_take_their_app_id(): void
    {
        $owner = $this->signIn('owner@example.com');
        $this->publishSite($owner);
        $this->save($owner, $this->webApp())->assertOk();

        $other = $this->signIn('other@example.com');
        $this->save($other, $this->webApp(extra: ['package' => 'com.other.app']))->assertStatus(422);
        $this->save($other)->assertStatus(422)
            ->assertJsonFragment(['error' => 'That app ID is already used by another app. Please pick a different one.']);
        // and can't see or change the owner's app
        $id = BuilderApp::first()->id;
        $this->api($other)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app()])->assertStatus(404);
        $this->api($other)->postJson("/builder/apps/{$id}/build")->assertStatus(404);
        $this->api($other)->getJson("/builder/apps/{$id}/keystore")->assertStatus(404);
        $this->api($other)->postJson("/builder/apps/{$id}/delete")->assertStatus(404);
    }

    public function test_other_websites_must_be_verified_before_building(): void
    {
        Http::fake();
        $token = $this->signIn();
        $r = $this->save($token, $this->webApp('https://www.joes.example'))->assertOk()->json();
        $this->assertTrue($r['app']['needs_website']);
        $this->assertFalse($r['app']['allowed']);
        $this->assertStringContainsString('<meta name="owlvix-app" content="', $r['app']['tag']);

        $this->api($token)->postJson("/builder/apps/{$r['app']['id']}/build")->assertStatus(422);
        Http::assertNothingSent();

        // Once verified (the tag was found on that host) it can build; changing the host needs a new check
        $app = BuilderApp::find($r['app']['id']);
        $app->update(['verified_host' => 'www.joes.example']);
        $this->assertTrue($app->fresh()->toApi()['allowed']);
        $app->update(['url' => 'https://shop.joes.example/']);
        $this->assertFalse($app->fresh()->toApi()['allowed']);

        // Without website tabs there's nothing to check
        $this->api($token)->postJson('/builder/apps', ['id' => $app->id, 'app' => $this->app(['url' => 'https://www.joes.example'])])->assertOk()
            ->assertJsonPath('app.needs_website', false)->assertJsonPath('app.allowed', true);
    }

    public function test_building_dispatches_to_github_and_the_runner_delivers_the_app(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $token = $this->signIn();
        $this->publishSite($token);
        $id = $this->save($token, $this->webApp())->json('app.id');

        $r = $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk()->json('app');
        $this->assertSame('queued', $r['build']['status']);
        $this->assertSame(1, $r['build']['version_code']);
        $runner = null;
        Http::assertSent(function ($req) use (&$runner) {
            $runner = $req['inputs']['token'];

            return $req->url() === 'https://api.github.com/repos/owlvix/owlvix/actions/workflows/build-android-app.yml/dispatches'
                && $req->hasHeader('Authorization', 'Bearer gh-token')
                && $req['ref'] === 'main'
                && str_ends_with($req['inputs']['api'], '/api/app-builds/'.BuilderAppBuild::first()->id);
        });

        // Only one build at a time
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(422);

        $build = BuilderAppBuild::first();
        $base = "/api/app-builds/{$build->id}";
        // Wrong pass
        $this->withToken('nope')->getJson("$base/job")->assertStatus(403);

        $job = $this->withToken($runner)->getJson("$base/job")->assertOk()->json('job');
        $this->assertSame('com.joesbakery.app', $job['package']);
        $this->assertSame(1, $job['versionCode']);
        $this->assertSame("https://owlvix.test/app-data/{$id}/content.json", $job['contentUrl']);
        $this->assertSame($this->contentFile($id), $job['content']); // built in, so the app works offline from the start
        $this->assertSame(['sites.owlvix.test'], $job['content']['hosts']);
        $this->assertSame('#16a34a', $job['colors']['primary']);
        $this->assertNull($job['keystore']);
        $this->assertSame(BuilderApp::find($id)->keystore_password, $job['keystorePassword']);
        $this->assertSame('building', $build->fresh()->status);

        // Files arrive in parts
        $apk = random_bytes(3000);
        $this->call('POST', "$base/upload?kind=apk&part=0&offset=0", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], substr($apk, 0, 1000))->assertOk();
        $this->call('POST', "$base/upload?kind=apk&part=1&offset=5", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], substr($apk, 1000))->assertStatus(409);
        $this->call('POST', "$base/upload?kind=apk&part=1&offset=1000", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], substr($apk, 1000))->assertOk();
        $aab = random_bytes(2000);
        $this->call('POST', "$base/upload?kind=aab&part=0&offset=0", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], $aab)->assertOk();
        $key = random_bytes(2500);
        $this->call('POST', "$base/upload?kind=keystore&part=0&offset=0", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], $key)->assertOk();

        // A damaged file is refused
        Mail::fake();
        $this->withToken($runner)->postJson("$base/finish", ['status' => 'done', 'sha256' => ['apk' => str_repeat('a', 64), 'aab' => hash('sha256', $aab)]])->assertStatus(422);
        $this->assertSame('failed', $build->fresh()->status);
        Mail::assertSent(BuilderAppBuilt::class);
    }

    public function test_a_finished_build_can_be_downloaded_and_keeps_its_key(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $token = $this->signIn();
        $this->publishSite($token);
        $id = $this->save($token)->json('app.id');
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        $runner = null;
        Http::assertSent(function ($req) use (&$runner) {
            $runner = $req['inputs']['token'];

            return true;
        });
        $build = BuilderAppBuild::first();
        $base = "/api/app-builds/{$build->id}";
        $send = fn ($kind, $bytes) => $this->call('POST', "$base/upload?kind=$kind&part=0&offset=0", [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $runner", 'CONTENT_TYPE' => 'application/octet-stream'], $bytes)->assertOk();
        $apk = random_bytes(3000);
        $aab = random_bytes(2000);
        $key = random_bytes(2500);
        $send('apk', $apk);
        $send('aab', $aab);
        $send('keystore', $key);

        Mail::fake();
        $this->withToken($runner)->postJson("$base/finish", ['status' => 'done', 'sha256' => ['apk' => hash('sha256', $apk), 'aab' => hash('sha256', $aab)]])->assertOk();
        $build->refresh();
        $this->assertSame('done', $build->status);
        $this->assertNull($build->token_hash);
        $app = BuilderApp::find($id);
        $this->assertSame(1, $app->version_code);
        $this->assertSame(base64_encode($key), $app->keystore);
        Mail::assertSent(BuilderAppBuilt::class, fn ($m) => $m->hasTo('maker@example.com'));

        // The pass stops working once the build has finished
        $this->withToken($runner)->getJson("$base/job")->assertStatus(403);

        // Signed download links send you on to the static file (served by the web server, not PHP); unsigned ones don't
        $url = $this->api($token)->getJson('/builder/apps')->json('apps.0.build.files.apk.url');
        $this->assertNotEmpty($url);
        $to = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->assertMatchesRegularExpression("#^https://owlvix\\.test/app-data/{$id}/builds/[a-zA-Z0-9]{32}/joes-bakery-1-0-0\\.apk$#", $to);
        $file = $this->public.'/'.$id.substr($to, strlen("https://owlvix.test/app-data/{$id}"));
        $this->assertSame($apk, File::get($file));
        $this->assertSame($aab, File::get(str_replace('.apk', '.aab', $file)));
        $this->assertDirectoryDoesNotExist(storage_path('app/app-uploads/'.$build->id));
        $this->get("/app-builds/{$build->id}/apk")->assertStatus(403);

        // The owner can download the key
        $this->api($token)->getJson("/builder/apps/{$id}/keystore")->assertOk()->assertJsonPath('keystore', base64_encode($key))->assertJsonPath('alias', 'upload');

        // The next build gets the next number and the saved key, and the app ID is now fixed
        $this->save($token, $this->app(['package' => 'com.joesbakery.newid']), false)->assertStatus(422); // a new app needs an icon
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(['package' => 'com.joesbakery.newid'])])->assertStatus(422);
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk()->assertJsonPath('app.build.version_code', 2);
        $this->assertSame(base64_encode($key), BuilderAppBuild::latest('id')->first()->job['keystore']);
        $this->assertFileExists($file);

        // Deleting removes the files
        $this->api($token)->postJson("/builder/apps/{$id}/delete")->assertOk();
        $this->assertSame(0, BuilderApp::count());
        $this->assertSame(0, BuilderAppBuild::count());
        $this->assertFileDoesNotExist($file);
        $this->assertDirectoryDoesNotExist($this->public.'/'.$id);
    }

    public function test_failed_builds_and_limits(): void
    {
        $github = 204;
        Http::fake(['api.github.com/*' => function () use (&$github) {
            return Http::response('', $github);
        }]);
        $token = $this->signIn();
        $this->publishSite($token);
        $id = $this->save($token)->json('app.id');

        // Switched off
        config(['builder.apps.github_token' => null]);
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(503);
        config(['builder.apps.github_token' => 'gh-token']);

        // Monthly limit
        config(['builder.apps.monthly_limit' => 0]);
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(429);
        config(['builder.apps.monthly_limit' => 100]);

        // GitHub refuses: the build is marked failed straight away
        $github = 500;
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(502);
        $this->assertSame('failed', BuilderAppBuild::first()->status);

        // The runner reports a failure
        $github = 204;
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        $runner = null;
        Http::assertSent(function ($req) use (&$runner) {
            $runner = $req['inputs']['token'] ?? $runner;

            return true;
        });
        $build = BuilderAppBuild::latest('id')->first();
        Mail::fake();
        $this->withToken($runner)->postJson("/api/app-builds/{$build->id}/finish", ['status' => 'failed', 'error' => 'gradle broke'])->assertOk();
        $this->assertSame('failed', $build->fresh()->status);
        $this->assertSame(1, $build->fresh()->version_code); // a failed build doesn't use up a number
        Mail::assertSent(BuilderAppBuilt::class);

        // A build stuck for over an hour counts as failed
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        BuilderAppBuild::latest('id')->first()->forceFill(['created_at' => now()->subHours(2)])->save();
        $this->api($token)->getJson('/builder/apps')->assertOk()->assertJsonPath('apps.0.build.status', 'failed');

        // Daily limit (5 a day per account)
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        BuilderAppBuild::latest('id')->first()->update(['status' => 'failed']);
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(429);
    }

    public function test_icon_design_is_kept_for_other_devices(): void
    {
        $token = $this->signIn();
        $this->publishSite($token);
        $logo = $this->png(300);
        $id = $this->api($token)->postJson('/builder/apps', ['app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(),
            'design' => ['mode' => 'image', 'zoom' => 999, 'letter' => 'JBX', 'fill' => true], 'logo' => $logo])->assertOk()->json('app.id');
        $r = $this->api($token)->getJson("/builder/apps/{$id}/icon")->assertOk()->json();
        $this->assertSame(['mode' => 'image', 'letter' => 'JB', 'zoom' => 70, 'fill' => true], $r['design']);
        $this->assertSame($logo, $r['logo']);
    }

    public function test_max_apps_per_account(): void
    {
        $token = $this->signIn();
        $this->publishSite($token);
        foreach (range(1, BuilderApp::MAX_PER_ACCOUNT) as $i) {
            $this->save($token, $this->app(['package' => "com.joes.app$i"]))->assertOk();
        }
        $this->save($token, $this->app(['package' => 'com.joes.extra']))->assertStatus(422);
    }
}
