<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public string $email;

    /**
     * Create a new message instance.
     */
    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $template = EmailTemplate::getByName('password_reset');
        
        if ($template) {
            $rendered = $template->render([
                'email' => $this->email,
                'token' => $this->token,
                'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->email),
            ]);
            return new Envelope(subject: $rendered['subject']);
        }

        return new Envelope(
            subject: 'NaraBox - Reset Your Password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = EmailTemplate::getByName('password_reset');
        
        if ($template) {
            $rendered = $template->render([
                'email' => $this->email,
                'token' => $this->token,
                'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->email),
            ]);
            return new Content(htmlString: $rendered['body']);
        }

        // Fallback template
        $resetUrl = (config('app.frontend_url') ?? 'http://localhost:3000') . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->email);
        $body = view('emails.password-reset', [
            'email' => $this->email,
            'token' => $this->token,
            'resetUrl' => $resetUrl,
        ])->render();

        return new Content(htmlString: $body);
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
