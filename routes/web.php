<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

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
