<?php

namespace App\Mail;

use App\Models\BuilderForm;
use App\Models\BuilderResponse;
use App\Support\SurveySchema;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the owner of a form-builder form when someone answers it. Replying goes to the first email answer.
class BuilderSurveyResponse extends Mailable
{
    public function __construct(public BuilderForm $form, public BuilderResponse $response) {}

    public function envelope(): Envelope
    {
        $reply = SurveySchema::replyTo($this->form->schema ?? [], $this->response->answers ?? []);

        return new Envelope(
            subject: 'New answer: '.$this->form->site_name,
            replyTo: $reply ? [new Address($reply)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-survey-response');
    }
}
