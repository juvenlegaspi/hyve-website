<?php

namespace App\Services;

use App\Models\BookingDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingEndingSoonTextService
{
    public function send(BookingDetail $detail, Carbon $scheduledEnd): bool
    {
        $header = $detail->bookingHeader;
        $phone = trim((string) $header?->phone);
        $apiKey = (string) config('services.semaphore.api_key');

        if ($phone === '' || $apiKey === '') {
            Log::info('Booking end reminder SMS not sent because contact details or Semaphore configuration are missing.', [
                'booking_detail_id' => $detail->getKey(),
                'reference_no' => $header?->reference_no,
                'has_phone' => $phone !== '',
                'has_api_key' => $apiKey !== '',
            ]);

            return false;
        }

        $roomName = $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'your room';
        $message = sprintf(
            'HYVE reminder: Your booking at %s ends in 5 minutes at %s. Ref: %s. Please prepare to wrap up or contact the front desk.',
            $roomName,
            $scheduledEnd->format('g:i A'),
            (string) $header?->reference_no,
        );

        try {
            $request = Http::asForm()->timeout(10);
            $caBundle = trim((string) config('services.semaphore.ca_bundle'));

            if ($caBundle !== '') {
                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $this->normalizePhone($phone),
                'message' => $message,
                'sendername' => filled(config('services.semaphore.sender_name'))
                    ? config('services.semaphore.sender_name')
                    : null,
            ])->throw();

            $providerMessage = $response->collect()->first();
            $status = is_array($providerMessage) ? (string) ($providerMessage['status'] ?? '') : '';
            $accepted = is_array($providerMessage) && ! in_array(strtolower($status), ['failed', 'refunded'], true);

            Log::log($accepted ? 'info' : 'warning', 'Booking end reminder SMS submitted.', [
                'booking_detail_id' => $detail->getKey(),
                'reference_no' => $header?->reference_no,
                'phone' => $phone,
                'message_id' => is_array($providerMessage) ? ($providerMessage['message_id'] ?? null) : null,
                'status' => $status ?: null,
            ]);

            return $accepted;
        } catch (\Throwable $exception) {
            Log::warning('Failed to send booking end reminder SMS.', [
                'booking_detail_id' => $detail->getKey(),
                'reference_no' => $header?->reference_no,
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);

            return false;
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
