<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRate;
use App\Models\HyveRoom;
use App\Models\PaymentSetting;
use App\Models\Space;
use App\Support\HyvePricing;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly HyvePricing $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $memberBookings = collect();
        $spaces = Space::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
        $hyveRooms = HyveRoom::query()
            ->active()
            ->orderBy('id')
            ->get();
        $paymentSetting = $this->pricing->activePaymentSetting();
        $rateCards = HyveRate::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        if ($user) {
            $memberBookings = BookingHeader::query()
                ->with(['details.hyveRoom', 'details.space'])
                ->where('booking_type', BookingHeader::TYPE_MEMBER)
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->get();
        }

        $oldInput = $request->session()->getOldInput();
        $oldScheduleSummary = null;

        if (($oldInput['booking_mode'] ?? null) === 'schedule') {
            $oldItems = $oldInput['selected_schedule_items'] ?? [];

            if (is_string($oldItems) && $oldItems !== '') {
                $decodedItems = json_decode($oldItems, true);
                $oldItems = json_last_error() === JSON_ERROR_NONE && is_array($decodedItems)
                    ? $decodedItems
                    : [];
            }

            if (is_array($oldItems) && $oldItems !== []) {
                $normalizedItems = collect($oldItems)
                    ->filter(fn ($item) => is_array($item))
                    ->values();
                $roomLookup = $hyveRooms->keyBy('id');
                $total = 0.0;

                foreach ($normalizedItems as $item) {
                    $roomId = isset($item['hyve_room_id']) ? (int) $item['hyve_room_id'] : 0;
                    $bookingDate = $item['booking_date'] ?? null;
                    $startTime = $item['start_time'] ?? null;
                    $endTime = $item['end_time'] ?? null;
                    $room = $roomLookup->get($roomId);

                    if (! $room instanceof HyveRoom || ! is_string($bookingDate) || ! is_string($startTime) || ! is_string($endTime)) {
                        continue;
                    }

                    $quote = $this->pricing->quoteForRoom($room, $bookingDate, $startTime, $endTime);
                    $total += (float) ($quote['total_amount'] ?? 0);
                }

                $minimumDownpayment = $this->pricing->minimumDownpaymentForTotal($total);
                $customerDownpayment = round((float) ($oldInput['downpayment_amount'] ?? 0), 2);

                $oldScheduleSummary = [
                    'slot_count' => $normalizedItems->count(),
                    'room_count' => $normalizedItems->pluck('hyve_room_id')->filter()->unique()->count(),
                    'date_count' => $normalizedItems->pluck('booking_date')->filter()->unique()->count(),
                    'first_date' => $normalizedItems->pluck('booking_date')->filter()->first(),
                    'items' => $normalizedItems->map(function (array $item) use ($roomLookup): array {
                        $roomId = isset($item['hyve_room_id']) ? (int) $item['hyve_room_id'] : 0;
                        $room = $roomLookup->get($roomId);
                        $bookingDate = (string) ($item['booking_date'] ?? '');
                        $startTime = (string) ($item['start_time'] ?? '');
                        $endTime = (string) ($item['end_time'] ?? '');
                        $label = $item['label'] ?? trim($startTime.'-'.$endTime);
                        $amount = round((float) ($item['total_amount'] ?? 0), 2);

                        return [
                            'room_name' => $item['room_name'] ?? $room?->room_name ?? 'Room',
                            'room_space' => $item['room_space'] ?? $room?->mappedSpaceLabel() ?? '',
                            'booking_date' => $bookingDate,
                            'label' => (string) $label,
                            'total_amount' => $amount,
                        ];
                    })->all(),
                    'total_amount' => round($total, 2),
                    'minimum_downpayment_amount' => round($minimumDownpayment, 2),
                    'remaining_balance' => round(max(0, $total - $customerDownpayment), 2),
                ];
            }
        }

        return view('bookings.index', [
            'meta' => [
                'title' => $user ? 'Member Booking | HYVE Workspace' : 'Book a Space | HYVE Workspace',
                'description' => 'Reserve a HYVE workspace directly, or sign in as a monthly member to manage your account-based booking experience.',
            ],
            'bookingConfig' => [
                'availability_url' => route('bookings.availability'),
                'unavailable_dates_url' => route('bookings.unavailable-dates'),
                'layout_url' => route('bookings.room-layout'),
                'quote_url' => route('bookings.quote'),
                'slot_interval_minutes' => (int) config('hyve.booking.slot_interval_minutes', 30),
                'minimum_duration_minutes' => (int) config('hyve.booking.minimum_duration_minutes', 60),
                'unavailable_dates_horizon' => 14,
            ],
            'layoutSections' => $this->layoutSections($hyveRooms),
            'hyveRooms' => $hyveRooms,
            'spaces' => $spaces,
            'paymentSetting' => $paymentSetting,
            'rates' => $rateCards->map(fn (HyveRate $rate): array => $rate->toDisplayArray())->all(),
            'memberBookings' => $memberBookings,
            'oldScheduleSummary' => $oldScheduleSummary,
            'user' => $user,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $room = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
        $quote = $this->pricing->quoteForRoom($room, $validated['booking_date'], $validated['start_time'], $validated['end_time']);

        if (! $quote) {
            return response()->json([
                'message' => 'No active rate card is configured for the selected room.',
            ], 422);
        }

        /** @var PaymentSetting|null $paymentSetting */
        $paymentSetting = $quote['payment_setting'];

        return response()->json([
            'rate_name' => $quote['rate_name'],
            'charge_period' => $quote['charge_period'],
            'charge_period_label' => $quote['charge_period_label'],
            'duration_hours' => $quote['duration_hours'],
            'billed_hours' => $quote['billed_hours'],
            'minimum_hours' => $quote['minimum_hours'],
            'minimum_rate' => $quote['minimum_rate'],
            'succeeding_hour_rate' => $quote['succeeding_hour_rate'],
            'total_amount' => $quote['total_amount'],
            'minimum_downpayment_amount' => $quote['minimum_downpayment_amount'],
            'payment' => [
                'downpayment_percentage' => (float) ($paymentSetting?->downpayment_percentage ?? 50),
                'gcash_account_name' => $paymentSetting?->gcash_account_name,
                'gcash_number' => $paymentSetting?->gcash_number,
                'bank_name' => $paymentSetting?->bank_name,
                'bank_account_name' => $paymentSetting?->bank_account_name,
                'bank_account_number' => $paymentSetting?->bank_account_number,
                'instructions' => $paymentSetting?->instructions,
            ],
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ]);

        $room = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
        $snapshot = $this->bookingSnapshotForRoom($room, $validated['booking_date'], $validated['start_time'] ?? null);

        return response()->json([
            'start_times' => $snapshot['start_times']->values()->all(),
            'end_times' => $snapshot['end_times']->values()->all(),
            'slots' => $snapshot['start_times']->values()->all(),
            'room' => [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'space_label' => $room->mappedSpaceLabel(),
                'status' => $snapshot['status'],
            ],
        ]);
    }

    public function unavailableDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $room = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
        $horizonDays = (int) ($validated['horizon_days'] ?? 14);

        return response()->json([
            'unavailable_dates' => $this->fullyBookedDates($room, $horizonDays)->values()->all(),
        ]);
    }

    public function roomLayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $rooms = HyveRoom::query()
            ->active()
            ->orderBy('id')
            ->get();

        return response()->json([
            'booking_date' => $validated['booking_date'],
            'rooms' => $rooms->map(function (HyveRoom $room) use ($validated): array {
                $snapshot = $this->bookingSnapshotForRoom($room, $validated['booking_date']);

                return [
                    'id' => $room->id,
                    'room_name' => $room->room_name,
                    'description' => $room->description,
                    'status' => $snapshot['status'],
                    'space_label' => $room->mappedSpaceLabel(),
                    'space_slug' => $room->mappedSpaceSlug(),
                    'available_slots' => $snapshot['available_slots']->values()->all(),
                    'booked_slots' => $snapshot['booked_slots']->values()->all(),
                    'booking_details' => $this->bookingDetailsForLayout($snapshot),
                ];
            })->values()->all(),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $isScheduleMode = ($validated['booking_mode'] ?? 'room') === 'schedule';
        $bookingItems = [];
        $grandTotal = 0.0;

        if ($isScheduleMode) {
            $unavailableItems = [];

            foreach ((array) ($validated['selected_schedule_items'] ?? []) as $item) {
                $room = HyveRoom::query()->active()->findOrFail((int) $item['hyve_room_id']);
                $space = $this->spaceForRoom($room);
                $quote = $this->pricing->quoteForRoom($room, $item['booking_date'], $item['start_time'], $item['end_time']);

                if (! $this->isTimeRangeAvailable($room, $item['booking_date'], $item['start_time'], $item['end_time'])) {
                    $unavailableItems[] = sprintf(
                        '%s - %s - %s to %s',
                        $room->room_name,
                        Carbon::parse($item['booking_date'])->format('F j, Y'),
                        Carbon::createFromFormat('H:i', $item['start_time'])->format('g:i A'),
                        Carbon::createFromFormat('H:i', $item['end_time'])->format('g:i A'),
                    );

                    continue;
                }

                if (! $quote) {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'selected_schedule_items' => 'One of the selected rooms does not have active pricing yet.',
                        ]);
                }

                $bookingItems[] = [
                    'room' => $room,
                    'space' => $space,
                    'quote' => $quote,
                    'booking_date' => $item['booking_date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                ];
                $grandTotal += (float) $quote['total_amount'];
            }

            if ($unavailableItems !== []) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'selected_schedule_items' => 'These schedule slots are no longer available: '.implode('; ', $unavailableItems).'. Please remove them and choose another time.',
                    ]);
            }
        } else {
            $room = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
            $space = $this->spaceForRoom($room);
            $quote = $this->pricing->quoteForRoom($room, $validated['booking_date'], $validated['start_time'], $validated['end_time']);

            if (! $this->isTimeRangeAvailable($room, $validated['booking_date'], $validated['start_time'], $validated['end_time'])) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'end_time' => 'The selected booking range is no longer available. Please choose a different start and end time.',
                    ]);
            }

            if (! $quote) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'hyve_room_id' => 'No active pricing is configured for the selected room yet.',
                    ]);
            }

            $bookingItems[] = [
                'room' => $room,
                'space' => $space,
                'quote' => $quote,
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ];
            $grandTotal = (float) $quote['total_amount'];
        }

        $customerDownpayment = round((float) $validated['downpayment_amount'], 2);
        $remainingBalance = round(max(0, $grandTotal - $customerDownpayment), 2);

        $paymentProofPath = $request->file('payment_proof')?->store('booking-payments', 'public');
        $paymentProofName = $request->file('payment_proof')?->getClientOriginalName();

        $contactDetails = $user
            ? [
                'user_id' => $user->id,
                'customer_name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'phone' => $user->number ?? $user->phone ?? '',
                'booking_type' => BookingHeader::TYPE_MEMBER,
            ]
            : [
                'customer_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'booking_type' => BookingHeader::TYPE_GUEST,
            ];

        $header = $this->database->transaction(function () use ($contactDetails, $validated, $bookingItems, $grandTotal, $paymentProofPath, $paymentProofName, $customerDownpayment, $remainingBalance): BookingHeader {
            $header = BookingHeader::query()->create([
                'reference_no' => $this->generateReferenceNumber(),
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending_verification',
                'payment_proof_path' => $paymentProofPath,
                'payment_proof_name' => $paymentProofName,
                'total_amount' => round($grandTotal, 2),
                'downpayment_amount' => $customerDownpayment,
                'balance_amount' => $remainingBalance,
                'notes' => $validated['notes'] ?? null,
                'status' => BookingHeader::STATUS_PENDING,
                ...$contactDetails,
            ]);

            foreach ($bookingItems as $bookingItem) {
                /** @var Space $space */
                $space = $bookingItem['space'];
                /** @var HyveRoom $room */
                $room = $bookingItem['room'];
                /** @var array $quote */
                $quote = $bookingItem['quote'];

                $header->details()->create([
                    'space_id' => $space->id,
                    'hyve_room_id' => $room->id,
                    'booking_date' => $bookingItem['booking_date'],
                    'start_time' => $bookingItem['start_time'],
                    'end_time' => $bookingItem['end_time'],
                    'charge_period' => $quote['charge_period'],
                    'duration_hours' => $quote['duration_hours'],
                    'billed_hours' => $quote['billed_hours'],
                    'guests' => $validated['guests'],
                    'rate_name' => $quote['rate_name'],
                    'rate_amount' => $quote['succeeding_hour_rate'],
                    'subtotal' => $quote['total_amount'],
                    'status' => BookingDetail::STATUS_PENDING,
                ]);
            }

            return $header;
        });

        return redirect()->route('bookings.index')->with(
            'booking_success',
            'Your booking request has been submitted under reference '.$header->reference_no.'. HYVE will contact you soon to confirm the details.',
        );
    }

    private function generateReferenceNumber(): string
    {
        return 'HYVE-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    /**
     * @return array{
     *     start_times: Collection<int, array{value: string, label: string}>,
     *     end_times: Collection<int, array{value: string, label: string, range_label: string, duration_label: string}>,
     *     available_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     booked_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     status: string
     * }
     */
    private function bookingSnapshotForRoom(HyveRoom $room, string $bookingDate, ?string $selectedStartTime = null): array
    {
        $dayStart = $this->slotBoundary($bookingDate, (string) config('hyve.booking.opening_time', '00:00'));
        $dayEnd = $this->slotBoundary($bookingDate, (string) config('hyve.booking.closing_time', '24:00'));
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 60);
        $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
        $blockedRanges = $this->blockedRangesForRoom($room, $bookingDate);
        $startTimes = $this->availableStartTimesForRoom($blockedRanges, $effectiveStart, $dayEnd, $minimumDuration);
        $endTimes = $selectedStartTime
            ? $this->availableEndTimesForRoom($blockedRanges, $bookingDate, $selectedStartTime, $effectiveStart, $dayEnd, $minimumDuration)
            : collect();
        $availableWindows = $this->hourlyWindowsForLayout($blockedRanges, $effectiveStart, $dayEnd, false);
        $bookedWindows = $this->hourlyWindowsForLayout($blockedRanges, $effectiveStart, $dayEnd, true);

        $status = 'available';

        if ($blockedRanges->isNotEmpty() && $startTimes->isNotEmpty()) {
            $status = 'booked';
        }

        if ($startTimes->isEmpty()) {
            $status = 'occupied';
        }

        return [
            'start_times' => $startTimes,
            'end_times' => $endTimes,
            'available_slots' => $availableWindows,
            'booked_slots' => $bookedWindows,
            'status' => $status,
        ];
    }

    private function isTimeRangeAvailable(HyveRoom $room, string $bookingDate, string $startTime, string $endTime): bool
    {
        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);
        $dayStart = $this->slotBoundary($bookingDate, (string) config('hyve.booking.opening_time', '00:00'));
        $dayEnd = $this->slotBoundary($bookingDate, (string) config('hyve.booking.closing_time', '24:00'));
        $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 60);
        $intervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($rangeStart->lt($effectiveStart) || $rangeEnd->gt($dayEnd)) {
            return false;
        }

        if ($rangeStart->diffInMinutes($rangeEnd, true) < $minimumDuration) {
            return false;
        }

        if (! $rangeStart->copy()->second(0)->equalTo($rangeStart) || ! $rangeEnd->copy()->second(0)->equalTo($rangeEnd)) {
            return false;
        }

        if ($rangeStart->minute % $intervalMinutes !== 0 || $rangeEnd->minute % $intervalMinutes !== 0) {
            return false;
        }

        return ! $this->blockedRangesForRoom($room, $bookingDate)
            ->contains(fn (array $range): bool => $rangeStart->lt($range['end']) && $rangeEnd->gt($range['start']));
    }

    private function slotBoundary(string $bookingDate, string $time): Carbon
    {
        if ($time === '24:00') {
            return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' 00:00')->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$time);
    }

    private function roundUpToNextHalfHour(Carbon $dateTime): Carbon
    {
        $rounded = $dateTime->copy()->second(0);

        if ($rounded->minute % 30 === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(30 - ($rounded->minute % 30))->second(0);
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function fullyBookedDates(HyveRoom $room, int $horizonDays): Collection
    {
        $today = Carbon::today();

        return collect(range(0, $horizonDays - 1))
            ->map(function (int $offset) use ($today, $room): ?array {
                $date = $today->copy()->addDays($offset);
                $bookingDate = $date->toDateString();
                $snapshot = $this->bookingSnapshotForRoom($room, $bookingDate);

                if ($snapshot['start_times']->isNotEmpty()) {
                    return null;
                }

                return [
                    'value' => $bookingDate,
                    'label' => $date->format('M j, Y'),
                ];
            })
            ->filter()
            ->values();
    }

    private function spaceForRoom(HyveRoom $room): Space
    {
        return Space::query()
            ->active()
            ->where('slug', $room->mappedSpaceSlug())
            ->firstOrFail();
    }

    /**
     * @return array<int, array{title: string, rooms: Collection<int, HyveRoom>}>
     */
    private function layoutSections(Collection $rooms): array
    {
        return [
            [
                'title' => 'Featured Spaces',
                'rooms' => $rooms->filter(fn (HyveRoom $room): bool => in_array($room->room_name, ['Conference Room', 'Room 7'], true))->values(),
            ],
            [
                'title' => 'Private Rooms',
                'rooms' => $rooms->filter(fn (HyveRoom $room): bool => in_array($room->room_name, ['Room 6', 'Room 5', 'Room 4', 'Room 3', 'Room 2', 'Room 1'], true))->values(),
            ],
            [
                'title' => 'Shared Tables',
                'rooms' => $rooms->filter(fn (HyveRoom $room): bool => $room->isSharedTable())->values(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function blockedStatuses(): array
    {
        return (array) config('hyve.booking.blocked_statuses', ['pending', 'confirmed']);
    }

    private function effectiveDayStart(string $bookingDate, Carbon $dayStart): Carbon
    {
        if ($bookingDate !== Carbon::today()->toDateString()) {
            return $dayStart->copy();
        }

        $roundedNow = $this->roundUpToNextHalfHour(Carbon::now());

        return $roundedNow->gt($dayStart) ? $roundedNow : $dayStart->copy();
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function blockedRangesForRoom(HyveRoom $room, string $bookingDate): Collection
    {
        $space = $this->spaceForRoom($room);

        $ranges = BookingDetail::query()
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', $this->blockedStatuses())
            ->where(function ($query) use ($room, $space) {
                $query->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $query->orWhere(function ($fallback) use ($space) {
                        $fallback->whereNull('hyve_room_id')
                            ->where('space_id', $space->id);
                    });
                }
            })
            ->get(['start_time', 'end_time'])
            ->map(function (BookingDetail $detail) use ($bookingDate): array {
                [$start, $end] = $this->dateRange($bookingDate, $detail->start_time, $detail->end_time);

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->sortBy(fn (array $range): int => $range['start']->timestamp)
            ->values();

        return $this->mergeRanges($ranges);
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $ranges
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function mergeRanges(Collection $ranges): Collection
    {
        return $ranges->reduce(function (Collection $carry, array $range): Collection {
            if ($carry->isEmpty()) {
                $carry->push([
                    'start' => $range['start']->copy(),
                    'end' => $range['end']->copy(),
                ]);

                return $carry;
            }

            $lastIndex = $carry->keys()->last();
            $lastRange = $carry->get($lastIndex);

            if ($range['start']->lte($lastRange['end'])) {
                if ($range['end']->gt($lastRange['end'])) {
                    $lastRange['end'] = $range['end']->copy();
                    $carry->put($lastIndex, $lastRange);
                }

                return $carry;
            }

            $carry->push([
                'start' => $range['start']->copy(),
                'end' => $range['end']->copy(),
            ]);

            return $carry;
        }, collect());
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $blockedRanges
     * @return Collection<int, array{value: string, label: string}>
     */
    private function availableStartTimesForRoom(Collection $blockedRanges, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration): Collection
    {
        $slotIntervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);
        $startTimes = collect();
        $cursor = $effectiveStart->copy();

        while ($cursor->copy()->addMinutes($minimumDuration)->lte($dayEnd)) {
            $availableUntil = $this->availableUntil($blockedRanges, $cursor, $dayEnd);

            if ($availableUntil && $cursor->diffInMinutes($availableUntil, true) >= $minimumDuration) {
                $startTimes->push([
                    'value' => $cursor->format('H:i'),
                    'label' => $cursor->format('g:i A'),
                ]);
            }

            $cursor->addMinutes($slotIntervalMinutes);
        }

        return $startTimes;
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $blockedRanges
     * @return Collection<int, array{value: string, label: string, range_label: string, duration_label: string}>
     */
    private function availableEndTimesForRoom(Collection $blockedRanges, string $bookingDate, string $selectedStartTime, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration): Collection
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$selectedStartTime);

        if ($selectedStartTime === '00:00' && $start->lt($effectiveStart) && $bookingDate !== Carbon::today()->toDateString()) {
            $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' 00:00');
        }

        if ($start->lt($effectiveStart)) {
            return collect();
        }

        $availableUntil = $this->availableUntil($blockedRanges, $start, $dayEnd);

        if (! $availableUntil || $start->diffInMinutes($availableUntil, true) < $minimumDuration) {
            return collect();
        }

        $slotIntervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);
        $cursor = $start->copy()->addMinutes($minimumDuration);
        $endTimes = collect();

        while ($cursor->lte($availableUntil)) {
            $endTimes->push([
                'value' => $cursor->format('H:i'),
                'label' => $this->displayTimeLabel($cursor, $start),
                'range_label' => $start->format('g:i A').' - '.$this->displayTimeLabel($cursor, $start),
                'duration_label' => $this->durationLabel($start, $cursor),
            ]);

            $cursor->addMinutes($slotIntervalMinutes);
        }

        return $endTimes;
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $blockedRanges
     * @return Collection<int, array{value: string, label: string, end_time: string}>
     */
    private function availableWindowsForLayout(Collection $blockedRanges, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration): Collection
    {
        $windows = collect();
        $cursor = $effectiveStart->copy();

        foreach ($blockedRanges as $range) {
            if ($range['end']->lte($cursor)) {
                continue;
            }

            $windowEnd = $range['start']->copy();

            if ($cursor->diffInMinutes($windowEnd, true) >= $minimumDuration) {
                $windows->push($this->windowPayload($cursor, $windowEnd));
            }

            if ($range['end']->gt($cursor)) {
                $cursor = $range['end']->copy();
            }
        }

        if ($cursor->diffInMinutes($dayEnd, true) >= $minimumDuration) {
            $windows->push($this->windowPayload($cursor, $dayEnd));
        }

        return $windows;
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $blockedRanges
     * @return Collection<int, array{value: string, label: string, end_time: string}>
     */
    private function hourlyWindowsForLayout(Collection $blockedRanges, Carbon $effectiveStart, Carbon $dayEnd, bool $booked): Collection
    {
        $slotMinutes = 60;
        $windows = collect();

        if ($booked) {
            foreach ($blockedRanges as $range) {
                $cursor = $range['start']->copy();

                while ($cursor->copy()->addMinutes($slotMinutes)->lte($range['end'])) {
                    $end = $cursor->copy()->addMinutes($slotMinutes);

                    if ($end->gt($effectiveStart) && $cursor->lt($dayEnd)) {
                        $windows->push($this->windowPayload($cursor, $end));
                    }

                    $cursor->addMinutes($slotMinutes);
                }
            }

            return $windows->values();
        }

        $cursor = $effectiveStart->copy();

        foreach ($blockedRanges as $range) {
            if ($range['end']->lte($cursor)) {
                continue;
            }

            $windowEnd = $range['start']->copy();

            while ($cursor->copy()->addMinutes($slotMinutes)->lte($windowEnd)) {
                $end = $cursor->copy()->addMinutes($slotMinutes);
                $windows->push($this->windowPayload($cursor, $end));
                $cursor->addMinutes($slotMinutes);
            }

            if ($range['end']->gt($cursor)) {
                $cursor = $range['end']->copy();
            }
        }

        while ($cursor->copy()->addMinutes($slotMinutes)->lte($dayEnd)) {
            $end = $cursor->copy()->addMinutes($slotMinutes);
            $windows->push($this->windowPayload($cursor, $end));
            $cursor->addMinutes($slotMinutes);
        }

        return $windows->values();
    }

    /**
     * @param Collection<int, array{start: Carbon, end: Carbon}> $blockedRanges
     */
    private function availableUntil(Collection $blockedRanges, Carbon $start, Carbon $dayEnd): ?Carbon
    {
        foreach ($blockedRanges as $range) {
            if ($start->gte($range['start']) && $start->lt($range['end'])) {
                return null;
            }

            if ($range['start']->gt($start)) {
                return $range['start']->copy();
            }
        }

        return $dayEnd->copy();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(string $bookingDate, string $startTime, string $endTime): array
    {
        $start = Carbon::parse($bookingDate.' '.$startTime);
        $end = Carbon::parse($bookingDate.' '.$endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * @return array{value: string, label: string, end_time: string}
     */
    private function windowPayload(Carbon $start, Carbon $end): array
    {
        return [
            'value' => $start->format('H:i'),
            'label' => $start->format('g:i A').' - '.$this->displayTimeLabel($end, $start),
            'end_time' => $end->format('H:i'),
        ];
    }

    private function displayTimeLabel(Carbon $time, Carbon $start): string
    {
        $label = $time->format('g:i A');

        if ($time->isSameDay($start)) {
            return $label;
        }

        return $label.' next day';
    }

    private function durationLabel(Carbon $start, Carbon $end): string
    {
        $minutes = $start->diffInMinutes($end, true);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' '.Str::plural('hour', $hours);
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes.' mins';
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{
     *     available_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     booked_slots: Collection<int, array{value: string, label: string, end_time: string}>
     * } $snapshot
     * @return array<int, array{label: string, type: string}>
     */
    private function bookingDetailsForLayout(array $snapshot): array
    {
        return $snapshot['available_slots']
            ->map(fn (array $slot): array => [
                'label' => $slot['label'],
                'type' => 'available',
            ])
            ->concat(
                $snapshot['booked_slots']->map(fn (array $slot): array => [
                    'label' => $slot['label'],
                    'type' => 'booked',
                ])
            )
            ->values()
            ->all();
    }
}
