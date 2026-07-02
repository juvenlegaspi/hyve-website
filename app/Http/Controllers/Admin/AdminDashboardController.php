<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
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
        $rooms = HyveRoom::query()->active()->orderBy('id')->get();
        $todayDetails = BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom'])
            ->whereDate('booking_date', $today)
            ->orderBy('start_time')
            ->get();

        $recentBookings = BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $bookingsToday = $todayDetails->count();
        $yesterdayCount = BookingDetail::query()->whereDate('booking_date', $today->copy()->subDay())->count();
        $revenueToday = (float) $todayDetails->sum(fn (BookingDetail $detail) => (float) ($detail->subtotal ?? 0));
        $paidToday = BookingHeader::query()->whereDate('created_at', $today)->where('balance_amount', '<=', 0)->count();
        $pendingToday = BookingHeader::query()->whereDate('created_at', $today)->where('status', BookingHeader::STATUS_PENDING)->count();
        $memberCount = User::query()->where('role', User::ROLE_MEMBER)->where('status', 0)->count();
        $newMembersThisWeek = User::query()
            ->where('role', User::ROLE_MEMBER)
            ->whereBetween('created_at', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()])
            ->count();

        $roomStatus = $rooms->map(function (HyveRoom $room) use ($todayDetails, $now): array {
            $activeDetail = $todayDetails->first(function (BookingDetail $detail) use ($room, $now) {
                if ((int) $detail->hyve_room_id !== (int) $room->id) {
                    return false;
                }

                $start = Carbon::createFromFormat('Y-m-d H:i:s', $detail->booking_date->format('Y-m-d').' '.$detail->start_time);
                $end = Carbon::createFromFormat('Y-m-d H:i:s', $detail->booking_date->format('Y-m-d').' '.$detail->end_time);

                if ($end->lte($start)) {
                    $end->addDay();
                }

                return $now->betweenIncluded($start, $end);
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

            return [
                'room_name' => $room->room_name,
                'status' => 'available',
                'status_label' => 'Available',
                'status_note' => 'Ready for walk-in or online booking',
                'until' => null,
            ];
        })->values();

        $occupiedCount = collect($roomStatus)->where('status', 'occupied')->count();
        $utilization = $rooms->count() > 0
            ? (int) round(($occupiedCount / $rooms->count()) * 100)
            : 0;

        return view('admin.dashboard', [
            'meta' => [
                'title' => 'Admin Dashboard | HYVE Workspace',
                'description' => 'Monitor bookings, members, rooms, and payments from the HYVE admin panel.',
            ],
            'adminUser' => $request->user(),
            'bookingsToday' => $bookingsToday,
            'bookingsDelta' => $bookingsToday - $yesterdayCount,
            'revenueToday' => $revenueToday,
            'paidToday' => $paidToday,
            'pendingToday' => $pendingToday,
            'memberCount' => $memberCount,
            'newMembersThisWeek' => $newMembersThisWeek,
            'utilization' => $utilization,
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
}
