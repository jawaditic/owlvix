<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the visitor right after they submit the quote popup or contact form.
class QuoteReceived extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We got your request — Owlvix');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quote-received');
    }
}
