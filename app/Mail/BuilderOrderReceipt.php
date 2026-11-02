<?php

namespace App\Mail;

use App\Models\BuilderOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the customer of a shop made with the online store builder. Replying goes to the shop owner.
class BuilderOrderReceipt extends Mailable
{
    public function __construct(public BuilderOrder $order, public string $ownerEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.orders'), mb_strimwidth($this->order->store->name, 0, 60, '…').' via Owlvix'),
            subject: 'Your order '.$this->order->label().' from '.$this->order->store->name,
            replyTo: [new Address($this->ownerEmail, $this->order->store->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-order-receipt');
    }
}
