<?php

namespace App\Support;

use App\Http\Controllers\BookingPublicController;
use App\Mail\BuilderBookingReminder;
use App\Models\BuilderBooking;
use Illuminate\Support\Facades\Mail;

/** The day-before reminder for a booking (routes/console.php runs this every 15 minutes). */
class BookingReminders
{
    public static function send(BuilderBooking $b): void
    {
        $page = $b->page;
        if (! $page || $page->disabled_at || $b->created_at > $b->starts_at->subHours(12) || ! BookingPublicController::mayEmailClient($page)) {
            return;
        }
        // claim it first, so a slow run or a second server never sends it twice
        if (! BuilderBooking::whereKey($b->id)->whereNull('reminded_at')->where('status', 'confirmed')->update(['reminded_at' => now()])) {
            return;
        }
        BookingPublicController::countClientEmail($page);
        try {
            Mail::to($b->email)->send(new BuilderBookingReminder($b, $page->account->email));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
