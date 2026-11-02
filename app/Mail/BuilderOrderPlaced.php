<?php

namespace App\Mail;

use App\Models\BuilderOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a shop owner (online store builder) for every new order. Replying goes to the customer.
class BuilderOrderPlaced extends Mailable
{
    public function __construct(public BuilderOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.noreply'), 'Owlvix'),
            subject: 'New order '.$this->order->label().' — '.$this->order->money($this->order->total).' — '.$this->order->store->name,
            replyTo: [new Address($this->order->email, $this->order->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-order-placed');
    }
}
