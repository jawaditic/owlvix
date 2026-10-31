<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// Builder form posts and published pages run without a session or cookies: they are opened from other people's pages,
// often in a browser that is also signed in to owlvix.com (the owner testing their form, or the admin). Starting a
// session there would replace theirs and sign them out.
$stateless = [\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];

// Builder subdomain (sites.owlvix.com): only published sites live here; everything else redirects to the main site
// (see App\Http\Middleware\BuilderHostOnly).
if (($builderDomain = config('builder.domain')) && ! config('builder.public_path')) {
    Route::domain($builderDomain)->group(function () use ($stateless) {
        Route::get('/', fn () => redirect()->away(route('landing-builder')))->name('builder.home');
        Route::get('/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'reportForm'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report');
        Route::post('/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'report'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report.send');
        Route::get('/{slug}/{page?}', [\App\Http\Controllers\BuilderPublicController::class, 'show'])
            ->where(['slug' => '[a-z0-9-]{3,40}', 'page' => '[a-z0-9-]{1,40}'])
            ->withoutMiddleware([\App\Http\Middleware\TrimHtmlWhitespace::class, ...$stateless])
            ->name('builder.site');
    });
}

Route::view('/', 'home')->name('home');

// Landing page for ads and search: every service on one page, with the quote form at the top
Route::view('/services', 'landing')->name('landing');
Route::view('/about', 'about')->name('about');

// Free website speed test (Google PageSpeed Insights): the page, and the endpoint its form calls
Route::get('/website-speed-test', [\App\Http\Controllers\SpeedTestController::class, 'show'])->name('speed-test');
Route::post('/website-speed-test', [\App\Http\Controllers\SpeedTestController::class, 'run'])
    ->middleware('throttle:6,1,speed-test')
    ->name('speed-test.run');
// Free SEO checker: the page, the check, and "email me these tips" (saves a lead)
Route::view('/seo-audit', 'seo-audit')->name('seo-audit');
Route::view('/free-tools', 'free-tools')->name('free-tools');
Route::view('/project-cost-calculator', 'tools.cost-calculator')->name('cost-calculator');
Route::view('/tech-stack-detector', 'tools.tech-stack')->name('tech-stack');
Route::view('/security-checker', 'tools.security')->name('security-checker');
Route::view('/meta-tag-preview', 'tools.meta-preview')->name('meta-preview');
Route::view('/broken-link-checker', 'tools.broken-links')->name('broken-links');
Route::view('/email-dns-checker', 'tools.email-check')->name('email-check');
Route::view('/ads-roi-calculator', 'tools.ads-roi')->name('ads-roi');
Route::view('/schema-markup-generator', 'tools.schema-generator')->name('schema-generator');
Route::view('/redirect-checker', 'tools.redirects')->name('redirect-check');
Route::view('/robots-txt-checker', 'tools.robots')->name('robots-check');
Route::view('/domain-age-checker', 'tools.domain')->name('domain-check');
Route::view('/image-compressor', 'tools.image-compressor')->name('image-compressor');
Route::view('/email-signature-generator', 'tools.email-signature')->name('email-signature');
Route::view('/form-builder', 'tools.form-builder')->name('form-builder');
Route::view('/email-template-builder', 'tools.email-builder')->name('email-builder');
Route::view('/resume-builder', 'tools.resume-builder')->name('resume-builder');
Route::view('/landing-page-builder', 'tools.landing-builder')->name('landing-builder');
Route::view('/funnel-builder', 'tools.funnel-builder')->name('funnel-builder');
// More free builders: the same editor with its own starting templates (App\Support\BuilderKits)
Route::view('/website-builder', 'tools.kit-builder', ['kit' => 'website'])->name('website-builder');
Route::view('/portfolio-builder', 'tools.kit-builder', ['kit' => 'portfolio'])->name('portfolio-builder');
Route::view('/link-in-bio-builder', 'tools.kit-builder', ['kit' => 'links'])->name('link-in-bio-builder');
Route::view('/coming-soon-page-builder', 'tools.kit-builder', ['kit' => 'soon'])->name('coming-soon-builder');
Route::view('/event-website-builder', 'tools.kit-builder', ['kit' => 'event'])->name('event-website-builder');
Route::view('/restaurant-menu-builder', 'tools.kit-builder', ['kit' => 'menu'])->name('menu-builder');
Route::view('/mobile-app-builder', 'tools.app-builder')->name('app-builder');
Route::post('/tech-stack-detector', [\App\Http\Controllers\FreeToolsController::class, 'tech'])->middleware('throttle:8,1,tool-tech')->name('tech-stack.run');
Route::post('/security-checker', [\App\Http\Controllers\FreeToolsController::class, 'security'])->middleware('throttle:8,1,tool-security')->name('security-checker.run');
Route::post('/meta-tag-preview', [\App\Http\Controllers\FreeToolsController::class, 'meta'])->middleware('throttle:8,1,tool-meta')->name('meta-preview.run');
Route::post('/broken-link-checker', [\App\Http\Controllers\FreeToolsController::class, 'links'])->middleware('throttle:4,1,tool-links')->name('broken-links.run');
Route::post('/email-dns-checker', [\App\Http\Controllers\FreeToolsController::class, 'email'])->middleware('throttle:8,1,tool-email')->name('email-check.run');
Route::post('/redirect-checker', [\App\Http\Controllers\FreeToolsController::class, 'redirects'])->middleware('throttle:8,1,tool-redirects')->name('redirect-check.run');
Route::post('/robots-txt-checker', [\App\Http\Controllers\FreeToolsController::class, 'robots'])->middleware('throttle:8,1,tool-robots')->name('robots-check.run');
Route::post('/domain-age-checker', [\App\Http\Controllers\FreeToolsController::class, 'domain'])->middleware('throttle:8,1,tool-domain')->name('domain-check.run');
Route::get('/seo-checker', [\App\Http\Controllers\SeoCheckerController::class, 'show'])->name('seo-checker');
Route::post('/seo-checker', [\App\Http\Controllers\SeoCheckerController::class, 'run'])->middleware('throttle:8,1,seo-checker')->name('seo-checker.run');
Route::post('/seo-checker/audit', [\App\Http\Controllers\SeoCheckerController::class, 'audit'])->middleware('throttle:3,1,seo-audit')->name('seo-checker.audit');
Route::post('/seo-checker/email', [\App\Http\Controllers\SeoCheckerController::class, 'email'])->middleware('throttle:3,1,seo-checker-email')->name('seo-checker.email');
Route::get('/website-speed-test/report.pdf', [\App\Http\Controllers\SpeedTestController::class, 'pdf'])
    ->middleware('throttle:10,1,speed-test-pdf')
    ->name('speed-test.pdf');
