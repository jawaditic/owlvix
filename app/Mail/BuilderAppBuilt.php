<?php

namespace App\Mail;

use App\Models\BuilderAppBuild;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sent to the owner of a mobile app builder app when an Android build finishes (or fails), with download links.
class BuilderAppBuilt extends Mailable
{
    public function __construct(public BuilderAppBuild $build) {}

    public function envelope(): Envelope
    {
        $name = $this->build->app->name;

        return new Envelope(subject: $this->build->status === 'done' ? "Your app {$name} is ready to download" : "Your app {$name} didn’t build");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.builder-app-built', with: ['app' => $this->build->app]);
    }
}
