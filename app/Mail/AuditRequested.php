<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a visitor who requested the full SEO audit (Claude SEO report) from the SEO checker.
class AuditRequested extends Mailable
{
    public function __construct(public ContactMessage $lead, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your full SEO audit for '.parse_url($this->url, PHP_URL_HOST).' is on its way');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.audit-requested');
    }
}
