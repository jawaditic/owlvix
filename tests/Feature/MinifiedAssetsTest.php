<?php

namespace Tests\Feature;

use App\Support\Asset;
use Tests\TestCase;

/** Pages load minified CSS/JS (made by `npm run minify`). Each copy must match its readable file. */
class MinifiedAssetsTest extends TestCase
{
    private const FILES = ['css/theme.css', 'css/site.css', 'css/admin.css', 'js/theme.js', 'js/owl.js', 'js/site.js', 'js/admin-dashboard.js'];

    public function test_every_minified_copy_is_up_to_date(): void
    {
        foreach (self::FILES as $file) {
            $min = public_path(preg_replace('/\.(css|js)$/', '.min.$1', $file));
            $this->assertFileExists($min, "Missing {$min}: run `npm run minify`.");

            $hash = substr(sha1_file(public_path($file)), 0, 12);
            $firstLine = strtok(file_get_contents($min), "\n");
            $this->assertSame("/*! from {$file} {$hash} */", $firstLine, "{$min} is out of date: run `npm run minify`.");
        }
    }

    public function test_pages_link_the_minified_files(): void
    {
        $this->assertStringContainsString('/css/site.min.css?v=', Asset::url('css/site.css'));
        $this->get('/')->assertOk()
            ->assertSee('css/site.min.css', false)
            ->assertSee('js/site.min.js', false)
            ->assertDontSee('css/site.css?v', false);
    }

    /** An unclosed { in a stylesheet silently swallows every rule after it (it happened once). */
    public function test_stylesheets_have_balanced_braces(): void
    {
        foreach (['css/site.css', 'css/theme.css', 'css/admin.css'] as $file) {
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(public_path($file)));
            $depth = 0;
            foreach (str_split($css) as $ch) {
                $depth += $ch === '{' ? 1 : ($ch === '}' ? -1 : 0);
                $this->assertGreaterThanOrEqual(0, $depth, "$file has an extra }");
            }
            $this->assertSame(0, $depth, "$file has an unclosed {");
        }
    }
}
