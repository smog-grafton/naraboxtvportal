<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $paymentData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $template = EmailTemplate::getByName('payment_success');
        
        if ($template) {
            $rendered = $template->render($this->paymentData);
            return new Envelope(subject: $rendered['subject']);
        }

        return new Envelope(
            subject: 'NaraBox - Payment Successful',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = EmailTemplate::getByName('payment_success');
        
        if ($template) {
            $rendered = $template->render($this->paymentData);
            return new Content(htmlString: $rendered['body']);
        }

        // Fallback template
        $body = view('emails.payment-success', $this->paymentData)->render();
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
