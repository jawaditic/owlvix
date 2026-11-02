<?php

namespace App\Mail;

use App\Models\BuilderBooking;

// The day-before reminder to the client: the confirmation email again, with "Reminder:" in the subject.
class BuilderBookingReminder extends BuilderBookingConfirmed
{
    public function __construct(BuilderBooking $booking, string $ownerEmail)
    {
        parent::__construct($booking, $ownerEmail, true);
    }
}
