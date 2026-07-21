<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = Carbon::today();
        $now = now();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();
        $rooms = HyveRoom::query()->active()->orderBy('id')->get();
        $todayDetails = $this->bookingDetailsForDate($today)
            ->with(['bookingHeader', 'hyveRoom'])
            ->orderBy('start_time')
            ->get();
        $monthDetails = $this->bookingDetailsForRange($monthStart, $monthEnd)
            ->with(['bookingHeader', 'hyveRoom'])
            ->get();
        $recentBookings = $monthDetails->sortByDesc('created_at')->take(30)->values();

        $bookingsThisMonth = $monthDetails->count();
        $previousMonthCount = $this->bookingDetailsForRange($previousMonthStart, $previousMonthEnd)->count();
        $revenueThisMonth = (float) BookingPayment::query()
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->whereBetween('verified_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $verifiedThisMonth = BookingPayment::query()
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->whereBetween('verified_at', [$monthStart, $monthEnd])
            ->count();
        $pendingThisMonth = BookingPayment::query()
            ->where('status', BookingPayment::STATUS_PENDING)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->count();
        $memberCount = User::query()->where('role', User::ROLE_MEMBER)->where('status', 0)->count();
        $newMembersThisMonth = User::query()
            ->where('role', User::ROLE_MEMBER)
            ->where('status', 0)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();
        $newMembersPreviousMonth = User::query()
            ->where('role', User::ROLE_MEMBER)
            ->where('status', 0)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $todayOverrides = HyveScheduleOverride::query()
            ->whereDate('booking_date', $today)
            ->get();
        $todayEvents = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->forDate($today->toDateString())
            ->where('affects_booking', true)
            ->where('all_day', true)
            ->get();

        $roomStatus = $rooms->map(function (HyveRoom $room) use ($todayDetails, $todayOverrides, $todayEvents, $now): array {
            $activeDetail = $todayDetails->first(function (BookingDetail $detail) use ($room, $now) {
                if ((int) $detail->hyve_room_id !== (int) $room->id) {
                    return false;
                }

                return (string) $detail->status === BookingDetail::STATUS_CONFIRMED
                    && $this->detailIsActiveAt($detail, $now);
            });

            if ($activeDetail) {
                return [
                    'room_name' => $room->room_name,
                    'status' => 'occupied',
                    'status_label' => 'Occupied',
                    'status_note' => $activeDetail->bookingHeader?->customer_name ?: 'Guest booking',
                    'until' => 'Until '.$this->displayTime((string) $activeDetail->end_time),
                ];
            }

            $roomOverride = $todayOverrides
                ->where('hyve_room_id', $room->getKey())
                ->first()
                ?? $todayOverrides->whereNull('hyve_room_id')->first();
            $blockingEvent = $todayEvents
                ->first(fn (HyveCalendarEvent $event): bool => $event->appliesToRoom($room));

            if ($roomOverride?->isClosed() || $blockingEvent) {
                return [
                    'room_name' => $room->room_name,
                    'status' => 'maintenance',
                    'status_label' => 'Maintenance',
                    'status_note' => $roomOverride?->reason ?: $blockingEvent?->title ?: 'Temporarily unavailable',
                    'until' => null,
                ];
            }

            return [
                'room_name' => $room->room_name,
                'status' => 'available',
                'status_label' => 'Available',
                'status_note' => 'Ready for walk-in or online booking',
                'until' => null,
            ];
        })->values();

        $occupiedCount = collect($roomStatus)->where('status', 'occupied')->count();
        $bookedMinutesThisMonth = $this->bookedMinutesWithinRange(
            $monthDetails->where('status', BookingDetail::STATUS_CONFIRMED)->values(),
            $monthStart,
            $monthEnd,
        );
        $availableMinutesThisMonth = $rooms->count() * $monthStart->daysInMonth * 24 * 60;
        $utilization = $availableMinutesThisMonth > 0
            ? round(min(100, ($bookedMinutesThisMonth / $availableMinutesThisMonth) * 100), 1)
            : 0.0;

        return view('admin.dashboard', [
            'meta' => [
                'title' => 'Admin Dashboard | HYVE Workspace',
                'description' => 'Monitor bookings, members, rooms, and payments from the HYVE admin panel.',
            ],
            'adminUser' => $request->user(),
            'bookingsThisMonth' => $bookingsThisMonth,
            'bookingsDelta' => $bookingsThisMonth - $previousMonthCount,
            'revenueThisMonth' => $revenueThisMonth,
            'verifiedThisMonth' => $verifiedThisMonth,
            'pendingThisMonth' => $pendingThisMonth,
            'memberCount' => $memberCount,
            'newMembersThisMonth' => $newMembersThisMonth,
            'membersDelta' => $newMembersThisMonth - $newMembersPreviousMonth,
            'utilization' => $utilization,
            'bookedHoursThisMonth' => round($bookedMinutesThisMonth / 60, 1),
            'occupiedCount' => $occupiedCount,
            'roomCount' => $rooms->count(),
            'recentBookings' => $recentBookings,
            'roomStatus' => $roomStatus,
        ]);
    }

    private function displayTime(string $value): string
    {
        $format = strlen($value) === 5 ? 'H:i' : 'H:i:s';

        return Carbon::createFromFormat($format, $value)->format('g:i A');
    }

    private function bookingDetailsForDate(Carbon $date)
    {
        return $this->bookingDetailsForRange($date, $date);
    }

    private function bookingDetailsForRange(Carbon $startDate, Carbon $endDate)
    {
        $startValue = $startDate->toDateString();
        $endValue = $endDate->toDateString();

        return BookingDetail::query()
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->whereDate('booking_date', '<=', $endValue)
            ->where(function ($query) use ($startValue) {
                $query
                    ->where(function ($sameDay) use ($startValue) {
                        $sameDay->whereNull('booking_end_date')
                            ->whereDate('booking_date', '>=', $startValue);
                    })
                    ->orWhere(function ($range) use ($startValue) {
                        $range->whereNotNull('booking_end_date')
                            ->whereDate('booking_end_date', '>=', $startValue);
                    });
            });
    }

    private function bookedMinutesWithinRange($details, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEndExclusive = $rangeEnd->copy()->addDay()->startOfDay();
        $minutes = 0;

        foreach ($details as $detail) {
            $bookingStart = $detail->booking_date?->copy()->startOfDay();
            $bookingEnd = ($detail->booking_end_date ?: $detail->booking_date)?->copy()->startOfDay();

            if (! $bookingStart || ! $bookingEnd) {
                continue;
            }

            $cursor = $bookingStart->greaterThan($rangeStart) ? $bookingStart->copy() : $rangeStart->copy();
            $lastDate = $bookingEnd->lessThan($rangeEnd) ? $bookingEnd->copy() : $rangeEnd->copy()->startOfDay();

            while ($cursor->lte($lastDate)) {
                $sessionStart = Carbon::parse($cursor->toDateString().' '.$detail->start_time);
                $sessionEnd = Carbon::parse($cursor->toDateString().' '.$detail->end_time);

                if ((string) $detail->end_time === '23:59') {
                    $sessionEnd->addMinute();
                } elseif ($sessionEnd->lte($sessionStart)) {
                    $sessionEnd->addDay();
                }

                $clippedStart = $sessionStart->greaterThan($rangeStart) ? $sessionStart : $rangeStart;
                $clippedEnd = $sessionEnd->lessThan($rangeEndExclusive) ? $sessionEnd : $rangeEndExclusive;

                if ($clippedEnd->gt($clippedStart)) {
                    $minutes += (int) $clippedStart->diffInMinutes($clippedEnd);
                }

                $cursor->addDay();
            }
        }

        return $minutes;
    }

    private function detailIsActiveAt(BookingDetail $detail, Carbon $now): bool
    {
        $bookingStartDate = $detail->booking_date?->copy()->startOfDay();
        $bookingEndDate = ($detail->booking_end_date ?: $detail->booking_date)?->copy()->startOfDay();

        if (! $bookingStartDate || ! $bookingEndDate || $now->lt($bookingStartDate) || $now->gt($bookingEndDate->copy()->endOfDay())) {
            return false;
        }

        $datesToCheck = [$now->copy()->startOfDay()];

        if ((string) $detail->end_time <= (string) $detail->start_time) {
            $datesToCheck[] = $now->copy()->subDay()->startOfDay();
        }

        foreach ($datesToCheck as $sessionDate) {
            if ($sessionDate->lt($bookingStartDate) || $sessionDate->gt($bookingEndDate)) {
                continue;
            }

            $start = Carbon::parse($sessionDate->toDateString().' '.$detail->start_time);
            $end = Carbon::parse($sessionDate->toDateString().' '.$detail->end_time);

            if ($end->lte($start)) {
                $end->addDay();
            }

            if ($now->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
