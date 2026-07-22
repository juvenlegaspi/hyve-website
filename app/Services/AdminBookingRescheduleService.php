<?php

namespace App\Services;

use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use App\Models\Space;
use App\Support\HyvePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBookingRescheduleService
{
    public function __construct(private readonly HyvePricing $pricing) {}

    public function canReschedule(BookingDetail $detail): bool
    {
        $detail->loadMissing('bookingHeader');
        $headerStatus = strtolower((string) ($detail->bookingHeader?->status ?? BookingHeader::STATUS_PENDING));
        $detailStatus = strtolower((string) ($detail->status ?? BookingDetail::STATUS_PENDING));
        $progress = strtolower((string) ($detail->progress_status ?? BookingDetail::PROGRESS_SCHEDULED));

        if (! in_array($headerStatus, ['pending', 'confirmed'], true)
            || ! in_array($detailStatus, ['pending', 'confirmed'], true)
            || in_array($progress, [BookingDetail::PROGRESS_IN_PROGRESS, BookingDetail::PROGRESS_COMPLETED], true)
            || $detail->actual_start_at !== null) {
            return false;
        }

        $startAt = $this->scheduledStart($detail);

        return $startAt !== null && $startAt->isFuture();
    }

    public function isLongStay(BookingDetail $detail): bool
    {
        return in_array((string) $detail->charge_period, ['daily', 'weekly', 'monthly'], true);
    }

    /** @return Collection<int, HyveRoom> */
    public function selectableRooms(): Collection
    {
        $rooms = HyveRoom::query()->active()->orderBy('id')->get();
        $representative = $rooms->first(fn (HyveRoom $room): bool => $room->isSharedTable());

        return $rooms
            ->reject(fn (HyveRoom $room): bool => $room->isSharedTable() && $representative && $room->isNot($representative))
            ->values();
    }

    public function selectedRoomId(BookingDetail $detail, Collection $selectableRooms): ?int
    {
        if (! $detail->hyveRoom?->isSharedTable()) {
            return $detail->hyve_room_id ? (int) $detail->hyve_room_id : null;
        }

        return (int) optional($selectableRooms->first(fn (HyveRoom $room): bool => $room->isSharedTable()))?->id;
    }

    /** @return array{start_times: array<int, array<string, mixed>>, end_times: array<int, array<string, mixed>>, selected_start: string|null} */
    public function availableSlots(BookingDetail $detail, int $roomId, string $bookingDate, ?string $selectedStart = null): array
    {
        $detail->loadMissing(['bookingHeader', 'hyveRoom']);

        if (! $this->canReschedule($detail) || $this->isLongStay($detail)) {
            throw ValidationException::withMessages([
                'booking_date' => 'Time slots are no longer available for this booking.',
            ]);
        }

        $selectedRoom = HyveRoom::query()->active()->find($roomId);

        if (! $selectedRoom) {
            throw ValidationException::withMessages(['hyve_room_id' => 'The selected room is no longer available.']);
        }

        $scheduleRoom = $selectedRoom->isSharedTable()
            ? HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->first() ?? $selectedRoom
            : $selectedRoom;
        $schedule = $this->scheduleWindow($bookingDate, $scheduleRoom);
        $interval = (int) config('hyve.booking.slot_interval_minutes', 30);
        $minimum = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $isAvailable = $this->slotAvailabilityChecker($selectedRoom, $bookingDate, $detail->getKey());
        $startTimes = collect();

        if (! $schedule['closed']) {
            $cursor = $schedule['start']->copy();
            $lastStart = $schedule['end']->copy()->subMinutes($minimum);

            while ($cursor->lte($lastStart)) {
                $value = $this->timeValue($cursor, $bookingDate);
                $isPast = ! $cursor->isFuture();
                $minimumEnd = $cursor->copy()->addMinutes($minimum);
                $available = ! $isPast && $isAvailable($cursor, $minimumEnd);

                $startTimes->push([
                    'value' => $value,
                    'label' => $cursor->format('g:i A'),
                    'available' => $available,
                    'reason' => $isPast ? 'Past' : ($available ? 'Available' : 'Booked'),
                ]);
                $cursor->addMinutes($interval);
            }
        }

        $endTimes = collect();

        if ($selectedStart !== null && $selectedStart !== '') {
            $start = $this->slotBoundary($bookingDate, $selectedStart);
            $cursor = $start->copy()->addMinutes($minimum);

            while ($cursor->lte($schedule['end'])) {
                $value = $this->timeValue($cursor, $bookingDate);
                $available = $start->isFuture() && $isAvailable($start, $cursor);

                $endTimes->push([
                    'value' => $value,
                    'label' => $cursor->format('g:i A').($cursor->toDateString() !== $bookingDate ? ' next day' : ''),
                    'available' => $available,
                    'reason' => $available ? 'Available' : 'Booked',
                    'duration_minutes' => $start->diffInMinutes($cursor),
                ]);
                $cursor->addMinutes($interval);
            }
        }

        return [
            'start_times' => $startTimes->all(),
            'end_times' => $endTimes->all(),
            'selected_start' => $selectedStart,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(BookingDetail $detail, array $data): array
    {
        $detail->loadMissing(['bookingHeader.payments', 'hyveRoom', 'space']);

        if (! $this->canReschedule($detail)) {
            throw ValidationException::withMessages([
                'booking_date' => 'This booking can no longer be rescheduled because its scheduled start has already arrived or the booking has started.',
            ]);
        }

        $isLongStay = $this->isLongStay($detail);
        $selectedRoom = HyveRoom::query()->active()->find((int) ($data['hyve_room_id'] ?? 0));

        if (! $selectedRoom) {
            throw ValidationException::withMessages(['hyve_room_id' => 'The selected room is no longer available.']);
        }

        $bookingDate = (string) ($data['booking_date'] ?? '');
        $startTime = $isLongStay ? '00:00' : (string) ($data['start_time'] ?? '');
        $endTime = $isLongStay ? '23:59' : (string) ($data['end_time'] ?? '');
        $bookingEndDate = $isLongStay
            ? (string) ($data['booking_end_date'] ?? '')
            : $this->dateRange($bookingDate, $startTime, $endTime)[1]->toDateString();
        $useType = filled($data['long_stay_use_type'] ?? null) ? (string) $data['long_stay_use_type'] : null;
        $newStart = $isLongStay
            ? Carbon::parse($bookingDate)->startOfDay()
            : $this->slotBoundary($bookingDate, $startTime);

        if (! $newStart->isFuture()) {
            throw ValidationException::withMessages([
                $isLongStay ? 'booking_date' : 'start_time' => 'The new schedule must start in the future.',
            ]);
        }

        $bookableRoom = $this->resolveBookableRoom(
            $selectedRoom,
            $bookingDate,
            $bookingEndDate,
            $startTime,
            $endTime,
            $isLongStay,
            $detail->getKey(),
        );

        if (! $bookableRoom) {
            throw ValidationException::withMessages([
                $isLongStay ? 'booking_end_date' : 'end_time' => 'The selected room, date, or time conflicts with another booking or a blocked schedule.',
            ]);
        }

        $pricingRoom = $selectedRoom->isSharedTable()
            ? HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->first() ?? $selectedRoom
            : $selectedRoom;
        $quote = $isLongStay
            ? $this->pricing->quoteForLongStayRoom($pricingRoom, '', $bookingDate, $bookingEndDate, $useType)
            : $this->pricing->quoteForRoom($pricingRoom, $bookingDate, $startTime, $endTime);

        if (! $quote) {
            throw ValidationException::withMessages([
                $isLongStay ? 'booking_end_date' : 'end_time' => 'HYVE could not calculate a rate for the selected schedule.',
            ]);
        }

        $header = $detail->bookingHeader;
        $oldLineTotal = round((float) ($detail->subtotal ?? 0), 2);
        $newLineTotal = round((float) ($quote['total_amount'] ?? 0), 2);
        $newGrossTotal = round(max(0, (float) $header->total_amount - $oldLineTotal + $newLineTotal), 2);
        $discount = $header->discountSnapshotFor($newGrossTotal);
        $newEffectiveTotal = round((float) $discount['discounted_total_amount'], 2);
        $approvedTotal = round((float) $header->payments->where('status', BookingPayment::STATUS_APPROVED)->sum('amount'), 2);
        $newBalance = round(max(0, $newEffectiveTotal - $approvedTotal), 2);
        $overpayment = round(max(0, $approvedTotal - $newEffectiveTotal), 2);

        return [
            'is_long_stay' => $isLongStay,
            'selected_room' => $selectedRoom,
            'bookable_room' => $bookableRoom,
            'booking_date' => $bookingDate,
            'booking_end_date' => $bookingEndDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'long_stay_use_type' => $useType,
            'quote' => $quote,
            'old_line_total' => $oldLineTotal,
            'new_line_total' => $newLineTotal,
            'price_difference' => round($newLineTotal - $oldLineTotal, 2),
            'new_gross_total' => $newGrossTotal,
            'new_effective_total' => $newEffectiveTotal,
            'approved_total' => $approvedTotal,
            'new_balance' => $newBalance,
            'overpayment' => $overpayment,
            'requires_price_confirmation' => abs($newLineTotal - $oldLineTotal) >= 0.01,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function reschedule(BookingDetail $detail, array $data, ?int $actorUserId): array
    {
        return DB::transaction(function () use ($detail, $data, $actorUserId): array {
            $lockedDetail = BookingDetail::query()->lockForUpdate()->findOrFail($detail->getKey());
            $lockedDetail->load(['bookingHeader.payments', 'hyveRoom', 'space']);
            BookingHeader::query()->whereKey($lockedDetail->booking_header_id)->lockForUpdate()->firstOrFail();
            $selectedRoomLock = HyveRoom::query()
                ->active()
                ->whereKey((int) ($data['hyve_room_id'] ?? 0))
                ->lockForUpdate()
                ->first();

            if ($selectedRoomLock?->isSharedTable()) {
                HyveRoom::query()
                    ->active()
                    ->where('room_name', 'like', 'Table %')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $preview = $this->preview($lockedDetail, $data);

            if ($preview['requires_price_confirmation'] && ! filter_var($data['confirm_price_change'] ?? false, FILTER_VALIDATE_BOOL)) {
                $difference = (float) $preview['price_difference'];
                $direction = $difference > 0 ? 'increase' : 'decrease';

                throw ValidationException::withMessages([
                    'confirm_price_change' => sprintf(
                        'The booking line will %s by Php %s. Review the updated totals and confirm the price change.',
                        $direction,
                        number_format(abs($difference), 2),
                    ),
                ]);
            }

            /** @var BookingHeader $header */
            $header = $lockedDetail->bookingHeader;
            /** @var HyveRoom $bookableRoom */
            $bookableRoom = $preview['bookable_room'];
            $space = $this->spaceForRoom($bookableRoom);
            $oldSchedule = $this->scheduleLabel($lockedDetail);
            $oldRoom = $lockedDetail->hyveRoom?->isSharedTable()
                ? 'Common Area'
                : ($lockedDetail->hyveRoom?->room_name ?? $lockedDetail->space?->name ?? 'Room');
            $quote = $preview['quote'];

            $lockedDetail->update([
                'space_id' => $space->id,
                'hyve_room_id' => $bookableRoom->id,
                'booking_date' => $preview['booking_date'],
                'booking_end_date' => $preview['booking_end_date'],
                'start_time' => $preview['start_time'],
                'end_time' => $preview['end_time'],
                'charge_period' => (string) $quote['charge_period'],
                'duration_hours' => (float) ($quote['duration_hours'] ?? 0),
                'billed_hours' => (float) ($quote['billed_hours'] ?? 0),
                'rate_name' => (string) $quote['rate_name'],
                'rate_amount' => (float) ($quote['succeeding_hour_rate'] ?? $quote['minimum_rate'] ?? 0),
                'subtotal' => (float) $preview['new_line_total'],
                'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
                'actual_start_at' => null,
                'actual_end_at' => null,
                'end_reminder_sent_at' => null,
            ]);

            $pendingPayments = $header->payments->contains(fn (BookingPayment $payment): bool => $payment->status === BookingPayment::STATUS_PENDING);
            $paymentStatus = $pendingPayments
                ? 'pending_balance_verification'
                : ((float) $preview['approved_total'] >= (float) $preview['new_effective_total'] && (float) $preview['new_effective_total'] > 0
                    ? 'paid'
                    : ((float) $preview['approved_total'] > 0 ? 'partially_paid' : (string) ($header->payment_status ?: 'pending_verification')));

            $header->update([
                'total_amount' => $preview['new_gross_total'],
                'discount_amount' => $header->discountSnapshotFor((float) $preview['new_gross_total'])['discount_amount'],
                'discounted_total_amount' => $preview['new_effective_total'],
                'downpayment_amount' => $preview['approved_total'],
                'balance_amount' => $preview['new_balance'],
                'payment_status' => $paymentStatus,
            ]);

            $freshDetail = $lockedDetail->fresh(['hyveRoom', 'space']);
            $newRoom = $freshDetail->hyveRoom?->isSharedTable() ? 'Common Area' : ($freshDetail->hyveRoom?->room_name ?? 'Room');
            $newSchedule = $this->scheduleLabel($freshDetail);

            BookingActivity::query()->create([
                'booking_header_id' => $header->getKey(),
                'booking_detail_id' => $freshDetail->getKey(),
                'actor_user_id' => $actorUserId,
                'event_key' => 'booking_rescheduled_by_admin',
                'event_label' => 'Booking rescheduled by admin',
                'reference_no' => $header->reference_no,
                'customer_name' => $header->customer_name,
                'room_name' => $newRoom,
                'booking_date' => $freshDetail->booking_date,
                'time_range' => $newSchedule,
                'message' => sprintf('Admin moved %s | %s to %s | %s.', $oldRoom, $oldSchedule, $newRoom, $newSchedule),
            ]);

            return [
                ...$preview,
                'header' => $header->fresh(['details', 'payments', 'wifiVoucher']),
                'detail' => $freshDetail,
                'old_room' => $oldRoom,
                'old_schedule' => $oldSchedule,
                'new_room' => $newRoom,
                'new_schedule' => $newSchedule,
            ];
        });
    }

    private function scheduledStart(BookingDetail $detail): ?Carbon
    {
        if (! $detail->booking_date) {
            return null;
        }

        return $this->isLongStay($detail)
            ? $detail->booking_date->copy()->startOfDay()
            : $this->slotBoundary($detail->booking_date->toDateString(), substr((string) $detail->start_time, 0, 5));
    }

    private function resolveBookableRoom(HyveRoom $selectedRoom, string $startDate, string $endDate, string $startTime, string $endTime, bool $isLongStay, int $ignoreDetailId): ?HyveRoom
    {
        if (! $selectedRoom->isSharedTable()) {
            $available = $isLongStay
                ? $this->isLongStayAvailable($selectedRoom, $startDate, $endDate, $ignoreDetailId)
                : $this->isDirectTimeAvailable($selectedRoom, $startDate, $startTime, $endTime, $ignoreDetailId);

            return $available ? $selectedRoom : null;
        }

        $tables = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();

        if (! $isLongStay && ! $this->isCommonAreaTimeAvailable($tables, $startDate, $startTime, $endTime, $ignoreDetailId)) {
            return null;
        }

        return $tables->first(function (HyveRoom $room) use ($startDate, $endDate, $startTime, $endTime, $isLongStay, $ignoreDetailId): bool {
            return $isLongStay
                ? $this->isLongStayAvailable($room, $startDate, $endDate, $ignoreDetailId)
                : $this->isDirectTimeAvailable($room, $startDate, $startTime, $endTime, $ignoreDetailId);
        });
    }

    /** @return \Closure(Carbon, Carbon): bool */
    private function slotAvailabilityChecker(HyveRoom $selectedRoom, string $date, int $ignoreDetailId): \Closure
    {
        $interval = (int) config('hyve.booking.slot_interval_minutes', 30);
        $minimum = (int) config('hyve.booking.minimum_duration_minutes', 120);

        if ($selectedRoom->isSharedTable()) {
            $tables = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();
            $representative = $tables->first() ?? $selectedRoom;
            $schedule = $this->scheduleWindow($date, $representative);
            $calendarRanges = $this->calendarBlockedRanges($representative, $date);
            $query = BookingDetail::query()
                ->whereIn('status', $this->blockedStatuses())
                ->whereIn('hyve_room_id', $tables->pluck('id'))
                ->whereKeyNot($ignoreDetailId);
            $this->applyDateOverlap($query, $date, $date);
            $occupancy = [];

            foreach ($query->get(['booking_date', 'booking_end_date', 'start_time', 'end_time', 'charge_period']) as $detail) {
                if ($this->isLongStay($detail)) {
                    $start = $schedule['start']->copy();
                    $end = $schedule['end']->copy();
                } else {
                    [$start, $end] = $this->dateRange($date, (string) $detail->start_time, (string) $detail->end_time);
                }

                while ($start->lt($end)) {
                    $key = $start->format('Y-m-d H:i');
                    $occupancy[$key] = ($occupancy[$key] ?? 0) + 1;
                    $start->addMinutes($interval);
                }
            }

            return function (Carbon $start, Carbon $end) use ($schedule, $calendarRanges, $occupancy, $tables, $interval, $minimum): bool {
                if ($schedule['closed'] || $start->lt($schedule['start']) || $end->gt($schedule['end'])
                    || $start->diffInMinutes($end, true) < $minimum) {
                    return false;
                }

                if ($calendarRanges->contains(fn (array $range): bool => $start->lt($range['end']) && $end->gt($range['start']))) {
                    return false;
                }

                $cursor = $start->copy();

                while ($cursor->lt($end)) {
                    if (($occupancy[$cursor->format('Y-m-d H:i')] ?? 0) >= $tables->count()) {
                        return false;
                    }

                    $cursor->addMinutes($interval);
                }

                return true;
            };
        }

        $schedule = $this->scheduleWindow($date, $selectedRoom);
        $space = $this->spaceForRoom($selectedRoom);
        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereKeyNot($ignoreDetailId)
            ->where(function ($builder) use ($selectedRoom, $space) {
                $builder->where('hyve_room_id', $selectedRoom->id);

                if ($selectedRoom->isConferenceRoom()) {
                    $builder->orWhere(fn ($fallback) => $fallback->whereNull('hyve_room_id')->where('space_id', $space->id));
                }
            });
        $this->applyDateOverlap($query, $date, $date);
        $blockedRanges = $query->get(['booking_date', 'booking_end_date', 'start_time', 'end_time', 'charge_period'])
            ->map(function (BookingDetail $detail) use ($date, $schedule): array {
                if ($this->isLongStay($detail)) {
                    return ['start' => $schedule['start'], 'end' => $schedule['end']];
                }

                [$start, $end] = $this->dateRange($date, (string) $detail->start_time, (string) $detail->end_time);

                return compact('start', 'end');
            })
            ->concat($this->calendarBlockedRanges($selectedRoom, $date));

        return function (Carbon $start, Carbon $end) use ($schedule, $blockedRanges, $minimum): bool {
            if ($schedule['closed'] || $start->lt($schedule['start']) || $end->gt($schedule['end'])
                || $start->diffInMinutes($end, true) < $minimum) {
                return false;
            }

            return ! $blockedRanges->contains(fn (array $range): bool => $start->lt($range['end']) && $end->gt($range['start']));
        };
    }

    private function isLongStayAvailable(HyveRoom $room, string $startDate, string $endDate, int $ignoreDetailId): bool
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $lastDate = Carbon::parse($endDate)->startOfDay();

        while ($cursor->lte($lastDate)) {
            $date = $cursor->toDateString();
            $schedule = $this->scheduleWindow($date, $room);

            if ($schedule['closed'] || $this->calendarEvents($room, $date)->isNotEmpty()) {
                return false;
            }

            $cursor->addDay();
        }

        $space = $this->spaceForRoom($room);
        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereKeyNot($ignoreDetailId)
            ->where(function ($builder) use ($room, $space) {
                $builder->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $builder->orWhere(fn ($fallback) => $fallback->whereNull('hyve_room_id')->where('space_id', $space->id));
                }
            });
        $this->applyDateOverlap($query, $startDate, $endDate);

        return ! $query->exists();
    }

    private function isDirectTimeAvailable(HyveRoom $room, string $date, string $startTime, string $endTime, int $ignoreDetailId): bool
    {
        [$requestedStart, $requestedEnd] = $this->dateRange($date, $startTime, $endTime);
        $schedule = $this->scheduleWindow($date, $room);
        $minimum = (int) config('hyve.booking.minimum_duration_minutes', 120);
        $interval = (int) config('hyve.booking.slot_interval_minutes', 30);

        if ($schedule['closed'] || $requestedStart->lt($schedule['start']) || $requestedEnd->gt($schedule['end'])
            || $requestedStart->diffInMinutes($requestedEnd, true) < $minimum
            || $requestedStart->minute % $interval !== 0 || $requestedEnd->minute % $interval !== 0) {
            return false;
        }

        $space = $this->spaceForRoom($room);
        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereKeyNot($ignoreDetailId)
            ->where(function ($builder) use ($room, $space) {
                $builder->where('hyve_room_id', $room->id);

                if ($room->isConferenceRoom()) {
                    $builder->orWhere(fn ($fallback) => $fallback->whereNull('hyve_room_id')->where('space_id', $space->id));
                }
            });
        $this->applyDateOverlap($query, $date, $date);

        $blocked = $query->get(['booking_date', 'booking_end_date', 'start_time', 'end_time', 'charge_period'])
            ->map(function (BookingDetail $detail) use ($date, $room): array {
                if ($this->isLongStay($detail)) {
                    $window = $this->scheduleWindow($date, $room);

                    return ['start' => $window['start'], 'end' => $window['end']];
                }

                [$start, $end] = $this->dateRange($date, (string) $detail->start_time, (string) $detail->end_time);

                return compact('start', 'end');
            })
            ->concat($this->calendarBlockedRanges($room, $date));

        return ! $blocked->contains(fn (array $range): bool => $requestedStart->lt($range['end']) && $requestedEnd->gt($range['start']));
    }

    /** @param Collection<int, HyveRoom> $tables */
    private function isCommonAreaTimeAvailable(Collection $tables, string $date, string $startTime, string $endTime, int $ignoreDetailId): bool
    {
        $representative = $tables->first();

        if (! $representative) {
            return false;
        }

        [$requestedStart, $requestedEnd] = $this->dateRange($date, $startTime, $endTime);
        $schedule = $this->scheduleWindow($date, $representative);
        $interval = (int) config('hyve.booking.slot_interval_minutes', 30);
        $minimum = (int) config('hyve.booking.minimum_duration_minutes', 120);

        if ($schedule['closed'] || $requestedStart->lt($schedule['start']) || $requestedEnd->gt($schedule['end'])
            || $requestedStart->diffInMinutes($requestedEnd, true) < $minimum
            || $requestedStart->minute % $interval !== 0 || $requestedEnd->minute % $interval !== 0) {
            return false;
        }

        foreach ($this->calendarBlockedRanges($representative, $date) as $range) {
            if ($requestedStart->lt($range['end']) && $requestedEnd->gt($range['start'])) {
                return false;
            }
        }

        $query = BookingDetail::query()
            ->whereIn('status', $this->blockedStatuses())
            ->whereIn('hyve_room_id', $tables->pluck('id'))
            ->whereKeyNot($ignoreDetailId);
        $this->applyDateOverlap($query, $date, $date);
        $occupancy = [];

        foreach ($query->get(['booking_date', 'booking_end_date', 'start_time', 'end_time', 'charge_period']) as $detail) {
            if ($this->isLongStay($detail)) {
                $start = $schedule['start'];
                $end = $schedule['end'];
            } else {
                [$start, $end] = $this->dateRange($date, (string) $detail->start_time, (string) $detail->end_time);
            }

            while ($start->lt($end)) {
                $key = $start->format('Y-m-d H:i');
                $occupancy[$key] = ($occupancy[$key] ?? 0) + 1;
                $start->addMinutes($interval);
            }
        }

        $cursor = $requestedStart->copy();

        while ($cursor->lt($requestedEnd)) {
            if (($occupancy[$cursor->format('Y-m-d H:i')] ?? 0) >= $tables->count()) {
                return false;
            }

            $cursor->addMinutes($interval);
        }

        return true;
    }

    private function calendarEvents(HyveRoom $room, string $date): Collection
    {
        return HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($date)
            ->where('affects_booking', true)
            ->get()
            ->filter(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room))
            ->values();
    }

    private function calendarBlockedRanges(HyveRoom $room, string $date): Collection
    {
        $events = $this->calendarEvents($room, $date);

        if ($events->contains(fn (HyveCalendarEvent $event): bool => $event->isAllDay())) {
            $schedule = $this->scheduleWindow($date, $room);

            return collect([['start' => $schedule['start'], 'end' => $schedule['end']]]);
        }

        return $events->map(function (HyveCalendarEvent $event) use ($date): array {
            [$start, $end] = $this->dateRange($date, (string) $event->start_time, (string) $event->end_time);

            return compact('start', 'end');
        });
    }

    /** @return array{start: Carbon, end: Carbon, closed: bool} */
    private function scheduleWindow(string $date, HyveRoom $room): array
    {
        $opening = (string) config('hyve.booking.opening_time', '00:00');
        $closing = (string) config('hyve.booking.closing_time', '24:00');
        $override = HyveScheduleOverride::query()
            ->where(fn ($query) => $query->where('hyve_room_id', $room->id)->orWhereNull('hyve_room_id'))
            ->whereDate('booking_date', $date)
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->first();

        if ($override?->isClosed()) {
            $boundary = $this->slotBoundary($date, $opening);

            return ['start' => $boundary, 'end' => $boundary->copy(), 'closed' => true];
        }

        if ($override?->isCustom()) {
            $opening = $override->opening_time ? substr((string) $override->opening_time, 0, 5) : $opening;
            $closing = $override->closing_time ? substr((string) $override->closing_time, 0, 5) : $closing;
        }

        return ['start' => $this->slotBoundary($date, $opening), 'end' => $this->slotBoundary($date, $closing), 'closed' => false];
    }

    private function applyDateOverlap($query, string $startDate, string $endDate): void
    {
        $query->whereDate('booking_date', '<=', $endDate)
            ->where(function ($builder) use ($startDate) {
                $builder->where(fn ($sameDay) => $sameDay->whereNull('booking_end_date')->whereDate('booking_date', '>=', $startDate))
                    ->orWhere(fn ($range) => $range->whereNotNull('booking_end_date')->whereDate('booking_end_date', '>=', $startDate));
            });
    }

    private function spaceForRoom(HyveRoom $room): Space
    {
        return Space::query()->active()->where('slug', $room->mappedSpaceSlug())->firstOrFail();
    }

    private function slotBoundary(string $date, string $time): Carbon
    {
        $time = substr($time, 0, 5);

        return $time === '24:00'
            ? Carbon::createFromFormat('Y-m-d H:i', $date.' 00:00')->addDay()
            : Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time);
    }

    private function timeValue(Carbon $time, string $bookingDate): string
    {
        if ($time->toDateString() !== $bookingDate && $time->format('H:i') === '00:00') {
            return '24:00';
        }

        return $time->format('H:i');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(string $date, string $startTime, string $endTime): array
    {
        $start = $this->slotBoundary($date, $startTime);
        $end = $this->slotBoundary($date, $endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function blockedStatuses(): array
    {
        return (array) config('hyve.booking.blocked_statuses', ['pending', 'confirmed']);
    }

    private function scheduleLabel(BookingDetail $detail): string
    {
        if ($this->isLongStay($detail)) {
            $start = optional($detail->booking_date)?->format('M j, Y') ?? '--';
            $end = optional($detail->booking_end_date ?: $detail->booking_date)?->format('M j, Y') ?? '--';

            return $start === $end ? $start : $start.' - '.$end;
        }

        return optional($detail->booking_date)?->format('M j, Y').' | '
            .Carbon::parse((string) $detail->start_time)->format('g:i A').' - '
            .Carbon::parse((string) $detail->end_time)->format('g:i A');
    }
}