Route::view('/privacy', 'privacy')->name('privacy');

// One page per service (content in config/service_pages.php)
foreach (array_keys(config('service_pages')) as $slug) {
    Route::view('/'.$slug, 'service', ['slug' => $slug])->name('service.'.$slug);
}

// Sitemap for search engines: the homepage, with the portfolio images
Route::get('/sitemap.xml', function () {
    $projects = \Illuminate\Support\Facades\Schema::hasTable('projects')
        ? \App\Models\Project::published()->ordered()->get() : collect();
    $lastmod = collect([
        filemtime(resource_path('views/home.blade.php')),
        $projects->max('updated_at')?->timestamp,
    ])->filter()->max();

    return response()->view('sitemap', ['projects' => $projects, 'lastmod' => date('Y-m-d', $lastmod)])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

// IndexNow key file: search engines fetch it to confirm pings come from this site (app/Support/IndexNow.php)
Route::get('/{key}.txt', function (string $key) {
    abort_unless(\App\Support\IndexNow::key() !== null && hash_equals(\App\Support\IndexNow::key(), $key), 404);

    return response($key)->header('Content-Type', 'text/plain; charset=UTF-8');
})->where('key', '[a-zA-Z0-9-]{8,128}');

// Thank-you page the owl talks on, linked from the confirmation email (signed link per enquiry)
Route::get('/thanks/{message}', function (\App\Models\ContactMessage $message) {
    return view('thanks', ['enquiry' => $message]);
})->middleware('signed')->name('thanks');

// Visitor stats beacon from site.js (no cookies, no IP stored)
Route::post('/t', [\App\Http\Controllers\TrackController::class, 'store'])
    ->middleware('throttle:60,1,track')
    ->name('track');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1,contact')
    ->name('contact.store');

// "Before you go" survey shown when a visitor is about to leave (see site.js)
Route::post('/survey', [\App\Http\Controllers\SurveyController::class, 'store'])
    ->middleware('throttle:5,1,survey')
    ->name('survey.store');

// Free landing page builder: email sign-in, contact forms and publishing (JSON, called by public/js/landing-builder.js)
Route::prefix('builder')->name('builder.')->controller(\App\Http\Controllers\BuilderController::class)->group(function () {
    Route::post('account/code', 'sendCode')->name('code');
    Route::post('account/verify', 'verify')->name('verify');
    Route::middleware('builder')->group(function () {
        Route::get('account', 'account')->name('account');
        Route::post('forms', 'createForm')->name('forms');
        Route::post('publish', 'publish')->name('publish');
        Route::post('unpublish', 'unpublish')->name('unpublish');
        // Funnels: leads from lead forms, step stats and email-tool integrations
        Route::get('leads', 'leads')->name('leads');
        Route::get('stats', 'stats')->name('stats');
        Route::get('forms/{id}/integrations', 'integrations')->where('id', '[a-z0-9]{16}')->name('integrations');
        Route::post('forms/{id}/integrations', 'saveIntegrations')->where('id', '[a-z0-9]{16}')->name('integrations.save');
        // Form builder: your forms and their answers
        Route::get('surveys', 'surveys')->name('surveys');
        Route::post('surveys', 'saveSurvey')->name('surveys.save');
        Route::get('surveys/{id}/responses', 'responses')->where('id', '[a-z0-9]{16}')->name('surveys.responses');
        Route::post('surveys/{id}/delete', 'deleteSurvey')->where('id', '[a-z0-9]{16}')->name('surveys.delete');
    });
});
// Mobile app builder: your apps, website check and Android builds (public/js/app-builder.js)
Route::prefix('builder/apps')->name('builder.apps.')->middleware('builder')->controller(\App\Http\Controllers\AppBuilderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'save')->middleware('throttle:30,1,app-save')->name('save');
    Route::post('{id}/verify', 'verify')->where('id', '[a-z0-9]{16}')->name('verify');
    Route::post('{id}/build', 'build')->where('id', '[a-z0-9]{16}')->middleware('throttle:6,1,app-build')->name('build');
    Route::get('{id}/icon', 'icon')->where('id', '[a-z0-9]{16}')->name('icon');
    Route::post('{id}/push', 'push')->where('id', '[a-z0-9]{16}')->middleware('throttle:10,1,app-push')->name('push');
    Route::get('{id}/pushes', 'pushes')->where('id', '[a-z0-9]{16}')->name('pushes');
    Route::get('{id}/stats', 'stats')->where('id', '[a-z0-9]{16}')->name('stats');
    Route::post('{id}/splash-video', 'splashVideo')->where('id', '[a-z0-9]{16}')->middleware('throttle:60,1,app-video')->name('splash-video');
    Route::get('{id}/keystore', 'keystore')->where('id', '[a-z0-9]{16}')->name('keystore');
    Route::post('{id}/delete', 'delete')->where('id', '[a-z0-9]{16}')->name('delete');
    Route::post('{id}/stripe/connect', 'stripeConnect')->where('id', '[a-z0-9]{16}')->middleware('throttle:10,1,app-stripe')->name('stripe.connect');
    Route::post('{id}/stripe/disconnect', 'stripeDisconnect')->where('id', '[a-z0-9]{16}')->name('stripe.disconnect');
});
// Stripe sends the owner back here after they connect their account (see App\Support\Stripe)
Route::get('/builder/stripe/callback', [\App\Http\Controllers\AppBuilderController::class, 'stripeCallback'])->middleware('throttle:20,1,app-stripe-back')->name('builder.stripe.callback');
// Where a customer lands after paying in the browser (then goes back to the app)
Route::get('/app-paid/{id}', [\App\Http\Controllers\AppBuilderController::class, 'paid'])->where('id', '[a-z0-9]{16}')->name('builder.apps.paid');
// Privacy policy of an app made with the mobile app builder (for its Google Play listing)
Route::get('/app-privacy/{id}', [\App\Http\Controllers\AppBuilderController::class, 'privacy'])->where('id', '[a-z0-9]{16}')->name('builder.apps.privacy');
// Reporting an app made with the mobile app builder (linked from the app's ☰ menu)
Route::get('/app-report/{id}', [\App\Http\Controllers\AppBuilderController::class, 'reportForm'])->where('id', '[a-z0-9]{16}')->name('builder.apps.report');
Route::post('/app-report/{id}', [\App\Http\Controllers\AppBuilderController::class, 'report'])->where('id', '[a-z0-9]{16}')->middleware('throttle:10,1,app-report')->name('builder.apps.report.send');
// Finished app files (signed links from the builder page and the "your app is ready" email)
Route::get('/app-builds/{build}/{kind}', [\App\Http\Controllers\AppBuilderController::class, 'download'])
    ->whereNumber('build')->whereIn('kind', ['apk', 'aab'])
    ->middleware(['signed', 'throttle:30,1,app-download'])->withoutMiddleware($stateless)->name('builder.apps.download');
