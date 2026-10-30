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

// Visitors' IP addresses are kept only to help block spam: forget them after 90 days (the enquiry itself stays).
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Models\ContactMessage::whereNotNull('ip')
    ->where('created_at', '<', now()->subDays(90))->update(['ip' => null]))->daily()->name('forget-enquiry-ips');
