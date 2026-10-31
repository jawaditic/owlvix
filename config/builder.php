<?php

return [
    // Published builder sites are served on this subdomain, e.g. "sites.owlvix.com" → https://sites.owlvix.com/{name}.
    // Leave empty to serve them at /p/{name} on the main domain (local development).
    'domain' => env('BUILDER_DOMAIN'),

    // The subdomain's own folder (its cPanel document root), e.g. /home/owlvgnjd/sites.owlvix.com. When set, every
    // published page is written there as a static file ({name}/index.html, {name}/about/index.html) with an .htaccess
    // that adds the security headers, so the web server serves sites directly without touching the Laravel app.
    'public_path' => env('BUILDER_PUBLIC_PATH'),

    // Mobile app builder (/mobile-app-builder): Android builds run on GitHub Actions in this repository
    // (.github/workflows/build-android-app.yml). The token needs "Actions: read and write" on that repository only.
    'apps' => [
        'github_repo' => env('APP_BUILDER_REPO'),            // e.g. jawaditic/owlvix
        'github_token' => env('APP_BUILDER_GITHUB_TOKEN'),
        'workflow' => env('APP_BUILDER_WORKFLOW', 'build-android-app.yml'),
        'ref' => env('APP_BUILDER_REF', 'main'),
        // Where the build server sends the finished app (defaults to APP_URL; must be reachable from the internet)
        'callback_url' => env('APP_BUILDER_CALLBACK_URL'),
        // Builds per month for everyone together, so the free GitHub minutes last the month
        'monthly_limit' => (int) env('APP_BUILDER_MONTHLY_LIMIT', 100),
        // Each app's live content (content.json + pictures) and finished APK/AAB files are written here as static
        // files, so the web server sends them without starting PHP: phones fetch content.json on every app open.
        'public_dir' => env('APP_BUILDER_PUBLIC_DIR') ?: public_path('app-data'),
        'public_url' => env('APP_BUILDER_PUBLIC_URL'), // defaults to APP_URL/app-data
    ],
];
