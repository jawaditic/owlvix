<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Testimonial;
use App\Support\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** IndexNow: telling Bing when the homepage changes (app/Support/IndexNow.php). */
class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
        config(['app.url' => 'https://owlvix.com', 'services.indexnow.key' => self::KEY]);
    }

    public function test_key_file_answers_only_for_the_right_key(): void
    {
        $this->get('/'.self::KEY.'.txt')->assertOk()->assertSeeText(self::KEY)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->get('/ffffffffffffffffffffffffffffffff.txt')->assertNotFound();

        config(['services.indexnow.key' => null]);
        $this->get('/'.self::KEY.'.txt')->assertNotFound();
    }

    public function test_changing_the_portfolio_pings_once_per_ten_minutes(): void
    {
        Project::create(['title' => 'New', 'category' => 'website', 'image' => 'x.jpg', 'is_published' => true]);
        Testimonial::create(['name' => 'Ann', 'text' => 'Great work', 'rating' => 5]);
        $this->app->terminate(); // pings are sent after the response

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.indexnow.org/indexnow'
            && $r['host'] === 'owlvix.com'
            && $r['key'] === self::KEY
            && $r['keyLocation'] === 'https://owlvix.com/'.self::KEY.'.txt'
            && $r['urlList'] === ['https://owlvix.com']);
    }

    public function test_nothing_is_sent_without_a_key(): void
    {
        config(['services.indexnow.key' => null]);
        Project::create(['title' => 'New', 'category' => 'website', 'image' => 'x.jpg']);
        $this->app->terminate();

        Http::assertNothingSent();
        $this->assertNull(IndexNow::submit(['https://owlvix.com']));
    }

    public function test_ping_command(): void
    {
        $this->artisan('indexnow:ping')->expectsOutputToContain('IndexNow answered 202')->assertSuccessful();
    }
}