// Funnel step stats beacon from published pages (sendBeacon from a sandboxed page): no session, cookies or CSRF
Route::post('/fs', [\App\Http\Controllers\BuilderPublicController::class, 'beacon'])->withoutMiddleware('web')->name('builder.beacon');
// Contact forms on builder pages (posted from any host, so no CSRF token: see bootstrap/app.php)
Route::post('/f/{id}', [\App\Http\Controllers\BuilderPublicController::class, 'submit'])->where('id', '[a-z0-9]{16}')->withoutMiddleware($stateless)->name('builder.form.submit');
// Forms made with the form builder: the page people fill in (also embedded in other sites in an iframe)
Route::get('/form/{id}', [\App\Http\Controllers\BuilderPublicController::class, 'survey'])->where('id', '[a-z0-9]{16}')->withoutMiddleware($stateless)->name('builder.survey');
Route::get('/f/{id}/sent', [\App\Http\Controllers\BuilderPublicController::class, 'sent'])->where('id', '[a-z0-9]{16}')->withoutMiddleware($stateless)->name('builder.form.sent');
// Published builder sites (served with a sandboxing CSP; the page HTML is left exactly as published).
// With BUILDER_DOMAIN set they live on their own subdomain (https://sites.owlvix.com/{name}) — those routes are
// registered at the top of this file — and old /p/{name} links redirect there.
if (config('builder.domain') && config('builder.public_path')) {
    // Static files on the subdomain: the report form stays here on the main site
    Route::get('/p/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'reportForm'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report');
    Route::post('/p/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'report'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report.send');
}
if (config('builder.domain')) {
    Route::get('/p/{slug}/{page?}', fn (string $slug, ?string $page = null) => redirect()->away(\App\Models\BuilderSite::base().$slug.($page ? '/'.$page : ''), 301))
        ->where(['slug' => '[a-z0-9-]{3,40}', 'page' => '[a-z0-9-]{1,40}']);
} else {
    Route::get('/p/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'reportForm'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report');
    Route::post('/p/{slug}/report', [\App\Http\Controllers\BuilderPublicController::class, 'report'])->where('slug', '[a-z0-9-]{3,40}')->name('builder.report.send');
    Route::get('/p/{slug}/{page?}', [\App\Http\Controllers\BuilderPublicController::class, 'show'])
        ->where(['slug' => '[a-z0-9-]{3,40}', 'page' => '[a-z0-9-]{1,40}'])
        ->withoutMiddleware([\App\Http\Middleware\TrimHtmlWhitespace::class, ...$stateless])
        ->name('builder.site');
}

