<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingApprovalTextService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $phone, array $context): void
    {
        $apiKey = (string) config('services.semaphore.api_key');
        $sender = (string) config('services.semaphore.sender_name');
        $caBundle = trim((string) config('services.semaphore.ca_bundle'));
        $message = $this->buildMessage($context);

        if ($apiKey === '') {
            Log::info('Booking approval SMS not sent because Semaphore is not configured.', [
                'phone' => $phone,
                'reference_no' => $context['reference_no'] ?? null,
                'message' => $message,
            ]);

            return;
        }

        try {
            $request = Http::asForm()->timeout(10);

            if ($caBundle !== '') {
                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $this->normalizePhone($phone),
                'message' => $message,
                'sendername' => $sender !== '' ? $sender : null,
            ])
                ->throw();

            $providerMessage = $response->collect()->first();

            Log::info('Booking approval SMS submitted.', [
                'phone' => $phone,
                'reference_no' => $context['reference_no'] ?? null,
                'message_id' => is_array($providerMessage) ? ($providerMessage['message_id'] ?? null) : null,
                'status' => is_array($providerMessage) ? ($providerMessage['status'] ?? null) : null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send booking approval SMS.', [
                'phone' => $phone,
                'reference_no' => $context['reference_no'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildMessage(array $context): string
    {
        $reference = (string) ($context['reference_no'] ?? '');
        $name = (string) ($context['customer_name'] ?? 'Customer');
        $line = $context['single_line'] ?? null;

        if (is_array($line)) {
            return trim(sprintf(
                'Hi %s! Your HYVE booking is approved. Ref: %s. %s on %s, %s. Balance due: Php %s.',
                $name,
                $reference,
                $line['room_name'] ?? $line['room'] ?? 'Room',
                $line['date'] ?? '',
                $line['time'] ?? '',
                $context['balance_amount'] ?? '0.00'
            ));
        }

        return trim(sprintf(
            'Hi %s! Your HYVE booking is approved. Ref: %s. %d booking slot(s) are now confirmed. Balance due: Php %s.',
            $name,
            $reference,
            (int) ($context['booking_count'] ?? 0),
            $context['balance_amount'] ?? '0.00'
        ));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '63')) {
            return $digits;
        }

        return $digits;
    }
}
