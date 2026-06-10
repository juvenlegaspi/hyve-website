<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\HyveRoom;
use Closure;
use App\Support\HyvePricing;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'hyve_room_id' => ['required', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $startTime = $this->input('start_time');

                    if (! is_string($startTime) || ! is_string($value)) {
                        return;
                    }

                    $startMinutes = $this->minutesFromTime($startTime);
                    $endMinutes = $this->minutesFromTime($value);

                    if ($startMinutes === null || $endMinutes === null) {
                        return;
                    }

                    if ($endMinutes <= $startMinutes) {
                        $endMinutes += 24 * 60;
                    }

                    $durationMinutes = $endMinutes - $startMinutes;
                    $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 60);

                    if ($durationMinutes < $minimumDuration) {
                        $fail('The end time must be at least '.($minimumDuration / 60).' hour after the start time.');
                    }
                },
            ],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'payment_method' => ['required', Rule::in(['gcash', 'bank_transfer'])],
            'downpayment_amount' => [
                'required',
                'numeric',
                'gt:0',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $roomId = $this->input('hyve_room_id');
                    $bookingDate = $this->input('booking_date');
                    $startTime = $this->input('start_time');
                    $endTime = $this->input('end_time');

                    if (! is_numeric($roomId) || ! is_string($bookingDate) || ! is_string($startTime) || ! is_string($endTime) || ! is_numeric($value)) {
                        return;
                    }

                    /** @var HyvePricing $pricing */
                    $pricing = app(HyvePricing::class);
                    $room = HyveRoom::query()->active()->find((int) $roomId);

                    if (! $room) {
                        return;
                    }

                    $quote = $pricing->quoteForRoom($room, $bookingDate, $startTime, $endTime);

                    if (! $quote) {
                        return;
                    }

                    $minimumDownpayment = (float) ($quote['minimum_downpayment_amount'] ?? 0);

                    if ((float) $value < $minimumDownpayment) {
                        $fail('The minimum downpayment for this booking is Php '.number_format($minimumDownpayment, 2).'.');
                    }

                    if ((float) $value > (float) $quote['total_amount']) {
                        $fail('The downpayment amount cannot be greater than the total booking amount.');
                    }
                },
            ],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $this->user()) {
            $rules['full_name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
            $rules['phone'] = ['required', 'string', 'max:30'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'hyve_room_id' => 'room',
            'booking_date' => 'booking date',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'payment_method' => 'payment method',
            'downpayment_amount' => 'downpayment amount',
            'payment_proof' => 'payment proof',
        ];
    }

    private function minutesFromTime(string $value): ?int
    {
        [$hour, $minute] = array_pad(explode(':', $value), 2, null);

        if (! is_numeric($hour) || ! is_numeric($minute)) {
            return null;
        }

        return ((int) $hour * 60) + (int) $minute;
    }
}
