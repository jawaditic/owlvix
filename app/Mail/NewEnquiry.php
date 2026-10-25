<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the site owner when a new enquiry comes in.
class NewEnquiry extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry: '.$this->enquiry->name.' — '.($this->enquiry->service ?? 'Owlvix'),
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-enquiry');
    }
}
