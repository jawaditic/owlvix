<?php

namespace App\Mail;

use App\Models\BuilderApp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the site owner (admin) when someone reports an app made with the mobile app builder.
class BuilderAppReported extends Mailable
{
    public function __construct(public BuilderApp $app, public string $reason, public ?string $email = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'App reported: '.$this->app->name.' ('.$this->app->package.')',
            replyTo: $this->email ? [new Address($this->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-app-report');
    }
}
