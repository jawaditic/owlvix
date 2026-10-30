<?php

namespace Tests\Feature;

use App\Mail\BuilderSignInCode;
use App\Models\BuilderSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Published sites written as static files into the subdomain's own folder (BUILDER_PUBLIC_PATH). */
class BuilderStaticFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('builder');
        $this->root = sys_get_temp_dir().'/owlvix-sites-'.uniqid();
        File::ensureDirectoryExists($this->root.'/cgi-bin'); // what cPanel puts in a new subdomain folder
        config(['builder.public_path' => $this->root, 'builder.domain' => 'sites.owlvix.test']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function token(): string
    {
        Mail::fake();
        $this->postJson('/builder/account/code', ['email' => 'maker@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(BuilderSignInCode::class, function ($m) use (&$code) { $code = $m->code; return true; });

        return $this->postJson('/builder/account/verify', ['email' => 'maker@example.com', 'code' => $code])->json('token');
    }

    private function publish(string $token, array $slugs)
    {
        $pages = array_map(fn ($s) => ['slug' => $s, 'title' => ucfirst($s), 'html' => "<!DOCTYPE html><html><head><title>{$s}</title></head><body><h1>Page {$s}</h1></body></html>"], $slugs);

        return $this->withHeaders(['X-Builder-Token' => $token])->postJson('/builder/publish', ['slug' => 'joes-bakery', 'name' => "Joe's Bakery", 'pages' => $pages]);
    }

    public function test_publishing_writes_static_files_with_security_headers(): void
    {
        $token = $this->token();
        $this->publish($token, ['index', 'about'])->assertOk()->assertJson(['url' => 'http://sites.owlvix.test/joes-bakery']);

        $home = File::get($this->root.'/joes-bakery/index.html');
        $this->assertStringContainsString('Page index', $home);
        $this->assertStringContainsString('Made with <b', $home); // the bar is baked in
        $this->assertStringContainsString('/p/joes-bakery/report', $home); // report form stays on the main site
        $this->assertStringContainsString('Page about', File::get($this->root.'/joes-bakery/about/index.html'));

        $ht = File::get($this->root.'/.htaccess');
        $this->assertStringContainsString('Content-Security-Policy "sandbox allow-scripts', $ht);
        $this->assertStringContainsString('X-Robots-Tag "noindex"', $ht);
        $this->assertStringContainsString('Options -Indexes', $ht);

        // Republishing without "about" removes that page's file
        $this->publish($token, ['index'])->assertOk();
        $this->assertFileDoesNotExist($this->root.'/joes-bakery/about/index.html');

        // Unpublishing removes the folder, and nothing else in the subdomain folder is touched
        $this->withHeaders(['X-Builder-Token' => $token])->postJson('/builder/unpublish', ['slug' => 'joes-bakery'])->assertOk();
        $this->assertDirectoryDoesNotExist($this->root.'/joes-bakery');
        $this->assertDirectoryExists($this->root.'/cgi-bin');
    }

    public function test_takedown_and_restore_and_safety(): void
    {
        $token = $this->token();
        $this->publish($token, ['index'])->assertOk();
        $site = BuilderSite::first();

        $site->update(['status' => 'removed', 'removed_by' => 'admin']);
        $site->syncPublicFiles();
        $this->assertDirectoryDoesNotExist($this->root.'/joes-bakery');

        $site->update(['status' => 'live', 'removed_by' => null]);
        $site->syncPublicFiles();
        $this->assertFileExists($this->root.'/joes-bakery/index.html');

        // Folders without our marker are never deleted, and cPanel's own folder names can't be published
        $other = new BuilderSite(['slug' => 'cgi-bin']);
        $other->removePublicFiles();
        $this->assertDirectoryExists($this->root.'/cgi-bin');
        $this->withHeaders(['X-Builder-Token' => $token])->postJson('/builder/publish', ['slug' => 'cgi-bin', 'name' => 'x', 'pages' => [['slug' => 'index', 'title' => 'x', 'html' => '<p>x</p>']]])->assertStatus(422);
    }
}
