<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $emailSubject;
    protected string $emailBody;
    protected array $emailData;

    /**
     * Create a new message instance.
     */
    public function __construct(string $templateName, array $data = [])
    {
        $template = EmailTemplate::getByName($templateName);
        
        if (!$template) {
            throw new \Exception("Email template '{$templateName}' not found");
        }

        $rendered = $template->render($data);
        $this->emailSubject = $rendered['subject'];
        $this->emailBody = $rendered['body'];
        $this->emailData = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->emailBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
