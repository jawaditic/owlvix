<?php

namespace App\Mail;

use App\Models\BuilderForm;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a builder user when someone fills in the contact form on their page. Replying goes to the visitor.
class BuilderFormMessage extends Mailable
{
    /** @param  array{name: string, email: string, phone: ?string, message: string, page: ?string}  $data */
    public function __construct(public BuilderForm $form, public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New message from your website: '.$this->form->site_name,
            replyTo: [new Address($this->data['email'], $this->data['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-form-message');
    }
}
