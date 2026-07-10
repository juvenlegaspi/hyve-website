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
        $attachments = [];

        $documents = [
            [
                'path' => storage_path('app/hyve-house-rules.pdf'),
                'name' => 'HYVE House Rules.pdf',
            ],
            [
                'path' => storage_path('app/hyve-booking-terms-and-conditions.pdf'),
                'name' => 'HYVE Booking Terms and Conditions.pdf',
            ],
        ];

        foreach ($documents as $document) {
            if (! is_file($document['path'])) {
                continue;
            }

            $attachments[] = Attachment::fromPath($document['path'])
                ->as($document['name'])
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
