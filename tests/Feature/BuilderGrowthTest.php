<?php

namespace Tests\Feature;

use App\Support\Stats\BuilderStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin → Growth: builder steps counted by where the visitor came from. */
class BuilderGrowthTest extends TestCase
{
    use RefreshDatabase;

    private const UA = ['User-Agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/140 Safari/537.36'];

    public function test_sources_are_named_from_where_the_visitor_came(): void
    {
        $this->assertSame('guide:guide.cv', BuilderStats::source('/how-to-write-a-cv'));
        $this->assertSame('design:bakery', BuilderStats::source('design:bakery'));
        $this->assertSame('examples', BuilderStats::source('/examples'));
        $this->assertSame('search', BuilderStats::source('ext:www.google.com'));
        $this->assertSame('other', BuilderStats::source('ext:example.org'));
        $this->assertSame('direct', BuilderStats::source(''));
        $this->assertSame('site', BuilderStats::source('/about'));
        $this->assertSame('other', BuilderStats::source('ext:notgoogle.com'));
    }

    public function test_steps_are_counted_and_bad_ones_refused(): void
    {
        $this->withHeaders(self::UA)->post('/t/b', ['builder' => 'resume-builder', 'event' => 'open', 'from' => '/how-to-write-a-cv'])->assertNoContent();
        $this->withHeaders(self::UA)->post('/t/b', ['builder' => 'resume-builder', 'event' => 'open', 'from' => '/how-to-write-a-cv'])->assertNoContent();
        $this->withHeaders(self::UA)->post('/t/b', ['builder' => 'resume-builder', 'event' => 'export', 'from' => '/how-to-write-a-cv'])->assertNoContent();
        $this->assertSame(2, (int) DB::table('builder_steps')->where(['builder' => 'resume-builder', 'event' => 'open', 'source' => 'guide:guide.cv'])->value('hits'));

        $this->withHeaders(self::UA)->postJson('/t/b', ['builder' => 'nope', 'event' => 'open'])->assertStatus(422);
        $this->withHeaders(self::UA)->postJson('/t/b', ['builder' => 'store-builder', 'event' => 'hack'])->assertStatus(422);
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->post('/t/b', ['builder' => 'store-builder', 'event' => 'open'])->assertNoContent();
        $this->assertSame(0, DB::table('builder_steps')->where('builder', 'store-builder')->count()); // bots aren't counted
    }

    public function test_builder_pages_carry_their_name_for_the_counter(): void
    {
        $this->get(route('store-builder'))->assertSee('<meta name="owl-builder" content="store-builder">', false);
        $this->get(route('resume-builder'))->assertSee('<meta name="owl-builder" content="resume-builder">', false);
        $this->get(route('guides'))->assertDontSee('owl-builder', false);
    }

    public function test_the_growth_page(): void
    {
        BuilderStats::record('store-builder', 'open', 'design:bakery');
        BuilderStats::record('store-builder', 'publish', 'design:bakery');
        BuilderStats::record('resume-builder', 'open', 'guide:guide.cv');

        $this->get('/admin/growth')->assertRedirect(); // admins only
        $this->withSession(['is_admin' => true])->get('/admin/growth')->assertOk()
            ->assertSee('Where builders come from')
            ->assertSee('Gallery design: Bakery')
            ->assertSee('Guide: How to write a CV that gets interviews')
            ->assertSee('Online store builder');
    }
}
