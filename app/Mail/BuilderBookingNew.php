<?php

namespace App\Mail;

use App\Models\BuilderBooking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the owner of a booking page when someone books. Replying goes to the client.
class BuilderBookingNew extends Mailable
{
    public function __construct(public BuilderBooking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.noreply'), 'Owlvix'),
            subject: 'New booking: '.$this->booking->service.', '.$this->booking->localStart()->format('D j M, H:i').' — '.mb_strimwidth($this->booking->name, 0, 40, '…'),
            replyTo: [new Address($this->booking->email, $this->booking->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-booking-new');
    }
}
