<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\MessageReply;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// A reply written in the admin dashboard, emailed to the visitor.
class EnquiryReply extends Mailable
{
    public function __construct(public ContactMessage $enquiry, public MessageReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reply->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reply');
    }
}
