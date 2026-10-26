<?php

namespace App\Support;

/**
 * Links to the site's CSS and JS. Uses the minified copy made by `npm run minify` (e.g. css/site.min.css)
 * when there is one, and adds ?v=<last change> so browsers fetch a new version after every update.
 */
class Asset
{
    public static function url(string $path): string
    {
        $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
        $file = is_file(public_path($min)) ? $min : $path;

        return asset($file).'?v='.@filemtime(public_path($file));
    }
}
