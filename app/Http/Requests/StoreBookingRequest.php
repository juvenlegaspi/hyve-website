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

        if ($this->input('payment_method') === 'pay_later') {
            $this->merge(['downpayment_amount' => 0]);
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
        $adminWalkIn = $this->routeIs('admin.bookings.create', 'admin.bookings.store');
        $commonAreaOnly = $this->isCommonAreaOnlyBooking();
        $payLaterAllowed = $adminWalkIn || $commonAreaOnly;
        $isPayLater = $payLaterAllowed && $this->input('payment_method') === 'pay_later';
        $paymentMethods = ['gcash', 'bank_transfer'];

        if ($adminWalkIn) {
            $paymentMethods[] = 'cash';
        }

        if ($payLaterAllowed) {
            $paymentMethods[] = 'pay_later';
        }
        $agreementRules = $adminWalkIn
            ? ['nullable']
            : ['required', 'accepted'];

        $rules = [
            'booking_mode' => ['nullable', Rule::in(['room', 'schedule', 'monthly'])],
            'hyve_room_id' => ['required_unless:booking_mode,schedule', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required_unless:booking_mode,schedule', 'date', 'after_or_equal:today'],
            'booking_end_date' => ['required_if:booking_mode,monthly', 'date', 'after_or_equal:booking_date'],
            'start_time' => ['required_if:booking_mode,room', 'date_format:H:i'],
            'long_stay_use_type' => [
                'nullable',
                Rule::in(['day', 'night']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('booking_mode') !== 'monthly') {
                        return;
                    }

                    $roomId = $this->input('hyve_room_id');
                    $bookingDate = $this->input('booking_date');
                    $endDate = $this->input('booking_end_date');

                    if (! is_numeric($roomId) || ! is_string($bookingDate) || ! is_string($endDate)) {
                        return;
                    }

                    /** @var HyvePricing $pricing */
                    $pricing = app(HyvePricing::class);
                    $room = HyveRoom::query()->active()->find((int) $roomId);

                    if (! $room) {
                        return;
                    }

                    if ($pricing->longStayRequiresUseType($room, $bookingDate, $endDate) && ! is_string($value)) {
                        $fail('Choose Day Use or Night Use first so HYVE can compute the correct long-stay rate.');
                    }
                },
            ],
            'end_time' => [
                'required_if:booking_mode,room',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('booking_mode') !== 'room') {
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
                        $minimumHours = $minimumDuration / 60;
                        $fail('The end time must be at least '.$minimumHours.' hour'.($minimumHours === 1.0 ? '' : 's').' after the start time.');
                    }
                },
            ],
            'monthly_plan' => ['nullable', 'string', 'max:120'],
            'selected_schedule_items' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('booking_mode') !== 'schedule') {
                        return;
                    }

                    if (! is_array($value) || $value === []) {
                        $fail('Select at least one schedule slot before continuing to checkout.');
                        return;
                    }

                    $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
                    $totalDuration = collect($value)
                        ->filter(fn ($item) => is_array($item))
                        ->sum(function (array $item): int {
                            $startTime = $item['start_time'] ?? null;
                            $endTime = $item['end_time'] ?? null;

                            if (! is_string($startTime) || ! is_string($endTime)) {
                                return 0;
                            }

                            $startMinutes = $this->minutesFromTime($startTime);
                            $endMinutes = $this->minutesFromTime($endTime);

                            if ($startMinutes === null || $endMinutes === null) {
                                return 0;
                            }

                            if ($endMinutes <= $startMinutes) {
                                $endMinutes += 24 * 60;
                            }

                            return $endMinutes - $startMinutes;
                        });

                    if ($totalDuration < $minimumDuration) {
                        $fail('Select at least '.($minimumDuration / 60).' hours of schedule slots before continuing to checkout.');
                    }
                },
            ],
            'selected_schedule_items.*.hyve_room_id' => ['required_if:booking_mode,schedule', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'selected_schedule_items.*.booking_date' => ['required_if:booking_mode,schedule', 'date', 'after_or_equal:today'],
            'selected_schedule_items.*.start_time' => ['required_if:booking_mode,schedule', 'date_format:H:i'],
            'selected_schedule_items.*.end_time' => ['required_if:booking_mode,schedule', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'rules_agreement' => $agreementRules,
            'downpayment_amount' => [
                Rule::requiredIf(! $isPayLater),
                'nullable',
                'numeric',
                $isPayLater ? 'min:0' : 'gt:0',
                function (string $attribute, mixed $value, Closure $fail) use ($isPayLater): void {
                    if ($isPayLater) {
                        if ((float) ($value ?? 0) !== 0.0) {
                            $fail('Set the initial payment to Php 0.00 when the customer will pay upon checkout.');
                        }

                        return;
                    }

                    /** @var HyvePricing $pricing */
                    $pricing = app(HyvePricing::class);
                    $bookingMode = (string) ($this->input('booking_mode') ?? 'room');

                    if ($bookingMode === 'schedule') {
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

                    if ($bookingMode === 'monthly') {
                        $roomId = $this->input('hyve_room_id');
                        $bookingDate = $this->input('booking_date');
                        $endDate = $this->input('booking_end_date');

                        if (! is_numeric($roomId) || ! is_string($bookingDate) || ! is_string($endDate) || ! is_numeric($value)) {
                            return;
                        }

                        $room = HyveRoom::query()->active()->find((int) $roomId);

                        if (! $room) {
                            return;
                        }

                        $quote = $pricing->quoteForLongStayRoom(
                            $room,
                            (string) $this->input('monthly_plan', ''),
                            $bookingDate,
                            $endDate,
                            (string) $this->input('long_stay_use_type', '') ?: null,
                        );

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
                Rule::requiredIf(fn () => ! (($adminWalkIn && $this->input('payment_method') === 'cash') || $isPayLater)),
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif',
                'max:5120',
            ],
            'notes' => [
                Rule::requiredIf(fn () => $adminWalkIn && $this->input('payment_method') === 'cash'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];

        $requiresGuestContact = ! $this->user() || $this->routeIs('admin.bookings.create', 'admin.bookings.store');

        if ($requiresGuestContact) {
            $rules['full_name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
            $rules['phone'] = ['required', 'string', 'max:30'];
        }

        return $rules;
    }

    private function isCommonAreaOnlyBooking(): bool
    {
        if ($this->input('booking_mode') === 'schedule') {
            $roomIds = collect($this->input('selected_schedule_items', []))
                ->filter(fn ($item): bool => is_array($item) && is_numeric($item['hyve_room_id'] ?? null))
                ->map(fn (array $item): int => (int) $item['hyve_room_id'])
                ->unique()
                ->values();

            if ($roomIds->isEmpty()) {
                return false;
            }

            $rooms = HyveRoom::query()->active()->whereKey($roomIds)->get();

            return $rooms->count() === $roomIds->count()
                && $rooms->every(fn (HyveRoom $room): bool => $room->isSharedTable());
        }

        $roomId = $this->input('hyve_room_id');

        if (! is_numeric($roomId)) {
            return false;
        }

        return HyveRoom::query()->active()->find((int) $roomId)?->isSharedTable() ?? false;
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
            'booking_end_date' => 'end date',
            'long_stay_use_type' => 'stay use type',
            'selected_schedule_items' => 'selected schedule items',
            'monthly_plan' => 'long-stay pricing',
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
