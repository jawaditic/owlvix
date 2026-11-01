<?php

namespace Tests\Feature;

use App\Support\FreeTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Each builder's own editor page (/…/editor): just the builder; the quick setup on the main page sends people there. */
class BuilderEditorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_builder_has_an_editor_page(): void
    {
        foreach (FreeTools::builderRoutes() as $name) {
            $main = $this->get(route($name))->assertOk();
            $main->assertSee('data-editor-url="'.route($name.'.editor').'"', false) // the quick setup's way to it
                ->assertSee('class="hero', false);

            $this->get(route($name.'.editor'))->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertSee('<link rel="canonical" href="'.route($name).'">', false)   // the main page is the one to find
                ->assertSee('<meta name="owl-builder" content="'.$name.'">', false)   // still counted in Admin → Growth
                ->assertSee('class="ebar"', false)
                ->assertDontSee('data-editor-url', false)
                ->assertDontSee('class="hero', false)
                ->assertDontSee('class="faq"', false)
                ->assertDontSee('class="nav"', false);
        }
    }

    public function test_the_quote_boxes_stay_on_the_editor_page_but_hidden(): void
    {
        // buttons like "Put it on my domain" open the quote form through them
        $this->get(route('store-builder.editor'))->assertSee('<div class="editor-hidden"  hidden >', false)->assertSee('data-lpb-quote', false);
        $this->get(route('store-builder'))->assertDontSee('editor-hidden"  hidden', false);
    }
}
