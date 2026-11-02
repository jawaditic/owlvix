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
    /** @param  array{name: string, email: string, phone: ?string, message: ?string, page: ?string}  $data  (no message: a waitlist sign-up) */
    public function __construct(public BuilderForm $form, public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.noreply'), 'Owlvix'),
            subject: ($this->data['message'] === null ? 'New sign-up on your website: ' : 'New message from your website: ').$this->form->site_name,
            replyTo: [new Address($this->data['email'], $this->data['name'] ?: $this->data['email'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-form-message');
    }
}
