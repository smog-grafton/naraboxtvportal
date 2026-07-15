<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenderedTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $emailSubject,
        protected string $emailBody
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->emailBody);
    }

    public function attachments(): array
    {
        return [];
    }
}
