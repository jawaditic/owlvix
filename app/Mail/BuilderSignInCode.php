<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// The 6-digit code that signs a visitor in to the free landing page builder.
class BuilderSignInCode extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your Owlvix page builder code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-code');
    }
}
