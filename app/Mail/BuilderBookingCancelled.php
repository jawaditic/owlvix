<?php

namespace App\Mail;

use App\Models\BuilderBooking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// A booking was cancelled. $recipient = 'owner' (the client cancelled) or 'client' (the business cancelled).
class BuilderBookingCancelled extends Mailable
{
    public function __construct(public BuilderBooking $booking, public string $recipient, public string $ownerEmail = '') {}

    public function envelope(): Envelope
    {
        $name = $this->booking->page->name();
        $when = $this->booking->localStart()->format('D j M, H:i');

        return $this->recipient === 'client'
            ? new Envelope(from: new Address(config('mail.senders.orders'), mb_strimwidth($name, 0, 60, '…').' via Owlvix'),
                subject: 'Cancelled: '.$this->booking->service.', '.$when, replyTo: $this->ownerEmail ? [new Address($this->ownerEmail, $name)] : [])
            : new Envelope(from: new Address(config('mail.senders.noreply'), 'Owlvix'),
                subject: 'Booking cancelled: '.$this->booking->service.', '.$when.' — '.mb_strimwidth($this->booking->name, 0, 40, '…'),
                replyTo: [new Address($this->booking->email, $this->booking->name)]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-booking-cancelled');
    }
}
