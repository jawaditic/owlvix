<?php

return [
    // Published builder sites are served on this subdomain, e.g. "sites.owlvix.com" → https://sites.owlvix.com/{name}.
    // Leave empty to serve them at /p/{name} on the main domain (local development).
    'domain' => env('BUILDER_DOMAIN'),

    // The subdomain's own folder (its cPanel document root), e.g. /home/owlvgnjd/sites.owlvix.com. When set, every
    // published page is written there as a static file ({name}/index.html, {name}/about/index.html) with an .htaccess
    // that adds the security headers, so the web server serves sites directly without touching the Laravel app.
    'public_path' => env('BUILDER_PUBLIC_PATH'),
];
