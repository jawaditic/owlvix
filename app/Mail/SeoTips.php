<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to a visitor who asked the free SEO checker to email them their tips.
class SeoTips extends Mailable
{
    public function __construct(public array $report, public ?string $name = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your SEO tips for '.parse_url($this->report['final_url'], PHP_URL_HOST).' — score '.$this->report['score'].'/100');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seo-tips');
    }
}
