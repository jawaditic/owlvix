<?php

namespace App\Mail;

use App\Models\BuilderBooking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the client who booked on a booking page. Replying goes to the business.
class BuilderBookingConfirmed extends Mailable
{
    public function __construct(public BuilderBooking $booking, public string $ownerEmail, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $name = $this->booking->page->name();

        return new Envelope(
            from: new Address(config('mail.senders.orders'), mb_strimwidth($name, 0, 60, '…').' via Owlvix'),
            subject: ($this->reminder ? 'Reminder: ' : 'Booked: ').$this->booking->service.', '.$this->booking->localStart()->format('D j M, H:i'),
            replyTo: [new Address($this->ownerEmail, $name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-booking-confirmed');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->booking->ics(), 'booking.ics')->withMime('text/calendar')];
    }
}
