<?php

namespace App\Mail;

use App\Models\BuilderStore;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// A one-time code for a shop's customer to see their orders (online store builder, /shop/{id}/account).
class BuilderShopSignIn extends Mailable
{
    public function __construct(public BuilderStore $store, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.senders.orders'), mb_strimwidth($this->store->name, 0, 60, '…').' via Owlvix'),
            subject: $this->code.' is your code for your orders at '.$this->store->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-shop-signin');
    }
}
