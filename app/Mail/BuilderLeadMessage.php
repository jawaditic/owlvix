<?php

namespace App\Mail;

use App\Models\BuilderForm;
use App\Models\BuilderLead;
use App\Models\BuilderSite;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a funnel owner when someone fills in a lead (opt-in) form. Replying goes to the lead.
class BuilderLeadMessage extends Mailable
{
    public function __construct(public BuilderForm $form, public BuilderLead $lead, public ?BuilderSite $site = null, public ?string $page = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New lead from '.$this->form->site_name,
            replyTo: [new Address($this->lead->email, $this->lead->name ?: '')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-lead-message');
    }
}
