<?php

// Login for the /admin dashboard. Set these in .env:
//   ADMIN_EMAIL=you@example.com
//   ADMIN_PASSWORD_HASH=   (create with: php artisan admin:password)
return [
    'email' => env('ADMIN_EMAIL'),
    'password_hash' => env('ADMIN_PASSWORD_HASH'),
];
