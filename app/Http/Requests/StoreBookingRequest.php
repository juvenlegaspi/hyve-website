<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\HyveRoom;
use Closure;
use App\Support\HyvePricing;

class StoreBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('selected_schedule_items');

        if (is_string($items) && $items !== '') {
            $decoded = json_decode($items, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([
                    'selected_schedule_items' => is_array($decoded) ? $decoded : [],
                ]);
            }
        }
    }

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
            'booking_mode' => ['nullable', Rule::in(['room', 'schedule'])],
            'hyve_room_id' => ['required_unless:booking_mode,schedule', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required_unless:booking_mode,schedule', 'date', 'after_or_equal:today'],
            'start_time' => ['required_unless:booking_mode,schedule', 'date_format:H:i'],
            'end_time' => [
                'required_unless:booking_mode,schedule',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('booking_mode') === 'schedule') {
                        return;
                    }

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
            'selected_schedule_items' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('booking_mode') !== 'schedule') {
                        return;
                    }

                    if (! is_array($value) || $value === []) {
                        $fail('Select at least one schedule slot before continuing to checkout.');
                    }
                },
            ],
            'selected_schedule_items.*.hyve_room_id' => ['required_if:booking_mode,schedule', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'selected_schedule_items.*.booking_date' => ['required_if:booking_mode,schedule', 'date', 'after_or_equal:today'],
            'selected_schedule_items.*.start_time' => ['required_if:booking_mode,schedule', 'date_format:H:i'],
            'selected_schedule_items.*.end_time' => ['required_if:booking_mode,schedule', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'payment_method' => ['required', Rule::in(['gcash', 'bank_transfer'])],
            'downpayment_amount' => [
                'required',
                'numeric',
                'gt:0',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var HyvePricing $pricing */
                    $pricing = app(HyvePricing::class);

                    if ($this->input('booking_mode') === 'schedule') {
                        $items = $this->input('selected_schedule_items');

                        if (! is_array($items) || ! is_numeric($value)) {
                            return;
                        }

                        $grandTotal = 0.0;

                        foreach ($items as $item) {
                            if (! is_array($item)) {
                                return;
                            }

                            $roomId = $item['hyve_room_id'] ?? null;
                            $bookingDate = $item['booking_date'] ?? null;
                            $startTime = $item['start_time'] ?? null;
                            $endTime = $item['end_time'] ?? null;

                            if (! is_numeric($roomId) || ! is_string($bookingDate) || ! is_string($startTime) || ! is_string($endTime)) {
                                return;
                            }

                            $room = HyveRoom::query()->active()->find((int) $roomId);

                            if (! $room) {
                                return;
                            }

                            $quote = $pricing->quoteForRoom($room, $bookingDate, $startTime, $endTime);

                            if (! $quote) {
                                return;
                            }

                            $grandTotal += (float) $quote['total_amount'];
                        }

                        $minimumDownpayment = $pricing->minimumDownpaymentForTotal($grandTotal);

                        if ((float) $value < $minimumDownpayment) {
                            $fail('The minimum downpayment for this booking is Php '.number_format($minimumDownpayment, 2).'.');
                        }

                        if ((float) $value > $grandTotal) {
                            $fail('The downpayment amount cannot be greater than the total booking amount.');
                        }

                        return;
                    }

                    $roomId = $this->input('hyve_room_id');
                    $bookingDate = $this->input('booking_date');
                    $startTime = $this->input('start_time');
                    $endTime = $this->input('end_time');

                    if (! is_numeric($roomId) || ! is_string($bookingDate) || ! is_string($startTime) || ! is_string($endTime) || ! is_numeric($value)) {
                        return;
                    }

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
            'payment_proof' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif',
                'max:5120',
            ],
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
            'selected_schedule_items' => 'selected schedule items',
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
