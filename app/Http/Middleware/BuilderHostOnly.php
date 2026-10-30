<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The builder subdomain (BUILDER_DOMAIN, e.g. sites.owlvix.com) only serves published sites. Any other page asked
 * for on it — /about, /admin/…, or a name nobody has published — is sent to the same address on the main site, so the subdomain never becomes a
 * copy of owlvix.com (duplicate content) or a second door into the admin.
 */
class BuilderHostOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = config('builder.domain');
        if ($domain && strcasecmp($request->getHost(), $domain) === 0) {
            $isSite = $request->routeIs('builder.site', 'builder.report', 'builder.report.send')
                && \App\Models\BuilderSite::where('slug', $request->route('slug'))->exists();
            // Anything that isn't a published site (e.g. /about or /admin/…) belongs to the main site
            if (! $isSite && ! $request->routeIs('builder.home')) {
                return redirect()->away(rtrim(config('app.url'), '/').$request->getRequestUri(), 301);
            }
        }

        return $next($request);
    }
}