// Admin dashboard
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1,admin-login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/export', [MessageController::class, 'export'])->name('messages.export');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
        Route::patch('messages/{message}/read', [MessageController::class, 'toggleRead'])->name('messages.read');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('traffic', [\App\Http\Controllers\Admin\TrafficController::class, 'index'])->name('traffic');

        // Exit survey answers
        Route::get('feedback', [\App\Http\Controllers\Admin\FeedbackController::class, 'index'])->name('feedback.index');
        Route::delete('feedback/{response}', [\App\Http\Controllers\Admin\FeedbackController::class, 'destroy'])->name('feedback.destroy');

        // Free landing page builder: published sites and forms
        Route::get('builder', [\App\Http\Controllers\Admin\BuilderController::class, 'index'])->name('builder.index');
        Route::post('builder/sites/{site}/takedown', [\App\Http\Controllers\Admin\BuilderController::class, 'takedown'])->name('builder.takedown');
        Route::post('builder/sites/{site}/restore', [\App\Http\Controllers\Admin\BuilderController::class, 'restore'])->name('builder.restore');
        Route::post('builder/sites/{site}/dismiss', [\App\Http\Controllers\Admin\BuilderController::class, 'dismiss'])->name('builder.dismiss');
        Route::post('builder/apps/{app}/takedown', [\App\Http\Controllers\Admin\BuilderController::class, 'takedownApp'])->name('builder.apps.takedown');
        Route::post('builder/apps/{app}/restore', [\App\Http\Controllers\Admin\BuilderController::class, 'restoreApp'])->name('builder.apps.restore');
        Route::post('builder/apps/{app}/dismiss', [\App\Http\Controllers\Admin\BuilderController::class, 'dismissApp'])->name('builder.apps.dismiss');
        // App payments: owlvix's Stripe platform keys (test and live), kept encrypted
        Route::get('payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'index'])->name('payments.index');
        Route::post('payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'save'])->name('payments.save');
        Route::post('builder/forms/{form}/toggle', [\App\Http\Controllers\Admin\BuilderController::class, 'toggleForm'])->name('builder.forms.toggle');

        // Portfolio ("Our work")
        Route::post('portfolio/{project}/move/{direction}', [ProjectController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('projects.move');
        Route::post('portfolio/{project}/toggle', [ProjectController::class, 'toggle'])->name('projects.toggle');
        Route::resource('portfolio', ProjectController::class)->except('show')
            ->parameters(['portfolio' => 'project'])->names('projects');

        // Reviews shown on the site
        Route::post('reviews/settings', [TestimonialController::class, 'settings'])->name('testimonials.settings');
        Route::post('reviews/{testimonial}/move/{direction}', [TestimonialController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('testimonials.move');
        Route::post('reviews/{testimonial}/toggle/{field}', [TestimonialController::class, 'toggle'])->name('testimonials.toggle');
        Route::resource('reviews', TestimonialController::class)->except('show')
            ->parameters(['reviews' => 'testimonial'])->names('testimonials');
    });
});
