<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Thank-you page the owl talks on, linked from the confirmation email (signed link per enquiry)
Route::get('/thanks/{message}', function (\App\Models\ContactMessage $message) {
    return view('thanks', ['enquiry' => $message]);
})->middleware('signed')->name('thanks');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// Admin dashboard
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::redirect('/', '/admin/messages');
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/export', [MessageController::class, 'export'])->name('messages.export');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
        Route::patch('messages/{message}/read', [MessageController::class, 'toggleRead'])->name('messages.read');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        // Reviews shown on the site
        Route::post('reviews/settings', [TestimonialController::class, 'settings'])->name('testimonials.settings');
        Route::post('reviews/{testimonial}/move/{direction}', [TestimonialController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('testimonials.move');
        Route::post('reviews/{testimonial}/toggle/{field}', [TestimonialController::class, 'toggle'])->name('testimonials.toggle');
        Route::resource('reviews', TestimonialController::class)->except('show')
            ->parameters(['reviews' => 'testimonial'])->names('testimonials');
    });
});
