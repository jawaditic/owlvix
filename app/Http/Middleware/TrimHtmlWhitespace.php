<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes the indentation at the start of each line of HTML pages before they're sent. The Blade files stay
 * readable; the homepage gets about a third smaller (and passes SEO "text to HTML ratio" checks).
 * Line breaks are kept, so text renders exactly the same. <pre> and <textarea> are left untouched.
 */
class TrimHtmlWhitespace
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type');
        $html = $response->getContent();
        if (! is_string($html) || $html === '' || ! str_contains($type, 'text/html')) {
            return $response;
        }

        // Split out <pre>/<textarea> blocks (kept as they are) and trim everything between them
        $parts = preg_split('#(<(pre|textarea)\b.*?</\2>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $response;
        }
        $out = '';
        for ($i = 0, $n = count($parts); $i < $n; $i++) {
            if ($i % 3 === 0) {
                $out .= preg_replace(['/\n[ \t]+/', '/[ \t]+\n/', '/\n{2,}/'], "\n", $parts[$i]);
            } elseif ($i % 3 === 1) {
                $out .= $parts[$i]; // the protected block
            }
            // $i % 3 === 2 is the captured tag name; skip it
        }

        return $response->setContent($out);
    }
}
