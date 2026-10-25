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
