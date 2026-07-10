<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRate;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use App\Models\PaymentSetting;
use App\Models\Space;
use App\Support\HyveCalendarService;
use App\Support\HyvePricing;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly HyvePricing $pricing,
        private readonly HyveCalendarService $calendarService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderBookingPage($request);
    }

    public function adminCreate(Request $request): View
    {
        return $this->renderBookingPage($request, true);
    }

    private function renderBookingPage(Request $request, bool $adminMode = false): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->isAdmin() && ! $adminMode) {
            return redirect()->route('admin.dashboard');
        }

        $memberBookings = collect();
        $spaces = Space::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
        $hyveRooms = HyveRoom::query()
            ->active()
            ->orderBy('id')
            ->get();
        $sharedTableRepresentative = $this->sharedTableRepresentative($hyveRooms);
        $displayRooms = $this->displayBookingRooms($hyveRooms, $sharedTableRepresentative);
        $paymentSetting = $this->pricing->activePaymentSetting();
        $rateCards = HyveRate::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
        $longStayPlansByRoom = $displayRooms->mapWithKeys(fn (HyveRoom $room): array => [
            $room->id => $this->pricing->longStayOptionsForRoom($this->pricingRoomForSelection($room, $sharedTableRepresentative)),
        ]);

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
        $preselectedRoom = $this->resolvePreselectedRoom($request, $hyveRooms, $sharedTableRepresentative);

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
                $roomLookup = $displayRooms->keyBy('id');
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

                    $quote = $this->pricing->quoteForRoom($this->pricingRoomForSelection($room, $sharedTableRepresentative), $bookingDate, $startTime, $endTime);
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
                'title' => $adminMode
                    ? 'Create Walk-In Booking | HYVE Admin'
                    : ($user ? 'Member Booking | HYVE Workspace' : 'Book a Space | HYVE Workspace'),
                'description' => $adminMode
                    ? 'Create a walk-in booking for in-person customers from the admin desk.'
                    : 'Reserve a HYVE workspace directly, or sign in as a monthly member to manage your account-based booking experience.',
            ],
            'bookingConfig' => [
                'availability_url' => route('bookings.availability'),
                'unavailable_dates_url' => route('bookings.unavailable-dates'),
                'layout_url' => route('bookings.room-layout'),
                'quote_url' => route('bookings.quote'),
                'slot_interval_minutes' => (int) config('hyve.booking.slot_interval_minutes', 30),
                'minimum_duration_minutes' => (int) config('hyve.booking.minimum_duration_minutes', 120),
                'unavailable_dates_horizon' => 365,
            ],
            'layoutSections' => $this->layoutSections($displayRooms),
            'hyveRooms' => $displayRooms,
            'allHyveRooms' => $hyveRooms,
            'sharedTableRepresentative' => $sharedTableRepresentative,
            'preselectedRoom' => $preselectedRoom,
            'spaces' => $spaces,
            'paymentSetting' => $paymentSetting,
            'rates' => $rateCards->map(fn (HyveRate $rate): array => $rate->toDisplayArray())->all(),
            'monthlyPlansByRoom' => $longStayPlansByRoom,
            'memberBookings' => $memberBookings,
            'oldScheduleSummary' => $oldScheduleSummary,
            'user' => $user,
            'adminMode' => $adminMode,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_mode' => ['nullable', Rule::in(['room', 'monthly'])],
            'hyve_room_id' => ['required', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'booking_end_date' => ['required_if:booking_mode,monthly', 'date', 'after_or_equal:booking_date'],
            'start_time' => ['required_unless:booking_mode,monthly', 'date_format:H:i'],
            'end_time' => ['required_unless:booking_mode,monthly', 'date_format:H:i'],
            'monthly_plan' => ['nullable', 'string', 'max:120'],
            'long_stay_use_type' => ['nullable', Rule::in(['day', 'night'])],
        ]);

        $room = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
        $isMonthlyMode = ($validated['booking_mode'] ?? 'room') === 'monthly';

        if (
            $isMonthlyMode
            && $this->pricing->longStayRequiresUseType($this->pricingRoomForSelection($room), $validated['booking_date'], $validated['booking_end_date'])
            && blank($validated['long_stay_use_type'] ?? null)
        ) {
            return response()->json([
                'message' => 'Choose Day Use or Night Use first so HYVE can compute the correct long-stay rate.',
            ], 422);
        }

        if ($isMonthlyMode && ! $this->isLongStayDateRangeAvailable($room, $validated['booking_date'], $validated['booking_end_date'], $validated['long_stay_use_type'] ?? null)) {
            return response()->json([
                'message' => 'The selected stay dates are no longer available for this room. Please choose another date range.',
            ], 422);
        }

        $quote = $isMonthlyMode
            ? $this->pricing->quoteForLongStayRoom(
                $this->pricingRoomForSelection($room),
                (string) ($validated['monthly_plan'] ?? ''),
                $validated['booking_date'],
                $validated['booking_end_date'],
                $validated['long_stay_use_type'] ?? null,
            )
            : $this->pricing->quoteForRoom($this->pricingRoomForSelection($room), $validated['booking_date'], $validated['start_time'], $validated['end_time']);

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
            'monthly_plan_label' => $quote['monthly_plan_label'] ?? null,
            'unit_type' => $quote['unit_type'] ?? null,
            'unit_count' => $quote['unit_count'] ?? null,
            'unit_label' => $quote['unit_label'] ?? null,
            'booking_end_date' => $quote['booking_end_date'] ?? null,
            'long_stay_use_type' => $quote['long_stay_use_type'] ?? null,
            'long_stay_use_label' => $quote['long_stay_use_label'] ?? null,
            'window_start_time' => $quote['window_start_time'] ?? null,
            'window_end_time' => $quote['window_end_time'] ?? null,
            'breakdown' => $quote['breakdown'] ?? [],
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
            'hyve_room_id' => ['required', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
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
                'room_name' => $this->bookingDisplayName($room),
                'space_label' => $room->mappedSpaceLabel(),
                'status' => $snapshot['status'],
            ],
        ]);
    }

    public function unavailableDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', Rule::exists(HyveRoom::class, 'id')->where(fn ($query) => $query->where('status', 0))],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:365'],
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
        $sharedTableRepresentative = $this->sharedTableRepresentative($rooms);
        $displayRooms = $this->displayBookingRooms($rooms, $sharedTableRepresentative);
        $snapshots = $this->roomLayoutSnapshots($displayRooms, $validated['booking_date'], $sharedTableRepresentative);

        return response()->json([
            'booking_date' => $validated['booking_date'],
            'rooms' => $displayRooms->map(function (HyveRoom $room) use ($snapshots): array {
                $snapshot = $snapshots[$room->id] ?? [
                    'available_slots' => collect(),
                    'booked_slots' => collect(),
                    'status' => 'closed',
                ];

                return [
                    'id' => $room->id,
                    'room_name' => $this->bookingDisplayName($room),
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

    /**
     * @return array<int, array{
     *     available_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     booked_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     status: string
     * }>
     */
    private function roomLayoutSnapshots(Collection $rooms, string $bookingDate, ?HyveRoom $sharedTableRepresentative = null): array
    {
        $spaceIdsBySlug = Space::query()
            ->active()
            ->whereIn('slug', $rooms->map(fn (HyveRoom $room): string => $room->mappedSpaceSlug())->unique()->values()->all())
            ->pluck('id', 'slug');

        $conferenceFallbackSpaceIds = $rooms
            ->filter(fn (HyveRoom $room): bool => $room->isConferenceRoom())
            ->mapWithKeys(fn (HyveRoom $room): array => [$room->id => $spaceIdsBySlug->get($room->mappedSpaceSlug())])
            ->filter();

        $bookingsByRoom = $this->roomLayoutBookingRanges($rooms, $bookingDate, $conferenceFallbackSpaceIds);
        $eventsByRoom = $this->roomLayoutCalendarEvents($rooms, $bookingDate);
        $snapshots = [];

        foreach ($rooms as $room) {
            $scheduleRoom = $room->isSharedTable() && $sharedTableRepresentative
                ? $sharedTableRepresentative
                : $room;
            $schedule = $this->scheduleWindowForDate($bookingDate, $scheduleRoom);

            if ($schedule['closed']) {
                $snapshots[$room->id] = [
                    'available_slots' => collect(),
                    'booked_slots' => collect(),
                    'status' => 'closed',
                ];

                continue;
            }

            $dayStart = $schedule['start'];
            $dayEnd = $schedule['end'];
            $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
            $roomEvents = $eventsByRoom[$room->id] ?? collect();

            if ($roomEvents->contains(fn (HyveCalendarEvent $event): bool => $event->isAllDay())) {
                $snapshots[$room->id] = [
                    'available_slots' => collect(),
                    'booked_slots' => collect(),
                    'status' => 'closed',
                ];

                continue;
            }

            $blockedRanges = collect($bookingsByRoom[$room->id] ?? [])
                ->concat(
                    $roomEvents
                        ->reject(fn (HyveCalendarEvent $event): bool => $event->isAllDay())
                        ->map(function (HyveCalendarEvent $event) use ($bookingDate): array {
                            [$start, $end] = $this->dateRange(
                                $bookingDate,
                                (string) $event->start_time,
                                (string) $event->end_time,
                            );

                            return [
                                'start' => $start,
                                'end' => $end,
                            ];
                        })
                );

            $mergedRanges = $this->mergeRanges(
                $blockedRanges
                    ->sortBy(fn (array $range): int => $range['start']->timestamp)
                    ->values()
            );
            $availableSlots = $this->slotWindowsWithPricing(
                $room,
                $bookingDate,
                $this->hourlyWindowsForLayout($mergedRanges, $effectiveStart, $dayEnd, false),
                $sharedTableRepresentative,
            );
            $bookedSlots = $this->hourlyWindowsForLayout($mergedRanges, $effectiveStart, $dayEnd, true);
            $status = 'available';

            if ($mergedRanges->isNotEmpty() && $availableSlots->isNotEmpty()) {
                $status = 'booked';
            }

            if ($availableSlots->isEmpty()) {
                $status = 'occupied';
            }

            $snapshots[$room->id] = [
                'available_slots' => $availableSlots,
                'booked_slots' => $bookedSlots,
                'status' => $status,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  Collection<int, HyveRoom>  $rooms
     * @param  Collection<int, int|string>  $conferenceFallbackSpaceIds
     * @return array<int, array<int, array{start: Carbon, end: Carbon}>>
     */
    private function roomLayoutBookingRanges(Collection $rooms, string $bookingDate, Collection $conferenceFallbackSpaceIds): array
    {
        $roomIds = $rooms->pluck('id')->values();
        $bookingDetailsQuery = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->where(function ($query) use ($roomIds, $conferenceFallbackSpaceIds) {
                $query->whereIn('hyve_room_id', $roomIds);

                if ($conferenceFallbackSpaceIds->isNotEmpty()) {
                    $query->orWhere(function ($fallback) use ($conferenceFallbackSpaceIds) {
                        $fallback->whereNull('hyve_room_id')
                            ->whereIn('space_id', $conferenceFallbackSpaceIds->values()->all());
                    });
                }
            });
        $this->applyBookingDateOverlapConstraint($bookingDetailsQuery, $bookingDate, $bookingDate);

        $bookingDetails = $bookingDetailsQuery->get([
            'hyve_room_id',
            'space_id',
            'booking_date',
            'booking_end_date',
            'start_time',
            'end_time',
        ]);

        $rangesByRoom = [];

        foreach ($rooms as $room) {
            $relevantDetails = $bookingDetails->filter(function (BookingDetail $detail) use ($room, $conferenceFallbackSpaceIds): bool {
                if ((int) $detail->hyve_room_id === (int) $room->id) {
                    return true;
                }

                return $room->isConferenceRoom()
                    && ! $detail->hyve_room_id
                    && (int) $detail->space_id === (int) $conferenceFallbackSpaceIds->get($room->id);
            });

            $rangesByRoom[$room->id] = $relevantDetails
                ->map(function (BookingDetail $detail) use ($bookingDate, $room): array {
                    if ($this->isLongStayFullDayDetail($detail) && $this->detailOccupiesDate($detail, $bookingDate)) {
                        $schedule = $this->scheduleWindowForDate($bookingDate, $room);

                        return [
                            'start' => $schedule['start'],
                            'end' => $schedule['end'],
                        ];
                    }

                    [$start, $end] = $this->dateRange($bookingDate, $detail->start_time, $detail->end_time);

                    return [
                        'start' => $start,
                        'end' => $end,
                    ];
                })
                ->values()
                ->all();
        }

        return $rangesByRoom;
    }

    /**
     * @param  Collection<int, HyveRoom>  $rooms
     * @return array<int, Collection<int, HyveCalendarEvent>>
     */
    private function roomLayoutCalendarEvents(Collection $rooms, string $bookingDate): array
    {
        $targetDate = Carbon::parse($bookingDate);
        $this->calendarService->ensureSystemHolidaysForYears([$targetDate->year, $targetDate->copy()->addYear()->year]);

        $events = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($bookingDate)
            ->where('affects_booking', true)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $eventsByRoom = [];

        foreach ($rooms as $room) {
            $eventsByRoom[$room->id] = $events
                ->filter(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room))
                ->values();
        }

        return $eventsByRoom;
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        return $this->submitBooking($request);
    }

    public function adminStore(StoreBookingRequest $request): RedirectResponse
    {
        return $this->submitBooking($request, true);
    }

    private function submitBooking(StoreBookingRequest $request, bool $adminMode = false): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $isScheduleMode = ($validated['booking_mode'] ?? 'room') === 'schedule';
        $isMonthlyMode = ($validated['booking_mode'] ?? 'room') === 'monthly';
        $bookingItems = [];
        $grandTotal = 0.0;
        $reservedRangesByRoom = [];

        if ($isScheduleMode) {
            $unavailableItems = [];

            foreach ((array) ($validated['selected_schedule_items'] ?? []) as $item) {
                $selectedRoom = HyveRoom::query()->active()->findOrFail((int) $item['hyve_room_id']);
                $room = $this->resolveBookableRoomForSelection(
                    $selectedRoom,
                    $item['booking_date'],
                    $item['start_time'],
                    $item['end_time'],
                    $reservedRangesByRoom,
                    false,
                );

                if (! $room) {
                    $unavailableItems[] = sprintf(
                        '%s - %s - %s to %s',
                        $this->bookingDisplayName($selectedRoom),
                        Carbon::parse($item['booking_date'])->format('F j, Y'),
                        Carbon::createFromFormat('H:i', $item['start_time'])->format('g:i A'),
                        Carbon::createFromFormat('H:i', $item['end_time'])->format('g:i A'),
                    );

                    continue;
                }

                $space = $this->spaceForRoom($room);
                $quote = $this->pricing->quoteForRoom($this->pricingRoomForSelection($selectedRoom), $item['booking_date'], $item['start_time'], $item['end_time']);

                if (! $this->isTimeRangeAvailable($selectedRoom, $item['booking_date'], $item['start_time'], $item['end_time'], false)) {
                    $unavailableItems[] = sprintf(
                        '%s - %s - %s to %s',
                        $this->bookingDisplayName($selectedRoom),
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
                    'selected_room' => $selectedRoom,
                    'space' => $space,
                    'quote' => $quote,
                    'booking_date' => $item['booking_date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                ];
                $grandTotal += (float) $quote['total_amount'];
                $reservedRangesByRoom[$room->id][] = [
                    'start' => Carbon::parse($item['booking_date'].' '.$item['start_time']),
                    'end' => $this->normalizedEndDateTime($item['booking_date'], $item['start_time'], $item['end_time']),
                ];
            }

            if ($unavailableItems !== []) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'selected_schedule_items' => 'These schedule slots are no longer available: '.implode('; ', $unavailableItems).'. Please remove them and choose another time.',
                    ]);
            }
        } elseif ($isMonthlyMode) {
            $selectedRoom = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
            $room = $this->pricingRoomForSelection($selectedRoom);
            $space = $this->spaceForRoom($room);

            if (
                $this->pricing->longStayRequiresUseType($room, $validated['booking_date'], $validated['booking_end_date'])
                && blank($validated['long_stay_use_type'] ?? null)
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'long_stay_use_type' => 'Choose Day Use or Night Use first so HYVE can compute the correct long-stay rate.',
                    ]);
            }

            if (! $this->isLongStayDateRangeAvailable($selectedRoom, $validated['booking_date'], $validated['booking_end_date'], $validated['long_stay_use_type'] ?? null)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'booking_end_date' => 'The selected stay dates are no longer available for this room. Please choose another date range.',
                    ]);
            }

            $quote = $this->pricing->quoteForLongStayRoom(
                $room,
                (string) ($validated['monthly_plan'] ?? ''),
                $validated['booking_date'],
                $validated['booking_end_date'],
                $validated['long_stay_use_type'] ?? null,
            );

            if (! $quote) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'booking_end_date' => 'No active long-stay pricing is configured for the selected room yet.',
                    ]);
            }

            $bookingItems[] = [
                'room' => $room,
                'selected_room' => $selectedRoom,
                'space' => $space,
                'quote' => $quote,
                'booking_date' => $validated['booking_date'],
                'booking_end_date' => $validated['booking_end_date'],
                'start_time' => (string) ($quote['window_start_time'] ?? '00:00'),
                'end_time' => (string) ($quote['window_end_time'] ?? '23:59'),
                'display_start_time' => null,
                'display_end_time' => null,
                'is_monthly' => ($quote['long_stay_use_type'] ?? null) === null,
            ];
            $grandTotal = (float) $quote['total_amount'];
        } else {
            $selectedRoom = HyveRoom::query()->active()->findOrFail($validated['hyve_room_id']);
            $room = $this->resolveBookableRoomForSelection(
                $selectedRoom,
                $validated['booking_date'],
                $validated['start_time'],
                $validated['end_time'],
                $reservedRangesByRoom,
            );
            $room ??= $selectedRoom;
            $space = $this->spaceForRoom($room);
            $quote = $this->pricing->quoteForRoom($this->pricingRoomForSelection($selectedRoom), $validated['booking_date'], $validated['start_time'], $validated['end_time']);

            if (! $this->isTimeRangeAvailable($selectedRoom, $validated['booking_date'], $validated['start_time'], $validated['end_time'])) {
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
                'selected_room' => $selectedRoom,
                'space' => $space,
                'quote' => $quote,
                'booking_date' => $validated['booking_date'],
                'booking_end_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'display_start_time' => $validated['start_time'],
                'display_end_time' => $validated['end_time'],
                'is_monthly' => false,
            ];
            $grandTotal = (float) $quote['total_amount'];
        }

        $customerDownpayment = round((float) $validated['downpayment_amount'], 2);
        $remainingBalance = round(max(0, $grandTotal - $customerDownpayment), 2);
        $isCashWalkIn = $adminMode && (($validated['payment_method'] ?? null) === 'cash');
        $paymentStatus = $isCashWalkIn
            ? ($remainingBalance <= 0 ? 'paid' : 'partially_paid')
            : 'pending_verification';

        $paymentProofPath = $isCashWalkIn
            ? null
            : $request->file('payment_proof')?->store('booking-payments', 'public');
        $paymentProofName = $isCashWalkIn
            ? null
            : $request->file('payment_proof')?->getClientOriginalName();

        $contactDetails = ($user && ! $adminMode)
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
                'booking_type' => $isMonthlyMode ? BookingHeader::TYPE_MONTHLY : BookingHeader::TYPE_GUEST,
            ];

        try {
            $header = $this->database->transaction(function () use ($user, $contactDetails, $validated, $bookingItems, $grandTotal, $paymentProofPath, $paymentProofName, $customerDownpayment, $remainingBalance, $paymentStatus, $adminMode, $isCashWalkIn): BookingHeader {
            $header = null;

            if ($user && ! $adminMode) {
                $header = BookingHeader::query()
                    ->where('booking_type', BookingHeader::TYPE_MEMBER)
                    ->where('user_id', $user->id)
                    ->where('status', BookingHeader::STATUS_PENDING)
                    ->where('payment_status', 'pending_verification')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
            }

            if ($header) {
                $existingNotes = trim((string) ($header->notes ?? ''));
                $newNotes = trim((string) ($validated['notes'] ?? ''));

                $header->update([
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $paymentStatus,
                    'payment_proof_path' => $paymentProofPath ?: $header->payment_proof_path,
                    'payment_proof_name' => $paymentProofName ?: $header->payment_proof_name,
                    'total_amount' => round((float) $header->total_amount + $grandTotal, 2),
                    'downpayment_amount' => round((float) $header->downpayment_amount + $customerDownpayment, 2),
                    'balance_amount' => round((float) $header->balance_amount + $remainingBalance, 2),
                    'notes' => $newNotes !== ''
                        ? ($existingNotes !== '' ? $existingNotes.PHP_EOL.PHP_EOL.$newNotes : $newNotes)
                        : $header->notes,
                    'status' => BookingHeader::STATUS_PENDING,
                    ...$contactDetails,
                ]);
            } else {
                $header = BookingHeader::query()->create([
                    'reference_no' => $this->generateReferenceNumber(),
                    'source' => $adminMode ? BookingHeader::SOURCE_ADMIN : BookingHeader::SOURCE_WEB,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $paymentStatus,
                    'payment_proof_path' => $paymentProofPath,
                    'payment_proof_name' => $paymentProofName,
                    'total_amount' => round($grandTotal, 2),
                    'downpayment_amount' => $customerDownpayment,
                    'balance_amount' => $remainingBalance,
                    'notes' => $validated['notes'] ?? null,
                    'status' => BookingHeader::STATUS_PENDING,
                    ...$contactDetails,
                ]);
            }

            foreach ($bookingItems as $bookingItem) {
                /** @var Space $space */
                $space = $bookingItem['space'];
                /** @var HyveRoom $room */
                $room = $bookingItem['room'];
                /** @var array $quote */
                $quote = $bookingItem['quote'];

                $detail = $header->details()->create([
                    'space_id' => $space->id,
                    'hyve_room_id' => $room->id,
                    'booking_date' => $bookingItem['booking_date'],
                    'booking_end_date' => $bookingItem['booking_end_date'] ?? $bookingItem['booking_date'],
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

                if (Schema::hasTable('booking_activities')) {
                    BookingActivity::query()->create([
                        'booking_header_id' => $header->getKey(),
                        'booking_detail_id' => $detail->getKey(),
                        'actor_user_id' => $user?->getKey(),
                        'event_key' => 'booking_submitted',
                        'event_label' => 'Booking submitted',
                        'reference_no' => $header->reference_no,
                        'customer_name' => $header->customer_name,
                        'room_name' => $room->room_name,
                        'booking_date' => $detail->booking_date,
                        'time_range' => ($bookingItem['is_monthly'] ?? false)
                            ? 'Monthly booking'
                            : Carbon::parse((string) $detail->start_time)->format('g:i A')
                                .' - '
                                .Carbon::parse((string) $detail->end_time)->format('g:i A'),
                        'message' => ($bookingItem['is_monthly'] ?? false)
                            ? 'New monthly booking request submitted for '.$room->room_name.'.'
                            : 'New booking request submitted for '.$room->room_name.'.',
                    ]);
                }
            }

            if ($customerDownpayment > 0) {
                BookingPayment::query()->create([
                    'booking_header_id' => $header->getKey(),
                    'user_id' => $user?->getKey(),
                    'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
                    'amount' => $customerDownpayment,
                    'payment_method' => (string) $validated['payment_method'],
                    'status' => $isCashWalkIn ? BookingPayment::STATUS_APPROVED : BookingPayment::STATUS_PENDING,
                    'payment_proof_path' => $paymentProofPath,
                    'payment_proof_name' => $paymentProofName,
                    'notes' => $adminMode
                        ? 'Initial booking payment submitted from the admin walk-in desk.'
                        : 'Initial booking downpayment submitted during booking.',
                    'paid_at' => now(),
                    'verified_at' => $isCashWalkIn ? now() : null,
                    'verified_by' => $isCashWalkIn ? $user?->getKey() : null,
                ]);
            }

                return $header;
            });
        } catch (\Throwable $exception) {
            Log::error('Booking submission failed.', [
                'booking_mode' => $validated['booking_mode'] ?? 'room',
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }

        if ($adminMode) {
            return redirect()->route('admin.bookings.index')->with(
                'admin_success',
                'Walk-in booking created successfully under reference '.$header->reference_no.'.',
            );
        }

        return redirect()->route('bookings.index')->with(
            'booking_success',
            'Your booking request has been submitted under reference '.$header->reference_no.'. HYVE will contact you soon to confirm the details.',
        );
    }

    private function generateReferenceNumber(): string
    {
        return 'HYVE-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function sharedTableRepresentative(?Collection $rooms = null): ?HyveRoom
    {
        $rooms ??= HyveRoom::query()->active()->orderBy('id')->get();

        return $rooms->first(fn (HyveRoom $room): bool => $room->isSharedTable());
    }

    private function bookingDisplayName(HyveRoom $room): string
    {
        return $room->isSharedTable() ? 'Common Area' : $room->room_name;
    }

    private function pricingRoomForSelection(HyveRoom $room, ?HyveRoom $sharedTableRepresentative = null): HyveRoom
    {
        if (! $room->isSharedTable()) {
            return $room;
        }

        return $sharedTableRepresentative ?: $this->sharedTableRepresentative() ?: $room;
    }

    private function resolvePreselectedRoom(Request $request, Collection $rooms, ?HyveRoom $sharedTableRepresentative): ?HyveRoom
    {
        $requestedRoomId = (int) $request->query('room', 0);

        if ($requestedRoomId > 0) {
            $roomById = $rooms->first(fn (HyveRoom $item): bool => (int) $item->id === $requestedRoomId);

            if ($roomById?->isSharedTable()) {
                return $sharedTableRepresentative ?: $roomById;
            }

            if ($roomById) {
                return $roomById;
            }
        }

        $selectedSpaceSlug = (string) $request->query('space', '');
        $room = $rooms->first(fn (HyveRoom $item): bool => \Illuminate\Support\Str::slug($item->mappedSpaceLabel()) === $selectedSpaceSlug);

        if ($room?->isSharedTable()) {
            return $sharedTableRepresentative ?: $room;
        }

        return $room;
    }

    private function displayBookingRooms(Collection $rooms, ?HyveRoom $sharedTableRepresentative): Collection
    {
        return $rooms
            ->reject(fn (HyveRoom $room): bool => $room->isSharedTable() && $sharedTableRepresentative && (int) $room->id !== (int) $sharedTableRepresentative->id)
            ->values();
    }

    private function resolveBookableRoomForSelection(HyveRoom $selectedRoom, string $bookingDate, string $startTime, string $endTime, array $reservedRangesByRoom = [], bool $enforceMinimumDuration = true): ?HyveRoom
    {
        if (! $selectedRoom->isSharedTable()) {
            return $selectedRoom;
        }

        $tableRooms = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->inRandomOrder()->get();
        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);

        foreach ($tableRooms as $tableRoom) {
            if (! $this->isDirectRoomTimeRangeAvailable($tableRoom, $bookingDate, $startTime, $endTime, $enforceMinimumDuration)) {
                continue;
            }

            $hasReservedOverlap = collect($reservedRangesByRoom[$tableRoom->id] ?? [])->contains(function (array $range) use ($rangeStart, $rangeEnd): bool {
                return $rangeStart->lt($range['end']) && $rangeEnd->gt($range['start']);
            });

            if ($hasReservedOverlap) {
                continue;
            }

            return $tableRoom;
        }

        return null;
    }

    private function normalizedEndDateTime(string $bookingDate, string $startTime, string $endTime): Carbon
    {
        [, $end] = $this->dateRange($bookingDate, $startTime, $endTime);

        return $end;
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
        if ($room->isSharedTable()) {
            return $this->bookingSnapshotForCommonArea($room, $bookingDate, $selectedStartTime);
        }

        if ($this->isFullDayBlockedByCalendarEvent($room, $bookingDate)) {
            return [
                'start_times' => collect(),
                'end_times' => collect(),
                'available_slots' => collect(),
                'booked_slots' => collect(),
                'status' => 'closed',
            ];
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $room);

        if ($schedule['closed']) {
            return [
                'start_times' => collect(),
                'end_times' => collect(),
                'available_slots' => collect(),
                'booked_slots' => collect(),
                'status' => 'closed',
            ];
        }

        $dayStart = $schedule['start'];
        $dayEnd = $schedule['end'];
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
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

    /**
     * @return array{
     *     start_times: Collection<int, array{value: string, label: string}>,
     *     end_times: Collection<int, array{value: string, label: string, range_label: string, duration_label: string}>,
     *     available_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     booked_slots: Collection<int, array{value: string, label: string, end_time: string}>,
     *     status: string
     * }
     */
    private function bookingSnapshotForCommonArea(HyveRoom $room, string $bookingDate, ?string $selectedStartTime = null): array
    {
        $tableRooms = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();
        $capacity = $tableRooms->count();

        if ($capacity < 1 || $this->isFullDayBlockedByCalendarEvent($room, $bookingDate)) {
            return [
                'start_times' => collect(),
                'end_times' => collect(),
                'available_slots' => collect(),
                'booked_slots' => collect(),
                'status' => 'closed',
            ];
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $room);

        if ($schedule['closed']) {
            return [
                'start_times' => collect(),
                'end_times' => collect(),
                'available_slots' => collect(),
                'booked_slots' => collect(),
                'status' => 'closed',
            ];
        }

        $dayStart = $schedule['start'];
        $dayEnd = $schedule['end'];
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
        $occupancy = $this->commonAreaOccupancyBySlot($bookingDate, $tableRooms);
        $calendarBlockedRanges = $this->calendarBlockedRangesForRoom($room, $bookingDate);
        $startTimes = $this->availableStartTimesForCommonArea($effectiveStart, $dayEnd, $minimumDuration, $occupancy, $capacity, $calendarBlockedRanges);
        $endTimes = $selectedStartTime
            ? $this->availableEndTimesForCommonArea($bookingDate, $selectedStartTime, $effectiveStart, $dayEnd, $minimumDuration, $occupancy, $capacity, $calendarBlockedRanges)
            : collect();
        $availableWindows = $this->hourlyWindowsForCommonArea($effectiveStart, $dayEnd, $occupancy, $capacity, $calendarBlockedRanges, false);
        $bookedWindows = $this->hourlyWindowsForCommonArea($effectiveStart, $dayEnd, $occupancy, $capacity, $calendarBlockedRanges, true);
        $status = 'available';

        if ($bookedWindows->isNotEmpty() && $availableWindows->isNotEmpty()) {
            $status = 'booked';
        }

        if ($availableWindows->isEmpty()) {
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

    private function isCommonAreaTimeRangeAvailable(string $bookingDate, string $startTime, string $endTime, bool $enforceMinimumDuration = true): bool
    {
        $tableRooms = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();
        $representative = $this->sharedTableRepresentative($tableRooms);

        if (! $representative) {
            return false;
        }

        if ($this->isFullDayBlockedByCalendarEvent($representative, $bookingDate)) {
            return false;
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $representative);

        if ($schedule['closed']) {
            return false;
        }

        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);
        $effectiveStart = $this->effectiveDayStart($bookingDate, $schedule['start']);
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $intervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($rangeStart->lt($effectiveStart) || $rangeEnd->gt($schedule['end'])) {
            return false;
        }

        if ($enforceMinimumDuration && $rangeStart->diffInMinutes($rangeEnd, true) < $minimumDuration) {
            return false;
        }

        if ($rangeStart->minute % $intervalMinutes !== 0 || $rangeEnd->minute % $intervalMinutes !== 0) {
            return false;
        }

        return $this->commonAreaWindowAvailable(
            $rangeStart,
            $rangeEnd,
            $this->commonAreaOccupancyBySlot($bookingDate, $tableRooms),
            $tableRooms->count(),
            $this->calendarBlockedRangesForRoom($representative, $bookingDate),
        );
    }

    /**
     * @return array<string, int>
     */
    private function commonAreaOccupancyBySlot(string $bookingDate, Collection $tableRooms): array
    {
        $roomIds = $tableRooms->pluck('id')->values();
        $counts = [];

        $details = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereIn('hyve_room_id', $roomIds)
            ->where(function ($query) use ($bookingDate) {
                $this->applyBookingDateOverlapConstraint($query, $bookingDate, $bookingDate);
            })
            ->get(['booking_date', 'booking_end_date', 'start_time', 'end_time']);

        foreach ($details as $detail) {
            if ($this->isLongStayDetail($detail) && $this->detailOccupiesDate($detail, $bookingDate)) {
                $schedule = $this->scheduleWindowForDate($bookingDate, $representative);
                $start = $schedule['start'];
                $end = $schedule['end'];
            } else {
                [$start, $end] = $this->dateRange($bookingDate, $detail->start_time, $detail->end_time);
            }
            $cursor = $start->copy();

            while ($cursor->lt($end)) {
                $counts[$cursor->format('Y-m-d H:i')] = ($counts[$cursor->format('Y-m-d H:i')] ?? 0) + 1;
                $cursor->addMinutes((int) config('hyve.booking.slot_interval_minutes', 30));
            }
        }

        return $counts;
    }

    private function commonAreaWindowAvailable(Carbon $start, Carbon $end, array $occupancy, int $capacity, Collection $calendarBlockedRanges): bool
    {
        if ($capacity < 1) {
            return false;
        }

        foreach ($calendarBlockedRanges as $range) {
            if ($start->lt($range['end']) && $end->gt($range['start'])) {
                return false;
            }
        }

        $cursor = $start->copy();
        $step = (int) config('hyve.booking.slot_interval_minutes', 30);

        while ($cursor->lt($end)) {
            if (($occupancy[$cursor->format('Y-m-d H:i')] ?? 0) >= $capacity) {
                return false;
            }

            $cursor->addMinutes($step);
        }

        return true;
    }

    private function isTimeRangeAvailable(HyveRoom $room, string $bookingDate, string $startTime, string $endTime, bool $enforceMinimumDuration = true): bool
    {
        if ($room->isSharedTable()) {
            return $this->isCommonAreaTimeRangeAvailable($bookingDate, $startTime, $endTime, $enforceMinimumDuration);
        }

        return $this->isDirectRoomTimeRangeAvailable($room, $bookingDate, $startTime, $endTime, $enforceMinimumDuration);
    }

    private function isDirectRoomTimeRangeAvailable(HyveRoom $room, string $bookingDate, string $startTime, string $endTime, bool $enforceMinimumDuration = true): bool
    {
        [$rangeStart, $rangeEnd] = $this->dateRange($bookingDate, $startTime, $endTime);

        if ($this->isFullDayBlockedByCalendarEvent($room, $bookingDate)) {
            return false;
        }

        $schedule = $this->scheduleWindowForDate($bookingDate, $room);

        if ($schedule['closed']) {
            return false;
        }

        $dayStart = $schedule['start'];
        $dayEnd = $schedule['end'];
        $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
        $minimumDuration = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $intervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($rangeStart->lt($effectiveStart) || $rangeEnd->gt($dayEnd)) {
            return false;
        }

        if ($enforceMinimumDuration && $rangeStart->diffInMinutes($rangeEnd, true) < $minimumDuration) {
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

    /**
     * @return array{start: Carbon, end: Carbon, closed: bool}
     */
    private function scheduleWindowForDate(string $bookingDate, ?HyveRoom $room = null): array
    {
        $defaultOpeningTime = (string) config('hyve.booking.opening_time', '00:00');
        $defaultClosingTime = (string) config('hyve.booking.closing_time', '24:00');
        $override = $this->scheduleOverrideForRoom($bookingDate, $room);

        if ($override?->isClosed()) {
            return [
                'start' => $this->slotBoundary($bookingDate, $defaultOpeningTime),
                'end' => $this->slotBoundary($bookingDate, $defaultOpeningTime),
                'closed' => true,
            ];
        }

        $openingTime = $override?->isCustom() && $override->opening_time
            ? (string) $override->opening_time
            : $defaultOpeningTime;
        $closingTime = $override?->isCustom() && $override->closing_time
            ? (string) $override->closing_time
            : $defaultClosingTime;

        return [
            'start' => $this->slotBoundary($bookingDate, $openingTime),
            'end' => $this->slotBoundary($bookingDate, $closingTime),
            'closed' => false,
        ];
    }

    private function scheduleOverrideForRoom(string $bookingDate, ?HyveRoom $room = null): ?HyveScheduleOverride
    {
        return HyveScheduleOverride::query()
            ->when($room, function ($query) use ($room) {
                $query->where(function ($builder) use ($room) {
                    $builder->where('hyve_room_id', $room->getKey())
                        ->orWhereNull('hyve_room_id');
                });
            }, fn ($query) => $query->whereNull('hyve_room_id'))
            ->whereDate('booking_date', $bookingDate)
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->first();
    }

    private function roundUpToNextHalfHour(Carbon $dateTime): Carbon
    {
        $rounded = $dateTime->copy()->setTime(
            (int) $dateTime->format('H'),
            (int) $dateTime->format('i'),
            0,
            0,
        );

        if ($rounded->minute % 30 === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(30 - ($rounded->minute % 30))->setTime(
            (int) $rounded->format('H'),
            (int) $rounded->format('i'),
            0,
            0,
        );
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function fullyBookedDates(HyveRoom $room, int $horizonDays): Collection
    {
        $today = Carbon::today();
        $this->calendarService->ensureSystemHolidaysForYears([$today->year, $today->copy()->addYear()->year]);

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
                'title' => 'Common Area',
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

    private function isLongStayDateRangeAvailable(HyveRoom $room, string $startDate, string $endDate, ?string $useType = null): bool
    {
        $space = $this->spaceForRoom($room);
        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->where(function ($builder) use ($room, $space) {
                $builder->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $builder->orWhere(function ($fallback) use ($space) {
                        $fallback->whereNull('hyve_room_id')
                            ->where('space_id', $space->id);
                    });
                }
            });

        $this->applyBookingDateOverlapConstraint($query, $startDate, $endDate);

        if (! in_array($useType, ['day', 'night'], true)) {
            return ! $query->exists();
        }

        $details = $query->get([
            'booking_date',
            'booking_end_date',
            'start_time',
            'end_time',
        ]);

        if ($details->isEmpty()) {
            return true;
        }

        [$requestStartTime, $requestEndTime] = $this->pricing->longStayWindowForUseType($useType);
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            [$requestedStart, $requestedEnd] = $this->dateRange($cursor->toDateString(), $requestStartTime, $requestEndTime);

            foreach ($details as $detail) {
                if (! $this->detailOccupiesDate($detail, $cursor->toDateString())) {
                    continue;
                }

                if ($this->isLongStayFullDayDetail($detail)) {
                    return false;
                }

                [$blockedStart, $blockedEnd] = $this->dateRange($cursor->toDateString(), $detail->start_time, $detail->end_time);

                if ($requestedStart->lt($blockedEnd) && $requestedEnd->gt($blockedStart)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function effectiveDayStart(string $bookingDate, Carbon $dayStart): Carbon
    {
        if ($bookingDate !== Carbon::today()->toDateString()) {
            return $dayStart->copy();
        }

        $roundedNow = $this->roundUpToNextHalfHour(Carbon::now());

        return $roundedNow->gt($dayStart) ? $roundedNow : $dayStart->copy();
    }

    private function isLongStayDetail(BookingDetail $detail): bool
    {
        $startDate = $this->detailDate($detail->booking_date);
        $endDate = $this->detailDate($detail->booking_end_date) ?: $startDate;

        return $startDate !== null && $endDate !== null && $endDate->ne($startDate);
    }

    private function isLongStayFullDayDetail(BookingDetail $detail): bool
    {
        if (! $this->isLongStayDetail($detail)) {
            return false;
        }

        return (string) $detail->start_time === '00:00'
            && in_array((string) $detail->end_time, ['23:59', '24:00'], true);
    }

    private function detailOccupiesDate(BookingDetail $detail, string $bookingDate): bool
    {
        $targetDate = Carbon::parse($bookingDate)->startOfDay();
        $startDate = $this->detailDate($detail->booking_date)?->startOfDay();
        $endDate = ($this->detailDate($detail->booking_end_date) ?: $startDate)?->startOfDay();

        return $startDate !== null
            && $endDate !== null
            && $startDate->lte($targetDate)
            && $endDate->gte($targetDate);
    }

    private function detailDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function applyBookingDateOverlapConstraint($query, string $startDate, string $endDate): void
    {
        $query
            ->whereDate('booking_date', '<=', $endDate)
            ->where(function ($builder) use ($startDate) {
                $builder
                    ->where(function ($sameDay) use ($startDate) {
                        $sameDay->whereNull('booking_end_date')
                            ->whereDate('booking_date', '>=', $startDate);
                    })
                    ->orWhere(function ($range) use ($startDate) {
                        $range->whereNotNull('booking_end_date')
                            ->whereDate('booking_end_date', '>=', $startDate);
                    });
            });
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function blockedRangesForRoom(HyveRoom $room, string $bookingDate): Collection
    {
        $space = $this->spaceForRoom($room);

        $rangesQuery = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->where(function ($query) use ($room, $space) {
                $query->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $query->orWhere(function ($fallback) use ($space) {
                        $fallback->whereNull('hyve_room_id')
                            ->where('space_id', $space->id);
                    });
                }
            });
        $this->applyBookingDateOverlapConstraint($rangesQuery, $bookingDate, $bookingDate);

        $ranges = $rangesQuery->get(['booking_date', 'booking_end_date', 'start_time', 'end_time'])
            ->map(function (BookingDetail $detail) use ($bookingDate, $room): array {
                if ($this->isLongStayFullDayDetail($detail) && $this->detailOccupiesDate($detail, $bookingDate)) {
                    $schedule = $this->scheduleWindowForDate($bookingDate, $room);

                    return [
                        'start' => $schedule['start'],
                        'end' => $schedule['end'],
                    ];
                }

                [$start, $end] = $this->dateRange($bookingDate, $detail->start_time, $detail->end_time);

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->sortBy(fn (array $range): int => $range['start']->timestamp)
            ->values();

        return $this->mergeRanges($ranges->concat($this->calendarBlockedRangesForRoom($room, $bookingDate)));
    }

    /**
     * @return Collection<int, HyveCalendarEvent>
     */
    private function calendarEventsForRoomOnDate(HyveRoom $room, string $bookingDate): Collection
    {
        $targetDate = Carbon::parse($bookingDate);
        $this->calendarService->ensureSystemHolidaysForYears([$targetDate->year, $targetDate->copy()->addYear()->year]);

        return HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($bookingDate)
            ->where('affects_booking', true)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room))
            ->values();
    }

    private function isFullDayBlockedByCalendarEvent(HyveRoom $room, string $bookingDate): bool
    {
        return $this->calendarEventsForRoomOnDate($room, $bookingDate)
            ->contains(fn (HyveCalendarEvent $event): bool => $event->isAllDay());
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function calendarBlockedRangesForRoom(HyveRoom $room, string $bookingDate): Collection
    {
        return $this->calendarEventsForRoomOnDate($room, $bookingDate)
            ->reject(fn (HyveCalendarEvent $event): bool => $event->isAllDay())
            ->map(function (HyveCalendarEvent $event) use ($bookingDate): array {
                [$start, $end] = $this->dateRange(
                    $bookingDate,
                    (string) $event->start_time,
                    (string) $event->end_time,
                );

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->values();
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

    private function availableStartTimesForCommonArea(Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration, array $occupancy, int $capacity, Collection $calendarBlockedRanges): Collection
    {
        $slotIntervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);
        $startTimes = collect();
        $cursor = $effectiveStart->copy();

        while ($cursor->copy()->addMinutes($minimumDuration)->lte($dayEnd)) {
            $end = $cursor->copy()->addMinutes($minimumDuration);

            if ($this->commonAreaWindowAvailable($cursor, $end, $occupancy, $capacity, $calendarBlockedRanges)) {
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
        $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$selectedStartTime)->seconds(0);

        if ($selectedStartTime === '00:00' && $start->lt($effectiveStart) && $bookingDate !== Carbon::today()->toDateString()) {
            $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' 00:00')->seconds(0);
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
        $endBoundary = $availableUntil->copy()->seconds(0);

        while ($cursor->timestamp <= $endBoundary->timestamp) {
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

    private function availableEndTimesForCommonArea(string $bookingDate, string $selectedStartTime, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration, array $occupancy, int $capacity, Collection $calendarBlockedRanges): Collection
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$selectedStartTime);

        if ($start->lt($effectiveStart)) {
            return collect();
        }

        $slotIntervalMinutes = (int) config('hyve.booking.slot_interval_minutes', 30);
        $cursor = $start->copy()->addMinutes($minimumDuration);
        $endTimes = collect();

        while ($cursor->lte($dayEnd)) {
            if (! $this->commonAreaWindowAvailable($start, $cursor, $occupancy, $capacity, $calendarBlockedRanges)) {
                break;
            }

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
        $slotMinutes = 120;
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

    private function hourlyWindowsForCommonArea(Carbon $effectiveStart, Carbon $dayEnd, array $occupancy, int $capacity, Collection $calendarBlockedRanges, bool $booked): Collection
    {
        $slotMinutes = 120;
        $windows = collect();
        $cursor = $effectiveStart->copy();

        while ($cursor->copy()->addMinutes($slotMinutes)->lte($dayEnd)) {
            $end = $cursor->copy()->addMinutes($slotMinutes);
            $available = $this->commonAreaWindowAvailable($cursor, $end, $occupancy, $capacity, $calendarBlockedRanges);

            if (($booked && ! $available) || (! $booked && $available)) {
                $windows->push($this->windowPayload($cursor, $end));
            }

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

    /**
     * @param  Collection<int, array{value: string, label: string, end_time: string}>  $windows
     * @return Collection<int, array{
     *     value: string,
     *     label: string,
     *     end_time: string,
     *     total_amount: float,
     *     display_amount: string,
     *     breakdown: array<int, array{label: string, amount: float}>
     * }>
     */
    private function slotWindowsWithPricing(HyveRoom $room, string $bookingDate, Collection $windows, ?HyveRoom $sharedTableRepresentative = null): Collection
    {
        $pricingRoom = $this->pricingRoomForSelection($room, $sharedTableRepresentative);

        return $windows
            ->map(function (array $window) use ($pricingRoom, $bookingDate): ?array {
                $quote = $this->pricing->quoteForRoom($pricingRoom, $bookingDate, $window['value'], $window['end_time']);

                if (! $quote) {
                    return null;
                }

                $totalAmount = round((float) ($quote['total_amount'] ?? 0), 2);

                return [
                    ...$window,
                    'total_amount' => $totalAmount,
                    'display_amount' => 'Php '.number_format($totalAmount, 0),
                    'breakdown' => $quote['breakdown'] ?? [],
                ];
            })
            ->filter()
            ->values();
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
