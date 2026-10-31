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
            'builder.apps.firebase' => '', // push notifications off unless a test switches them on
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
        $app['tabs'] = array_fill(0, 16, ['label' => 'A', 'icon' => 'home', 'type' => 'screen', 'blocks' => []]);
        $this->save($token, $app)->assertStatus(422);
        // Up to 5 pages in the bottom bar; the rest go in the ☰ menu
        $app['tabs'] = array_fill(0, 6, ['label' => 'A', 'icon' => 'home', 'type' => 'screen', 'bar' => true, 'blocks' => []]);
        $this->save($token, $app)->assertStatus(422);
        $this->save($token, ['tabs' => []] + $this->app())->assertStatus(422);
        $this->assertSame(0, BuilderApp::count());

        // A map action keeps the address
        $r = $this->save($token, $with(['type' => 'image', 'image' => ['src' => $this->jpeg()], 'action' => ['kind' => 'map', 'value' => '1 Bread St, Leeds'], 'round' => true]))->assertOk()->json('app');
        $this->assertSame(['kind' => 'map', 'value' => '1 Bread St, Leeds'], $r['tabs'][0]['blocks'][0]['action']);
        $this->assertTrue($r['tabs'][0]['blocks'][0]['round']);
    }

    public function test_more_blocks_slider_cards_reviews_faq_social_notice_spacer(): void
    {
        $token = $this->signIn();
        $app = $this->app();
        $app['tabs'][0]['blocks'] = [
            ['type' => 'slider', 'images' => [['src' => $this->jpeg(320, 200)], ['src' => $this->jpeg(320, 200, 0x00ff00)]]],
            ['type' => 'slider', 'images' => []], // empty: dropped
            ['type' => 'cards', 'title' => 'Why us', 'columns' => 3, 'items' => [['icon' => 'heart', 'title' => 'Fresh', 'text' => 'Daily', 'action' => ['kind' => 'tab', 'value' => 1]], ['icon' => 'nope', 'title' => 'Fast'], ['title' => '', 'text' => '']]],
            ['type' => 'reviews', 'title' => 'Love', 'items' => [['text' => 'Great!', 'name' => 'Ann', 'stars' => 9], ['text' => '', 'name' => 'Empty']]],
            ['type' => 'faq', 'items' => [['q' => 'Open Sunday?', 'a' => 'Yes, 10–4.']]],
            ['type' => 'social', 'title' => 'Follow', 'links' => ['instagram' => 'https://instagram.com/joes', 'myspace' => 'https://myspace.com/x', 'tiktok' => '']],
            ['type' => 'notice', 'text' => 'Closed Monday', 'tone' => 'warning'],
            ['type' => 'notice', 'text' => 'Hi', 'tone' => 'purple'],
            ['type' => 'spacer', 'size' => 'huge'],
        ];
        $b = $this->save($token, $app)->assertOk()->json('app.tabs.0.blocks');
        $this->assertSame(['slider', 'cards', 'reviews', 'faq', 'social', 'notice', 'notice', 'spacer'], array_column($b, 'type'));
        $this->assertCount(2, $b[0]['images']);
        $this->assertSame(2, $b[1]['columns']); // only 1 or 2
        $this->assertSame([['icon' => 'heart', 'title' => 'Fresh', 'text' => 'Daily', 'action' => ['kind' => 'tab', 'value' => 1]], ['icon' => 'star', 'title' => 'Fast']], $b[1]['items']);
        $this->assertSame([['text' => 'Great!', 'name' => 'Ann', 'stars' => 5]], $b[2]['items']);
        $this->assertSame([['q' => 'Open Sunday?', 'a' => 'Yes, 10–4.']], $b[3]['items']);
        $this->assertSame(['instagram' => 'https://instagram.com/joes'], $b[4]['links']);
        $this->assertSame('warning', $b[5]['tone']);
        $this->assertSame('info', $b[6]['tone']);
        $this->assertSame('medium', $b[7]['size']);

        $with = function (array $block) {
            $app = $this->app();
            $app['tabs'][0]['blocks'] = [$block];

            return $app;
        };
        $this->save($token, $with(['type' => 'faq', 'items' => [['q' => 'No answer?', 'a' => '']]]))->assertStatus(422);
        $this->save($token, $with(['type' => 'social', 'links' => ['facebook' => 'http://facebook.com/x']]))->assertStatus(422);
        $this->save($token, $with(['type' => 'slider', 'images' => array_fill(0, 13, ['src' => 'x'])]))->assertStatus(422);
        $this->save($token, $with(['type' => 'cards', 'items' => array_fill(0, 13, ['title' => 'x'])]))->assertStatus(422);
    }

    public function test_intro_picture_or_video_after_the_splash(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $token = $this->signIn();
        $photo = $this->jpeg(400, 800);
        $r = $this->api($token)->postJson('/builder/apps', ['app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(), 'splash_bg_mode' => 'image', 'splash_bg' => $photo])->assertOk()->json('app');
        $id = $r['id'];
        $this->assertSame('image', $r['settings']['splash']['bg']);
        $this->assertSame($photo, $this->api($token)->getJson("/builder/apps/{$id}/icon")->json('splash_bg'));
        // Not a JPEG, or too small: refused
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(), 'splash_bg_mode' => 'image', 'splash_bg' => $this->jpeg(100, 100)])->assertStatus(422);
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(), 'splash_bg_mode' => 'image', 'splash_bg' => $this->png(400)])->assertStatus(422);

        // The build server fetches it with the build's pass
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        $runner = null;
        Http::assertSent(function ($req) use (&$runner) {
            $runner = $req['inputs']['token'];

            return true;
        });
        $build = BuilderAppBuild::first();
        $this->assertSame('image', $build->job['intro']);
        $this->getJson("/api/app-builds/{$build->id}/asset")->assertStatus(403);
        $this->assertSame(base64_decode(substr($photo, 23)), $this->withToken($runner)->get("/api/app-builds/{$build->id}/asset")->assertOk()->streamedContent());

        // A video arrives in parts; it must be an MP4 and the parts must line up
        $video = "\x00\x00\x00\x18ftypmp42".random_bytes(5000);
        $send = fn ($offset, $part, $total) => $this->call('POST', "/builder/apps/{$id}/splash-video?offset=$offset&total=$total", [], [], [],
            ['HTTP_X_BUILDER_TOKEN' => $token, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/octet-stream'], $part);
        $send(0, 'not a video at all', 5000)->assertStatus(422);
        $send(0, substr($video, 0, 3000), strlen($video))->assertOk()->assertJsonPath('received', 3000);
        $send(10, substr($video, 3000), strlen($video))->assertStatus(409);
        $done = $send(3000, substr($video, 3000), strlen($video))->assertOk()->json('app');
        $this->assertSame('video', $done['settings']['splash']['bg']);
        Storage::disk('local')->assertExists("apps/{$id}/splash-bg.mp4");
        Storage::disk('local')->assertMissing("apps/{$id}/splash-bg.jpg"); // the old picture is gone
        // Someone else can't upload to this app
        $other = $this->signIn('other@example.com');
        $this->call('POST', "/builder/apps/{$id}/splash-video?offset=0&total=5000", [], [], [], ['HTTP_X_BUILDER_TOKEN' => $other, 'HTTP_ACCEPT' => 'application/json'], $video)->assertStatus(404);

        // Back to no intro: the video is removed and builds have none
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(), 'splash_bg_mode' => 'color'])->assertOk()->assertJsonPath('app.settings.splash.bg', 'color');
        Storage::disk('local')->assertMissing("apps/{$id}/splash-bg.mp4");
    }

    public function test_apps_can_be_reported_taken_down_and_restored(): void
    {
        Mail::fake();
        config(['admin.email' => 'admin@owlvix.test']);
        $token = $this->signIn();
        $app = $this->app();
        $app['tabs'][0]['blocks'][] = ['type' => 'image', 'image' => ['src' => $this->jpeg()], 'alt' => 'Our shop front'];
        $r = $this->save($token, $app)->assertOk()->json('app');
        $id = $r['id'];
        $this->assertSame('Our shop front', collect($r['tabs'][0]['blocks'])->firstWhere('type', 'image')['alt']);
        $this->assertSame(route('builder.apps.report', $id), $this->contentFile($id)['report']);

        // Anyone can report it; the admin is emailed
        $this->get("/app-report/{$id}")->assertOk()->assertSee('Report this app');
        $this->post("/app-report/{$id}", ['reason' => 'Fake bank login'])->assertRedirect();
        Mail::assertSent(\App\Mail\BuilderAppReported::class, fn ($m) => $m->hasTo('admin@owlvix.test'));
        $this->assertSame(1, BuilderApp::find($id)->reports);

        // The admin takes it down: phones see "unavailable", pictures go offline, the owner can't change or build it
        $this->get('/admin/builder')->assertRedirect(route('admin.login'));
        $this->post("/admin/builder/apps/{$id}/takedown")->assertRedirect(route('admin.login'));
        $this->withSession(['is_admin' => true])->get('/admin/builder')->assertOk()->assertSee('Mobile apps')->assertSee('Reported 1×');
        $this->withSession(['is_admin' => true])->post("/admin/builder/apps/{$id}/takedown")->assertRedirect();
        $model = BuilderApp::find($id);
        $this->assertFalse($model->isLive());
        $doc = $this->contentFile($id);
        $this->assertSame('This app is no longer available.', $doc['tabs'][0]['blocks'][0]['text']);
        $this->assertCount(0, File::files($this->public."/{$id}/img"));
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app()])->assertStatus(403);
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertStatus(403);
        $this->api($token)->getJson('/builder/apps')->assertJsonPath('apps.0.removed', true);

        // Restored: everything is back
        $this->withSession(['is_admin' => true])->post("/admin/builder/apps/{$id}/restore")->assertRedirect();
        $this->assertTrue(BuilderApp::find($id)->isLive());
        $this->assertCount(1, File::files($this->public."/{$id}/img"));
        $this->assertSame('image', collect($this->contentFile($id)['tabs'][0]['blocks'])->last()['type']);
    }

    public function test_the_builder_says_when_phones_need_a_new_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $token = $this->signIn();
        $id = $this->save($token)->json('app.id');
        $this->assertSame([], $this->api($token)->getJson('/builder/apps')->json('apps.0.out_of_date')); // never built

        // A finished build remembers what it was made from
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        BuilderAppBuild::first()->update(['status' => 'done']);
        $this->assertSame([], $this->api($token)->getJson('/builder/apps')->json('apps.0.out_of_date'));

        // Content changes don't need a build; name and icon do
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(['tabs' => [0 => ['label' => 'Start']]])])->assertOk()->assertJsonPath('app.out_of_date', []);
        $other = imagecreatetruecolor(1024, 1024);
        imagefill($other, 0, 0, 0xff0000);
        ob_start();
        imagepng($other);
        $red = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(['name' => 'Joe New']), 'icon' => $red, 'foreground' => $this->png()])
            ->assertOk()->assertJsonPath('app.out_of_date', ['name', 'icon']);

        // An app built with an older version of the app is flagged too
        BuilderAppBuild::first()->update(['info' => ['shell' => 1] + BuilderAppBuild::first()->info]);
        $this->assertContains('features', $this->api($token)->getJson('/builder/apps')->json('apps.0.out_of_date'));
    }

    public function test_each_app_gets_a_privacy_policy_page(): void
    {
        $token = $this->signIn();
        $r = $this->save($token, $this->app(['privacy' => ['owner' => 'Joe’s Bakery Ltd', 'email' => 'Privacy@Joes.Example', 'extra' => "We also take orders by phone.\nThanks!"]]))->assertOk()->json('app');
        $this->assertSame(['owner' => 'Joe’s Bakery Ltd', 'email' => 'privacy@joes.example', 'extra' => "We also take orders by phone.\nThanks!"], $r['settings']['privacy']);
        $this->assertSame(route('builder.apps.privacy', $r['id']), $r['privacy_url']);
        // What the app does decides what the policy says
        $this->assertSame(['website' => null, 'youtube' => true, 'social' => false, 'contact' => true, 'links' => false, 'push' => false, 'orders' => false], $r['data_use']);

        $page = $this->get($r['privacy_url'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $page->assertSee('Privacy policy for Joe’s Bakery', false)->assertSee('provided by Joe’s Bakery Ltd', false)
            ->assertSee('privacy@joes.example')->assertSee('We also take orders by phone.')->assertSee('YouTube')
            ->assertDontSee('may use cookies'); // no website tab

        $this->save($token, $this->app(['package' => 'com.joes.two', 'privacy' => ['email' => 'not-an-email']]))->assertStatus(422);
        // A taken-down app has no policy page
        BuilderApp::find($r['id'])->update(['status' => 'removed']);
        $this->get($r['privacy_url'])->assertNotFound();
    }

    /** Push notifications on, with a throwaway service account key and Google's servers faked. */
    private function fakeFirebase(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $file = $this->public.'-firebase.json';
        File::put($file, json_encode(['type' => 'service_account', 'project_id' => 'owlvix-test', 'private_key' => $pem,
            'client_email' => 'sa@owlvix-test.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['builder.apps.firebase' => $file]);
        $sent = [];
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g-token', 'expires_in' => 3600]),
            'firebase.googleapis.com/v1beta1/projects/owlvix-test/androidApps?*' => Http::response(['apps' => []]),
            'firebase.googleapis.com/v1beta1/projects/owlvix-test/androidApps' => Http::response(['name' => 'operations/op1', 'done' => false]),
            'firebase.googleapis.com/v1beta1/operations/op1' => Http::response(['name' => 'operations/op1', 'done' => true, 'response' => ['appId' => '1:99:android:abc']]),
            'firebase.googleapis.com/v1beta1/projects/owlvix-test/androidApps/1:99:android:abc/config' => Http::response(['configFileContents' => base64_encode(json_encode(['client' => [['client_info' => ['android_client_info' => ['package_name' => 'com.joesbakery.app']]]]]))]),
            'fcm.googleapis.com/*' => function ($req) use (&$sent) {
                $sent[] = $req->data();

                return Http::response(['name' => 'projects/owlvix-test/messages/1']);
            },
            'api.github.com/*' => Http::response('', 204),
        ]);

        return [&$sent, $file];
    }

    public function test_push_notifications(): void
    {
        $token = $this->signIn();
        $icon96 = imagecreatetruecolor(96, 96);
        ob_start();
        imagepng($icon96);
        $notif = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        $id = $this->api($token)->postJson('/builder/apps', ['app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(), 'notification_icon' => $notif])
            ->assertOk()->assertJsonPath('app.push.available', false)->json('app.id');

        // Switched off: nothing to send
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Hi', 'body' => 'Hello'])->assertStatus(503);

        [, $file] = $this->fakeFirebase();
        $r = $this->api($token)->getJson('/builder/apps')->json('apps.0');
        $this->assertTrue($r['push']['available']);
        $this->assertFalse($r['push']['ready']);
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Hi', 'body' => 'Hello'])->assertStatus(422); // not built yet

        // The build registers the app in Firebase and carries its config, icon and topic
        $this->api($token)->postJson("/builder/apps/{$id}/build")->assertOk();
        $build = BuilderAppBuild::first();
        $this->assertSame('app_'.$id, $build->job['pushTopic']);
        $this->assertStringContainsString('com.joesbakery.app', base64_decode($build->job['googleServices']));
        $this->assertSame(base64_decode(substr($notif, 22)), base64_decode($build->job['notificationIcon']));
        $this->assertTrue($build->info['push']);
        Storage::disk('local')->assertExists("apps/{$id}/google-services.json");
        Http::assertSent(fn ($req) => $req->url() === 'https://firebase.googleapis.com/v1beta1/projects/owlvix-test/androidApps' && $req['packageName'] === 'com.joesbakery.app');
        $build->update(['status' => 'done']);
        $this->assertTrue($this->api($token)->getJson('/builder/apps')->json('apps.0.push.ready'));

        // Sending goes to the app's topic, with the page to open
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => '', 'body' => 'x'])->assertStatus(422);
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Hi', 'body' => 'x', 'tab' => 9])->assertStatus(422);
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Weekend offer', 'body' => '20% off cakes', 'tab' => 1])
            ->assertOk()->assertJsonPath('pushes.0.title', 'Weekend offer');
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://fcm.googleapis.com/v1/projects/owlvix-test/messages:send')
            && ($m = json_decode($req->body(), true)['message'])['topic'] === 'app_'.$id && $m['notification']['title'] === 'Weekend offer' && $m['data']['tab'] === '1'
            && $req->hasHeader('Authorization', 'Bearer g-token'));
        // Up to 3 a day
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Two', 'body' => 'x'])->assertOk();
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Three', 'body' => 'x'])->assertOk();
        $this->api($token)->postJson("/builder/apps/{$id}/push", ['title' => 'Four', 'body' => 'x'])->assertStatus(429);
        $this->assertCount(3, $this->api($token)->getJson("/builder/apps/{$id}/pushes")->json('pushes'));
        // Someone else can't send to it
        $other = $this->signIn('other@example.com');
        $this->api($other)->postJson("/builder/apps/{$id}/push", ['title' => 'x', 'body' => 'x'])->assertStatus(404);

        // The privacy page and data-safety answers mention notifications
        $this->get(route('builder.apps.privacy', $id))->assertSee('Firebase Cloud Messaging');
        File::delete($file);
    }

    public function test_anonymous_daily_statistics(): void
    {
        $token = $this->signIn();
        $id = $this->save($token)->json('app.id');
        $yesterday = now()->subDay()->toDateString();
        $twoDays = now()->subDays(2)->toDateString();

        // A new phone sends its first two days; another phone sends yesterday
        $this->postJson("/api/app-stats/{$id}", ['new' => true, 'days' => [
            ['day' => $twoDays, 'opens' => 3, 'pages' => ['0' => 3, '1' => 1]],
            ['day' => $yesterday, 'opens' => 2, 'pages' => ['0' => 2, '2' => 4, '99' => 50]], // no page 99: ignored
        ]])->assertOk();
        $this->postJson("/api/app-stats/{$id}", ['new' => false, 'days' => [['day' => $yesterday, 'opens' => 5000, 'pages' => ['1' => 2]]]])->assertOk(); // capped
        // Odd days are ignored; unknown apps refused
        $this->postJson("/api/app-stats/{$id}", ['days' => [['day' => now()->addDays(3)->toDateString(), 'opens' => 1], ['day' => '2020-01-01', 'opens' => 1]]])->assertOk();
        $this->postJson('/api/app-stats/aaaaaaaaaaaaaaaa', ['days' => [['day' => $yesterday, 'opens' => 1]]])->assertNotFound();

        $r = $this->api($token)->getJson("/builder/apps/{$id}/stats")->assertOk()->json();
        $this->assertCount(30, $r['days']);
        $byDay = collect($r['days'])->keyBy('day');
        $this->assertSame(['day' => $yesterday, 'active' => 2, 'opens' => 202, 'installs' => 0], $byDay[$yesterday]);
        $this->assertSame(['day' => $twoDays, 'active' => 1, 'opens' => 3, 'installs' => 1], $byDay[$twoDays]);
        $this->assertSame(1, $r['installs']);
        $this->assertSame(round(3 / 7, 1), $r['daily_users']);
        $this->assertSame(205, $r['opens']);
        $this->assertSame([['page' => 'Home', 'views' => 5], ['page' => 'Odd', 'views' => 4], ['page' => 'Menu', 'views' => 3]], $r['pages']);
        // Only the owner sees them
        $this->api($this->signIn('other@example.com'))->getJson("/builder/apps/{$id}/stats")->assertNotFound();
        // The privacy page says so
        $this->get(route('builder.apps.privacy', $id))->assertSee('sends these counts once a day');
    }

    public function test_products_and_orders(): void
    {
        $token = $this->signIn();
        $app = $this->app();
        $app['tabs'][1]['blocks'] = [['type' => 'products', 'title' => 'Order', 'items' => [
            ['name' => 'Latte', 'price' => '4,5', 'category' => 'Coffee', 'text' => 'Oat or dairy', 'image' => ['src' => $this->jpeg(300, 300)]],
            ['name' => 'Cake of the day', 'price' => '', 'category' => 'Bakery'],
            ['name' => '', 'price' => '3'], // no name: dropped
        ]]];
        // Products need somewhere for orders to go
        $this->save($token, $app)->assertStatus(422)->assertJsonFragment(['error' => 'Your app has products: add a WhatsApp number or email for orders under “More settings”.']);
        $this->save($token, $app + ['shop' => ['whatsapp' => '12', 'email' => '']])->assertStatus(422);
        $this->save($token, $app + ['shop' => ['email' => 'nope']])->assertStatus(422);

        $r = $this->save($token, $app + ['shop' => ['currency' => '£', 'whatsapp' => '+44 7700 900123', 'note' => 'Pay on collection']])->assertOk()->json('app');
        $items = $r['tabs'][1]['blocks'][0]['items'];
        $this->assertCount(2, $items);
        $this->assertSame(['name' => 'Latte', 'text' => 'Oat or dairy', 'category' => 'Coffee', 'price' => 4.5], array_diff_key($items[0], ['image' => 1]));
        $this->assertArrayNotHasKey('price', $items[1]); // "Ask us"
        $this->assertSame(['currency' => '£', 'whatsapp' => '447700900123', 'email' => '', 'note' => 'Pay on collection'], $r['settings']['shop']);
        $this->assertSame($r['settings']['shop'], $this->contentFile($r['id'])['shop']);
        $this->assertTrue($r['data_use']['orders']);
        $this->get($r['privacy_url'])->assertSee('When you send an order');

        // Prices must be numbers
        $app['tabs'][1]['blocks'][0]['items'][0]['price'] = 'four';
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $app + ['shop' => ['whatsapp' => '+44 7700 900123']]])->assertStatus(422);
    }

    public function test_up_to_fifteen_pages_with_a_menu(): void
    {
        $token = $this->signIn();
        $app = $this->app();
        foreach (range(4, 12) as $n) {
            $app['tabs'][] = ['label' => "Page $n", 'icon' => 'info', 'type' => 'screen', 'blocks' => [['type' => 'text', 'text' => "Page $n"]]];
        }
        $app['tabs'][1]['bar'] = false;
        $tabs = $this->save($token, $app)->assertOk()->json('app.tabs');
        $this->assertCount(12, $tabs);
        // Without a choice, the first five are in the bar and the rest in the ☰ menu; here one was moved out of the bar
        $this->assertSame([true, false, true, true, true, false, false], array_slice(array_column($tabs, 'bar'), 0, 7));
        $this->assertSame([false, true, false, false, false, true, true], array_slice(array_column($tabs, 'menu'), 0, 7));
        $this->assertSame($tabs, $this->contentFile($this->api($token)->getJson('/builder/apps')->json('apps.0.id'))['tabs']);
        $id = $this->api($token)->getJson('/builder/apps')->json('apps.0.id');
        $this->assertSame('show', $this->contentFile($id)['menu']);
        // A page can be in both the bottom bar and the menu; the whole menu can be turned off
        $app = $this->app(['menu' => 'hide']);
        $app['tabs'][0]['menu'] = true;
        $saved = $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $app])->assertOk()->assertJsonPath('app.settings.menu', 'hide')->json('app.tabs.0');
        $this->assertTrue($saved['bar'] && $saved['menu']);
        $this->assertSame('hide', $this->contentFile($id)['menu']);
        $this->api($token)->postJson('/builder/apps', ['id' => $id, 'app' => $this->app(['menu' => 'sometimes'])])->assertOk();
        $this->assertSame('show', $this->contentFile($id)['menu']);
    }

    public function test_splash_screen_picture_and_size_go_to_the_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $token = $this->signIn();
        $r = $this->api($token)->postJson('/builder/apps', ['app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(),
            'splash' => $this->png(), 'splash_design' => ['mode' => 'image', 'size' => 'large'], 'splash_image' => $this->png(300)])->assertOk()->json('app');
        $this->assertSame(['mode' => 'image', 'size' => 'large', 'bg' => 'color'], $r['settings']['splash']);
        $this->assertSame(['mode' => 'image', 'size' => 'large', 'bg' => 'color'], $this->api($token)->getJson("/builder/apps/{$r['id']}/icon")->json('splash_design'));
        $this->assertNotNull($this->api($token)->getJson("/builder/apps/{$r['id']}/icon")->json('splash_image'));

        $this->api($token)->postJson("/builder/apps/{$r['id']}/build")->assertOk();
        $job = BuilderAppBuild::first()->job;
        $this->assertSame(260, $job['splashWidth']);
        $this->assertSame(base64_decode(substr($this->png(), 22)), base64_decode($job['splash']));

        // Saving without a new icon keeps the splash; a bad splash picture is refused
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $this->app()])->assertOk()->assertJsonPath('app.settings.splash.size', 'large');
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(), 'splash' => $this->png(500)])->assertStatus(422);
        // "Another picture" without the picture falls back to the icon; odd values fall back too
        $this->api($token)->postJson('/builder/apps', ['id' => $r['id'], 'app' => $this->app(), 'icon' => $this->png(), 'foreground' => $this->png(), 'splash_design' => ['mode' => 'image', 'size' => 'giant']])
            ->assertOk()->assertJsonPath('app.settings.splash', ['mode' => 'icon', 'size' => 'medium', 'bg' => 'color']);
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
        $this->assertSame(['label' => 'Menu', 'icon' => 'food', 'type' => 'web', 'bar' => true, 'menu' => false, 'url' => 'https://sites.owlvix.test/joes-bakery/menu'], $r['tabs'][1]);
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
