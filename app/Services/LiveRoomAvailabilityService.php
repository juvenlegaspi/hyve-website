<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LiveRoomAvailabilityService
{
    public function __construct(private readonly HyveOperatingScheduleService $operatingSchedule) {}

    /**
     * Return a privacy-safe, member-facing snapshot of current room availability.
     * Shared Common Area tables are combined into one easy-to-read card.
     *
     * @return array{generated_at:string, available_count:int, total_count:int, rooms:array<int, array<string, mixed>>}
     */
    public function memberSnapshot(?Carbon $at = null): array
    {
        $now = ($at ?: now())->copy();
        $today = $now->copy()->startOfDay();
        $rooms = HyveRoom::query()->active()->orderBy('id')->get();
        $details = $this->detailsForDate($today)
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->get()
            ->groupBy('hyve_room_id');
        $overrides = HyveScheduleOverride::query()->whereDate('booking_date', $today)->get();
        $events = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($today->toDateString())
            ->where('affects_booking', true)
            ->where('all_day', true)
            ->get();
        $globallyClosed = $this->operatingSchedule->isGloballyClosed($today);

        $individual = $rooms->map(fn (HyveRoom $room): array => $this->roomSnapshot(
            $room,
            $details->get($room->getKey(), collect()),
            $overrides,
            $events,
            $now,
            $globallyClosed,
        ));

        $sharedRooms = $individual->filter(fn (array $room): bool => $room['is_shared']);
        $displayRooms = $individual->reject(fn (array $room): bool => $room['is_shared']);

        if ($sharedRooms->isNotEmpty()) {
            $availableTables = $sharedRooms->where('status', 'available')->count()
                + $sharedRooms->where('status', 'upcoming')->count();
            $occupiedTables = $sharedRooms->where('status', 'occupied')->count();
            $unavailableTables = $sharedRooms->where('status', 'unavailable')->count();
            $representative = $rooms->first(fn (HyveRoom $room): bool => $room->isSharedTable());
            $commonStatus = $availableTables > 0
                ? 'available'
                : ($occupiedTables > 0 ? 'occupied' : 'unavailable');

            $displayRooms->prepend([
                'id' => $representative?->getKey(),
                'room_name' => 'Common Area',
                'space_label' => 'Shared workspace',
                'status' => $commonStatus,
                'status_label' => $commonStatus === 'available' ? 'Available now' : ($commonStatus === 'occupied' ? 'Fully occupied' : 'Unavailable'),
                'status_note' => $availableTables.' of '.$sharedRooms->count().' tables available now',
                'availability_detail' => $occupiedTables.' occupied'.($unavailableTables > 0 ? ' · '.$unavailableTables.' unavailable' : ''),
                'book_url' => $representative ? route('bookings.index', ['room' => $representative->getKey()]) : route('bookings.index'),
                'is_shared' => true,
                'sort_order' => 0,
            ]);
        }

        $displayRooms = $displayRooms
            ->sortBy('sort_order')
            ->values()
            ->map(function (array $room): array {
                unset($room['sort_order']);

                return $room;
            });

        return [
            'generated_at' => $now->format('g:i:s A'),
            'available_count' => $displayRooms->whereIn('status', ['available', 'upcoming'])->count(),
            'total_count' => $displayRooms->count(),
            'rooms' => $displayRooms->all(),
        ];
    }

    /** @param Collection<int, BookingDetail> $details */
    private function roomSnapshot(HyveRoom $room, Collection $details, Collection $overrides, Collection $events, Carbon $now, bool $globallyClosed): array
    {
        $activeWindow = $details
            ->map(fn (BookingDetail $detail): ?array => $this->sessionWindow($detail, $now))
            ->filter()
            ->first(fn (array $window): bool => $now->betweenIncluded($window['start'], $window['end']));
        $roomOverride = $overrides->where('hyve_room_id', $room->getKey())->first()
            ?? $overrides->whereNull('hyve_room_id')->first();
        $blockingEvent = $events->first(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room));

        if ($activeWindow) {
            $status = 'occupied';
            $label = 'Occupied now';
            $note = 'Currently in use';
            $detail = 'Available after '.$activeWindow['end']->format('g:i A');
        } elseif ($globallyClosed || $roomOverride?->isClosed() || $blockingEvent) {
            $status = 'unavailable';
            $label = 'Unavailable';
            $note = 'Temporarily unavailable today';
            $detail = $globallyClosed
                ? ($this->operatingSchedule->reasonFor($now) ?: 'HYVE is closed today')
                : ($roomOverride?->reason ?: $blockingEvent?->title ?: 'Please choose another room');
        } else {
            $nextWindow = $details
                ->map(fn (BookingDetail $detail): ?array => $this->sessionWindow($detail, $now))
                ->filter(fn (?array $window): bool => $window !== null && $window['start']->gt($now))
                ->sortBy(fn (array $window) => $window['start']->timestamp)
                ->first();

            if ($nextWindow) {
                $status = 'upcoming';
                $label = 'Available now';
                $note = 'Reserved later at '.$nextWindow['start']->format('g:i A');
                $detail = 'Available until '.$nextWindow['start']->format('g:i A');
            } else {
                $status = 'available';
                $label = 'Available now';
                $note = 'Open for a new booking';
                $detail = 'No active reservation right now';
            }
        }

        return [
            'id' => $room->getKey(),
            'room_name' => $room->room_name,
            'space_label' => $room->mappedSpaceLabel(),
            'status' => $status,
            'status_label' => $label,
            'status_note' => $note,
            'availability_detail' => $detail,
            'book_url' => route('bookings.index', ['room' => $room->getKey()]),
            'is_shared' => $room->isSharedTable(),
            'sort_order' => $this->sortOrder($room),
        ];
    }

    private function sessionWindow(BookingDetail $detail, Carbon $now): ?array
    {
        $bookingStart = $detail->booking_date?->copy()->startOfDay();
        $bookingEnd = ($detail->booking_end_date ?: $detail->booking_date)?->copy()->startOfDay();

        if (! $bookingStart || ! $bookingEnd) {
            return null;
        }

        $overnight = (string) $detail->end_time <= (string) $detail->start_time;
        $sessionDates = collect([$now->copy()->startOfDay()]);

        if ($overnight) {
            $sessionDates->push($now->copy()->subDay()->startOfDay());
        }

        $windows = $sessionDates
            ->filter(fn (Carbon $sessionDate): bool => $sessionDate->gte($bookingStart) && $sessionDate->lte($bookingEnd))
            ->map(function (Carbon $sessionDate) use ($detail): array {
                $start = Carbon::parse($sessionDate->toDateString().' '.$detail->start_time);
                $end = Carbon::parse($sessionDate->toDateString().' '.$detail->end_time);

                if ($end->lte($start)) {
                    $end->addDay();
                }

                return ['start' => $start, 'end' => $end];
            })
            ->values();

        return $windows->first(fn (array $window): bool => $now->betweenIncluded($window['start'], $window['end']))
            ?? $windows->first(fn (array $window): bool => $window['start']->gte($now));
    }

    private function detailsForDate(Carbon $date)
    {
        $value = $date->toDateString();
        $earliestValue = $date->copy()->subDay()->toDateString();

        return BookingDetail::query()
            ->whereDate('booking_date', '<=', $value)
            ->where(function ($query) use ($earliestValue) {
                $query->where(function ($sameDay) use ($earliestValue) {
                    $sameDay->whereNull('booking_end_date')->whereDate('booking_date', '>=', $earliestValue);
                })->orWhere(function ($range) use ($earliestValue) {
                    $range->whereNotNull('booking_end_date')->whereDate('booking_end_date', '>=', $earliestValue);
                });
            });
    }

    private function sortOrder(HyveRoom $room): int
    {
        if ($room->isSharedTable()) {
            return 0;
        }

        if (preg_match('/^Room\s+(\d+)$/', $room->room_name, $matches) === 1) {
            return 100 + (int) $matches[1];
        }

        return $room->isConferenceRoom() ? 1_000 : 500 + (int) $room->getKey();
    }
}
