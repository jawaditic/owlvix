<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Create the password hash for the /admin login. Put the output in .env as ADMIN_PASSWORD_HASH.
Artisan::command('admin:password {password}', function (string $password) {
    $this->line(\Illuminate\Support\Facades\Hash::make($password));
})->purpose('Make a password hash for the admin dashboard');

// Tell Bing (IndexNow) the homepage and services page changed, e.g. after a deploy. Needs INDEXNOW_KEY in .env.
Artisan::command('indexnow:ping {url?*}', function (array $url) {
    $urls = $url ?: \App\Support\IndexNow::pages();
    $status = \App\Support\IndexNow::submit($urls);
    if ($status === null) {
        $this->error('Not sent: set INDEXNOW_KEY in .env (or the request failed, see the log).');
    } else {
        $this->info("IndexNow answered {$status} for: ".implode(', ', $urls).' (200 or 202 means accepted).');
    }
})->purpose('Tell Bing and other IndexNow search engines that pages changed');

// Daily clean-up (needs the scheduler: cron "* * * * * php artisan schedule:run"): funnel step stats older than
// 400 days, and leads of deleted or unpublished funnels older than 90 days (see the models' prunable()).
\Illuminate\Support\Facades\Schedule::command('model:prune', [
    '--model' => [\App\Models\BuilderStat::class, \App\Models\BuilderLead::class],
])->daily();

// Mobile app builder: parts of uploads that never finished (an intro video left half-way, a build that stopped)
\Illuminate\Support\Facades\Schedule::call(function () {
    foreach (glob(storage_path('app/app-uploads/*')) ?: [] as $path) {
        if (filemtime($path) < now()->subDay()->getTimestamp()) {
            is_dir($path) ? \Illuminate\Support\Facades\File::deleteDirectory($path) : @unlink($path);
        }
    }
})->daily()->name('app-builder-upload-cleanup');

// Visitors' IP addresses are kept only to help block spam: forget them after 90 days (the enquiry itself stays).
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Models\ContactMessage::whereNotNull('ip')
    ->where('created_at', '<', now()->subDays(90))->update(['ip' => null]))->daily()->name('forget-enquiry-ips');

// Online store builder: card orders left unpaid past their hold are cancelled (their stock goes back on sale) and,
// when the shop allows it, the customer gets one "you left something" email — on time, not only at the next order.
\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\BuilderStore::whereHas('orders', fn ($q) => $q->where('status', 'unpaid'))->get()
        ->each(fn ($store) => \App\Support\Shop::releaseExpired($store));
})->everyFiveMinutes()->name('shop-release-unpaid')->withoutOverlapping();

// Booking page builder: a reminder email to the client about a day before their booking (once; only for bookings
// made at least 12 hours ahead — people who book for this afternoon don't need one).
\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\BuilderBooking::where('status', 'confirmed')->whereNull('reminded_at')
        ->whereBetween('starts_at', [now()->addHours(2), now()->addHours(24)])->with('page.account')->limit(100)->get()
        ->each(fn ($b) => \App\Support\BookingReminders::send($b));
})->everyFifteenMinutes()->name('booking-reminders')->withoutOverlapping();

// Data for scripts/share-images.mjs (the share pictures in public/images/og): the owl drawing and the page titles
Artisan::command('share-images:data {dir}', function (string $dir) {
    @mkdir($dir, 0775, true);
    file_put_contents($dir.'/owl-dev.svg.html', view('partials.owl', ['alt' => '', 'variant' => 'dev'])->render());
    file_put_contents($dir.'/og-pages.json', json_encode([
        'guides' => collect(\App\Support\Guides::ALL)->map(fn ($g, $r) => ['route' => $r, 'title' => $g[2], 'path' => $g[0]])->values(),
        'builders' => collect(\App\Support\FreeTools::builders())->map(fn ($t) => ['route' => $t['route'], 'title' => $t['name'], 'desc' => $t['desc'], 'path' => parse_url(route($t['route']), PHP_URL_PATH)])->values(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $this->info('Wrote '.$dir.'/owl-dev.svg.html and og-pages.json');
})->purpose('Write the data the share picture script needs');
