<?php

namespace App\Mail;

use App\Models\BuilderOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a shop's customer when the owner marks a cash or bank order paid and it has downloads or gift cards.
class BuilderOrderPaid extends Mailable
{
    public function __construct(public BuilderOrder $order, public string $ownerEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — your order '.$this->order->label().' from '.$this->order->store->name.' is ready',
            replyTo: [new Address($this->ownerEmail, $this->order->store->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-order-paid');
    }
}
