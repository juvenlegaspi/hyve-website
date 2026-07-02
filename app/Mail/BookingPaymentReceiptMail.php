<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingPaymentReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public array $context)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HYVE Payment Receipt - '.$this->context['reference_no'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-payment-receipt',
            with: [
                'context' => $this->context,
            ],
        );
    }
}
