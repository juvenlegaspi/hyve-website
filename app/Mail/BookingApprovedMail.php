<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingApprovedMail extends Mailable
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
            subject: 'HYVE Booking Approved - '.$this->context['reference_no'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-approved',
            with: [
                'context' => $this->context,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = storage_path('app/hyve-house-rules.pdf');

        if (! is_file($path)) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as('HYVE House Rules.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
