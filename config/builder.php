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
        // Seals each build's one-time pass in the (public) Actions logs; the same value is the repo's
        // OWLVIX_RUNNER_KEY secret. Any long random string, e.g. `openssl rand -hex 32`.
        'runner_key' => env('APP_BUILDER_RUNNER_KEY'),
        'workflow' => env('APP_BUILDER_WORKFLOW', 'build-android-app.yml'),
        'ref' => env('APP_BUILDER_REF', 'main'),
        // Where the build server sends the finished app (defaults to APP_URL; must be reachable from the internet)
        'callback_url' => env('APP_BUILDER_CALLBACK_URL'),
        // Builds per month for everyone together, so the free GitHub minutes last the month
        'monthly_limit' => (int) env('APP_BUILDER_MONTHLY_LIMIT', 100),
        // Each app's live content (content.json + pictures) and finished APK/AAB files are written here as static
        // files, so the web server sends them without starting PHP: phones fetch content.json on every app open.
        // (empty: public_path('app-data'), worked out when used, because the live site sets its public folder in
        // public_html/index.php and a cached config made on the command line would hold the wrong one)
        'public_dir' => env('APP_BUILDER_PUBLIC_DIR') ?: null,
        'public_url' => env('APP_BUILDER_PUBLIC_URL'), // defaults to APP_URL/app-data
        // Push notifications: the owlvix-apps Firebase project's service account key (a JSON file, never in public_html).
        // Without it the builder hides notifications (App\Support\Firebase).
        // A Firebase project holds 30 apps: add more projects' keys beside it as firebase-2.json, firebase-3.json…
        'firebase' => env('APP_BUILDER_FIREBASE') ?: storage_path('app/firebase.json'),
        'pushes_per_day' => (int) env('APP_BUILDER_PUSHES_PER_DAY', 3),
    ],

    // Online card payments in apps through Stripe Connect (App\Support\Stripe): owners connect their own Stripe
    // account, owlvix keeps only its id. Two sets of owlvix's platform keys: test (sandbox, test cards) and live.
    // APP_BUILDER_PAYMENTS=false switches online payments off for every app (cash, card on pickup… still work).
    // Stripe dashboard → Connect: add the redirect URI https://owlvix.com/builder/stripe/callback.
    'payments' => [
        'enabled' => (bool) env('APP_BUILDER_PAYMENTS', true),
        'stripe' => [
            'test' => ['secret' => env('STRIPE_TEST_SECRET'), 'client_id' => env('STRIPE_TEST_CLIENT_ID')],
            'live' => ['secret' => env('STRIPE_LIVE_SECRET'), 'client_id' => env('STRIPE_LIVE_CLIENT_ID')],
            'fee_percent' => (float) env('APP_BUILDER_STRIPE_FEE', 0), // owlvix's share of each payment, 0 = none
        ],
    ],
];
