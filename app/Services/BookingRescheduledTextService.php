<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingRescheduledTextService
{
    /** @param array<string, mixed> $context */
    public function send(string $phone, array $context): void
    {
        $apiKey = (string) config('services.semaphore.api_key');
        $sender = (string) config('services.semaphore.sender_name');
        $caBundle = trim((string) config('services.semaphore.ca_bundle'));
        $message = sprintf(
            'Hi %s! HYVE moved booking %s to %s, %s. New balance: Php %s. Contact HYVE if you need help.',
            (string) ($context['customer_name'] ?? 'Customer'),
            (string) ($context['reference_no'] ?? ''),
            (string) ($context['new_room'] ?? 'Room'),
            (string) ($context['new_schedule'] ?? ''),
            number_format((float) ($context['balance_amount'] ?? 0), 2),
        );

        if ($apiKey === '') {
            Log::info('Booking reschedule SMS not sent because Semaphore is not configured.', [
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

            $request->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $this->normalizePhone($phone),
                'message' => $message,
                'sendername' => $sender !== '' ? $sender : null,
            ])->throw();

            Log::info('Booking reschedule SMS submitted.', [
                'phone' => $phone,
                'reference_no' => $context['reference_no'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send booking reschedule SMS.', [
                'phone' => $phone,
                'reference_no' => $context['reference_no'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '63'.substr($digits, 1);
        }

        return $digits;
    }
}
