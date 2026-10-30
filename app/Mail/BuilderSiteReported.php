<?php

namespace App\Mail;

use App\Models\BuilderSite;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the site owner (admin) when a visitor reports a page published with the builder.
class BuilderSiteReported extends Mailable
{
    public function __construct(public BuilderSite $site, public string $reason, public ?string $email = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Page reported: /p/'.$this->site->slug,
            replyTo: $this->email ? [new Address($this->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-report');
    }
}
