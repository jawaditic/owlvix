<?php

namespace App\Mail;

use App\Models\BuilderOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a shop's customer when the owner refunds (part of) their order (online store builder).
class BuilderOrderRefunded extends Mailable
{
    public function __construct(public BuilderOrder $order, public string $ownerEmail, public int $amount) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.orders'), mb_strimwidth($this->order->store->name, 0, 60, '…').' via Owlvix'),
            subject: 'Refund of '.$this->order->money($this->amount).' for your order '.$this->order->label(),
            replyTo: [new Address($this->ownerEmail, $this->order->store->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-order-refunded');
    }
}
