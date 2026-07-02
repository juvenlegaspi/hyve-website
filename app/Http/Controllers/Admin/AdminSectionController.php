<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRate;
use App\Models\HyveRoom;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AdminSectionController extends Controller
{
    public function show(Request $request, string $section): View|StreamedResponse
    {
        if ($section === 'rooms') {
            $rooms = HyveRoom::query()
                ->orderBy('id')
                ->get();

            $rateCards = HyveRate::query()
                ->active()
                ->get()
                ->keyBy('space_slug');

            return view('admin.rooms.index', [
                'meta' => [
                    'title' => 'Rooms | HYVE Admin',
                    'description' => 'Review and manage the active HYVE room inventory.',
                ],
                'adminUser' => $request->user(),
                'rooms' => $rooms,
                'rateCards' => $rateCards,
            ]);
        }

        if ($section === 'calendar-events') {
            $upcomingItems = collect([
                [
                    'title' => 'Conference Room Maintenance',
                    'details' => 'June 10, 2026, 2:00 PM - 4:00 PM - Blocked - Conference Room - AC and projector check',
                    'type' => 'blocked',
                    'tag' => 'Blocked',
                ],
                [
                    'title' => 'Independence Day',
                    'details' => 'June 12, 2026 - PH Holiday',
                    'type' => 'holiday',
                    'tag' => 'Holiday',
                ],
                [
                    'title' => 'HYVE Team Workshop',
                    'details' => 'June 21, 2026 - Custom event - Rooms 1-4 reserved',
                    'type' => 'custom',
                    'tag' => 'Custom',
                ],
                [
                    'title' => 'Room 7 Maintenance',
                    'details' => 'June 15-17, 2026 - Blocked - Room 7 - Network and furniture refresh',
                    'type' => 'blocked',
                    'tag' => 'Blocked',
                ],
                [
                    'title' => 'Ninoy Aquino Day',
                    'details' => 'August 21, 2026 - PH Holiday',
                    'type' => 'holiday',
                    'tag' => 'Holiday',
                ],
                [
                    'title' => 'National Heroes Day',
                    'details' => 'August 31, 2026 - PH Holiday',
                    'type' => 'holiday',
                    'tag' => 'Holiday',
                ],
            ]);

            return view('admin.calendar-events.index', [
                'meta' => [
                    'title' => 'Calendar & Events | HYVE Admin',
                    'description' => 'Track PH holidays, custom events, and blocked booking dates from one clean admin workspace.',
                ],
                'adminUser' => $request->user(),
                'upcomingItems' => $upcomingItems,
            ]);
        }

        if ($section === 'reports') {
            [$dateFrom, $dateTo, $selectedRange] = $this->reportDateRange($request);
            [$previousDateFrom, $previousDateTo] = $this->previousReportDateRange($dateFrom, $dateTo);

            $bookingHeaders = BookingHeader::query()
                ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

            $bookingDetails = BookingDetail::query()
                ->with(['hyveRoom', 'space'])
                ->whereBetween('booking_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

            $payments = BookingPayment::query()
                ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

            $summary = [
                'bookings_total' => (clone $bookingHeaders)->count(),
                'approved_bookings' => (clone $bookingHeaders)->where('status', 'confirmed')->count(),
                'pending_bookings' => (clone $bookingHeaders)->where('status', BookingHeader::STATUS_PENDING)->count(),
                'rejected_bookings' => (clone $bookingHeaders)->where('status', 'cancelled')->count(),
                'gross_booking_value' => (float) (clone $bookingHeaders)->sum('total_amount'),
                'approved_payments_total' => (float) (clone $payments)->where('status', BookingPayment::STATUS_APPROVED)->sum('amount'),
                'pending_payments_total' => (float) (clone $payments)->where('status', BookingPayment::STATUS_PENDING)->sum('amount'),
                'approved_payments_count' => (clone $payments)->where('status', BookingPayment::STATUS_APPROVED)->count(),
                'pending_payments_count' => (clone $payments)->where('status', BookingPayment::STATUS_PENDING)->count(),
                'member_bookings' => (clone $bookingHeaders)->where('booking_type', BookingHeader::TYPE_MEMBER)->count(),
                'guest_bookings' => (clone $bookingHeaders)->where('booking_type', BookingHeader::TYPE_GUEST)->count(),
            ];

            $previousSummary = [
                'bookings_total' => (int) BookingHeader::query()
                    ->whereBetween('created_at', [$previousDateFrom->copy()->startOfDay(), $previousDateTo->copy()->endOfDay()])
                    ->count(),
                'approved_payments_total' => (float) BookingPayment::query()
                    ->whereBetween('created_at', [$previousDateFrom->copy()->startOfDay(), $previousDateTo->copy()->endOfDay()])
                    ->where('status', BookingPayment::STATUS_APPROVED)
                    ->sum('amount'),
                'gross_booking_value' => (float) BookingHeader::query()
                    ->whereBetween('created_at', [$previousDateFrom->copy()->startOfDay(), $previousDateTo->copy()->endOfDay()])
                    ->sum('total_amount'),
            ];

            $occupancy = [
                'booked_lines_total' => (clone $bookingDetails)->count(),
                'confirmed_lines' => (clone $bookingDetails)->where('status', BookingDetail::STATUS_CONFIRMED)->count(),
                'completed_lines' => (clone $bookingDetails)->where('progress_status', BookingDetail::PROGRESS_COMPLETED)->count(),
                'in_progress_lines' => (clone $bookingDetails)->where('progress_status', BookingDetail::PROGRESS_IN_PROGRESS)->count(),
                'unique_rooms_used' => (clone $bookingDetails)->distinct('hyve_room_id')->count('hyve_room_id'),
            ];

            $previousOccupancyBooked = (int) BookingDetail::query()
                ->whereBetween('booking_date', [$previousDateFrom->toDateString(), $previousDateTo->toDateString()])
                ->count();
            $previousOccupancyConfirmed = (int) BookingDetail::query()
                ->whereBetween('booking_date', [$previousDateFrom->toDateString(), $previousDateTo->toDateString()])
                ->where('status', BookingDetail::STATUS_CONFIRMED)
                ->count();

            $utilizationRate = $occupancy['booked_lines_total'] > 0
                ? round(($occupancy['confirmed_lines'] / $occupancy['booked_lines_total']) * 100, 1)
                : 0.0;
            $previousUtilizationRate = $previousOccupancyBooked > 0
                ? round(($previousOccupancyConfirmed / $previousOccupancyBooked) * 100, 1)
                : 0.0;

            $averageBookingValue = $summary['bookings_total'] > 0
                ? round($summary['gross_booking_value'] / $summary['bookings_total'], 2)
                : 0.0;
            $previousAverageBookingValue = $previousSummary['bookings_total'] > 0
                ? round($previousSummary['gross_booking_value'] / $previousSummary['bookings_total'], 2)
                : 0.0;

            $bookingDetailRows = $bookingDetails->get();
            $bookingDetailsByRoom = $bookingDetailRows->groupBy(function (BookingDetail $detail): string {
                return $detail->hyveRoom?->room_name
                    ?? $detail->space?->name
                    ?? 'Room';
            });

            $topRooms = $bookingDetailsByRoom
                ->map(function ($group, string $roomName): array {
                    return [
                        'room' => $roomName,
                        'bookings' => $group->count(),
                        'revenue' => (float) $group->sum(fn (BookingDetail $detail): float => (float) ($detail->subtotal ?? 0)),
                    ];
                })
                ->sortByDesc('bookings')
                ->take(6)
                ->values();

            $roomPerformance = $bookingDetailsByRoom
                ->map(function ($group, string $roomName): array {
                    $bookingsCount = $group->count();
                    $confirmedCount = $group->where('status', BookingDetail::STATUS_CONFIRMED)->count();

                    return [
                        'room' => $roomName,
                        'bookings' => $bookingsCount,
                        'revenue' => (float) $group->sum(fn (BookingDetail $detail): float => (float) ($detail->subtotal ?? 0)),
                        'utilization_rate' => $bookingsCount > 0 ? round(($confirmedCount / $bookingsCount) * 100, 1) : 0.0,
                    ];
                })
                ->sortByDesc('revenue')
                ->values();

            $topMembers = BookingHeader::query()
                ->with('user')
                ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->whereNotNull('user_id')
                ->get()
                ->groupBy('user_id')
                ->map(function ($group, $userId): array {
                    $user = $group->first()?->user;

                    return [
                        'user_id' => $userId,
                        'name' => $user?->name ?: ($group->first()?->customer_name ?? 'Member'),
                        'email' => $user?->email ?: ($group->first()?->email ?? '--'),
                        'bookings' => $group->count(),
                        'revenue' => (float) $group->sum(fn (BookingHeader $header): float => (float) ($header->total_amount ?? 0)),
                    ];
                })
                ->sortByDesc('revenue')
                ->take(6)
                ->values();

            $paymentBreakdown = BookingPayment::query()
                ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->get()
                ->groupBy('payment_method')
                ->map(function ($group, string $method): array {
                    return [
                        'method' => ucfirst(str_replace('_', ' ', $method)),
                        'amount' => (float) $group->sum('amount'),
                    ];
                })
                ->sortByDesc('amount')
                ->values();

            $approvedPaymentsTotal = max(0.01, (float) $summary['approved_payments_total']);
            $paymentBreakdown = $paymentBreakdown
                ->map(function (array $item) use ($approvedPaymentsTotal): array {
                    $item['percentage'] = round(($item['amount'] / $approvedPaymentsTotal) * 100, 1);

                    return $item;
                })
                ->values();

            $shiftCollections = BookingPayment::query()
                ->with('verifiedByUser')
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->whereNotNull('verified_at')
                ->whereBetween('verified_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->get()
                ->groupBy('verified_by')
                ->map(function ($group, $userId): array {
                    $user = $group->first()?->verifiedByUser;
                    $cashPayments = $group->where('payment_method', 'cash');
                    $onlinePayments = $group->filter(fn (BookingPayment $payment): bool => in_array((string) $payment->payment_method, ['gcash', 'bank_transfer'], true));

                    return [
                        'user_id' => $userId,
                        'name' => $user?->name ?: 'Admin #'.$userId,
                        'role' => ucfirst(str_replace('_', ' ', (string) ($user?->role ?? 'admin'))),
                        'cash_total' => round((float) $cashPayments->sum('amount'), 2),
                        'cash_count' => $cashPayments->count(),
                        'online_total' => round((float) $onlinePayments->sum('amount'), 2),
                        'online_count' => $onlinePayments->count(),
                        'grand_total' => round((float) $group->sum('amount'), 2),
                        'transactions_count' => $group->count(),
                        'last_verified_at' => optional($group->max('verified_at'))?->format('M j, Y g:i A') ?? '--',
                    ];
                })
                ->sortByDesc('grand_total')
                ->values();

            $shiftTransactionRows = BookingPayment::query()
                ->with(['bookingHeader', 'verifiedByUser'])
                ->where('status', BookingPayment::STATUS_APPROVED)
                ->whereNotNull('verified_at')
                ->whereBetween('verified_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->orderByDesc('verified_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (BookingPayment $payment): array {
                    $header = $payment->bookingHeader;
                    $verifiedBy = $payment->verifiedByUser;
                    $method = (string) ($payment->payment_method ?? '--');

                    return [
                        'payment_id' => (int) $payment->id,
                        'booking_id' => (int) ($payment->booking_header_id ?? 0),
                        'reference_no' => (string) ($header?->reference_no ?? '--'),
                        'customer_name' => (string) ($header?->customer_name ?? 'Customer'),
                        'payment_type' => ucfirst((string) ($payment->payment_type ?? '--')),
                        'payment_method' => ucfirst(str_replace('_', ' ', $method)),
                        'channel' => $method === 'cash' ? 'Cash' : 'Online',
                        'amount' => round((float) ($payment->amount ?? 0), 2),
                        'verified_by' => (string) ($verifiedBy?->name ?? 'Admin'),
                        'verified_role' => ucfirst(str_replace('_', ' ', (string) ($verifiedBy?->role ?? 'admin'))),
                        'verified_at' => optional($payment->verified_at)?->format('M j, Y g:i A') ?? '--',
                        'paid_at' => optional($payment->paid_at)?->format('M j, Y g:i A') ?? '--',
                    ];
                })
                ->values();

            $dailyBookings = $this->dailyBookingSummaries($dateFrom, $dateTo);
            $dailyPayments = $this->dailyPaymentSummaries($dateFrom, $dateTo);

            $chartSeries = $this->reportChartSeries($dateFrom, $dateTo, $dailyBookings, $dailyPayments);
            $peakBookingHours = $this->peakBookingHours($dateFrom, $dateTo);

            $currentMonthStart = $dateTo->copy()->startOfMonth();
            $currentMonthEnd = $dateTo->copy()->endOfMonth();
            $previousMonthStart = $currentMonthStart->copy()->subMonth()->startOfMonth();
            $previousMonthEnd = $currentMonthStart->copy()->subMonth()->endOfMonth();

            $monthlySummary = [
                'current' => [
                    'label' => $currentMonthStart->format('F Y'),
                    'bookings' => BookingHeader::query()
                        ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                        ->count(),
                    'sales' => (float) BookingPayment::query()
                        ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                        ->where('status', BookingPayment::STATUS_APPROVED)
                        ->sum('amount'),
                ],
                'previous' => [
                    'label' => $previousMonthStart->format('F Y'),
                    'bookings' => BookingHeader::query()
                        ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
                        ->count(),
                    'sales' => (float) BookingPayment::query()
                        ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
                        ->where('status', BookingPayment::STATUS_APPROVED)
                        ->sum('amount'),
                ],
            ];

            if ((string) $request->query('export') === 'shift_excel') {
                return $this->downloadShiftCollectionsExcel(
                    $dateFrom,
                    $dateTo,
                    $shiftCollections->all(),
                    $shiftTransactionRows->all(),
                );
            }

            if ((string) $request->query('export') === 'csv') {
                return $this->downloadReportsCsv(
                    $dateFrom,
                    $dateTo,
                    $summary,
                    $occupancy,
                    $topRooms->all(),
                    $topMembers->all(),
                    $shiftCollections->all(),
                    $dailyBookings->all(),
                    $dailyPayments->all(),
                    $monthlySummary,
                );
            }

            return view('admin.reports.index', [
                'meta' => [
                    'title' => 'Reports | HYVE Admin',
                'description' => 'Review bookings, payments, occupancy signals, and top-performing rooms from one report dashboard.',
                ],
                'adminUser' => $request->user(),
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'selectedRange' => $selectedRange,
                'summary' => $summary,
                'kpis' => [
                    [
                        'label' => 'Revenue',
                        'value' => 'Php '.number_format($summary['approved_payments_total'], 2),
                        'delta' => $this->percentDelta($summary['approved_payments_total'], $previousSummary['approved_payments_total']),
                    ],
                    [
                        'label' => 'Bookings',
                        'value' => number_format($summary['bookings_total']),
                        'delta' => $this->percentDelta($summary['bookings_total'], $previousSummary['bookings_total']),
                    ],
                    [
                        'label' => 'Utilization rate',
                        'value' => number_format($utilizationRate, 1).'%',
                        'delta' => $this->percentDelta($utilizationRate, $previousUtilizationRate),
                    ],
                    [
                        'label' => 'Avg booking value',
                        'value' => 'Php '.number_format($averageBookingValue, 2),
                        'delta' => $this->percentDelta($averageBookingValue, $previousAverageBookingValue),
                    ],
                ],
                'occupancy' => $occupancy,
                'monthlySummary' => $monthlySummary,
                'topRooms' => $topRooms,
                'roomPerformance' => $roomPerformance,
                'topMembers' => $topMembers,
                'paymentBreakdown' => $paymentBreakdown,
                'shiftCollections' => $shiftCollections,
                'dailyBookings' => $dailyBookings,
                'dailyPayments' => $dailyPayments,
                'chartSeries' => $chartSeries,
                'peakBookingHours' => $peakBookingHours,
            ]);
        }

        $sections = $this->sections();

        abort_unless(array_key_exists($section, $sections), 404);

        $page = $sections[$section];

        return view('admin.section', [
            'meta' => [
                'title' => $page['title'].' | HYVE Admin',
                'description' => $page['description'],
            ],
            'adminUser' => $request->user(),
            'pageTitle' => $page['title'],
            'pageEyebrow' => $page['eyebrow'],
            'pageDescription' => $page['description'],
            'pageNote' => $page['note'],
        ]);
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:string}
     */
    private function reportDateRange(Request $request): array
    {
        $selectedRange = (string) $request->query('range', 'month');
        $today = now();

        if ($request->query('date_from') || $request->query('date_to')) {
            $dateTo = $request->query('date_to')
                ? Carbon::parse((string) $request->query('date_to'))
                : $today;

            $dateFrom = $request->query('date_from')
                ? Carbon::parse((string) $request->query('date_from'))
                : $dateTo->copy()->subDays(29);

            $selectedRange = 'custom';
        } else {
            [$dateFrom, $dateTo] = match ($selectedRange) {
                'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
                'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
                'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
                default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            };
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy(), $dateFrom->copy()];
        }

        return [$dateFrom->startOfDay(), $dateTo->endOfDay(), $selectedRange];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function previousReportDateRange(Carbon $dateFrom, Carbon $dateTo): array
    {
        $days = max(1, $dateFrom->diffInDays($dateTo) + 1);
        $previousEnd = $dateFrom->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return [$previousStart, $previousEnd];
    }

    /**
     * @return array{value:string,tone:string}
     */
    private function percentDelta(float|int $current, float|int $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if (abs($previous) < 0.0001) {
            if (abs($current) < 0.0001) {
                return ['value' => '0%', 'tone' => 'neutral'];
            }

            return ['value' => '+100%', 'tone' => 'up'];
        }

        $delta = (($current - $previous) / abs($previous)) * 100;
        $rounded = round($delta, 1);

        return [
            'value' => ($rounded > 0 ? '+' : '').number_format($rounded, 1).'%',
            'tone' => $rounded > 0 ? 'up' : ($rounded < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function reportChartSeries(Carbon $dateFrom, Carbon $dateTo, $dailyBookings, $dailyPayments)
    {
        $days = max(1, $dateFrom->diffInDays($dateTo) + 1);
        $bucketCount = min(4, $days);
        $bucketSize = (int) ceil($days / $bucketCount);
        $series = collect();
        $bookingCountsByDate = collect($dailyBookings)->keyBy('date_key');
        $paymentTotalsByDate = collect($dailyPayments)->keyBy('date_key');

        for ($index = 0; $index < $bucketCount; $index++) {
            $bucketStart = $dateFrom->copy()->addDays($index * $bucketSize)->startOfDay();
            $bucketEnd = $bucketStart->copy()->addDays($bucketSize - 1)->endOfDay();

            if ($bucketEnd->gt($dateTo)) {
                $bucketEnd = $dateTo->copy()->endOfDay();
            }

            $bucketRevenue = 0.0;
            $bucketBookings = 0;

            foreach ($bucketStart->copy()->daysUntil($bucketEnd->copy()->addDay()) as $day) {
                $dateKey = $day->toDateString();
                $bucketBookings += (int) ($bookingCountsByDate->get($dateKey)['count'] ?? 0);
                $bucketRevenue += (float) ($paymentTotalsByDate->get($dateKey)['approved_total'] ?? 0);
            }

            $series->push([
                'label' => $bucketStart->format('M j').($bucketStart->isSameDay($bucketEnd) ? '' : '-'.$bucketEnd->format('j')),
                'revenue' => round($bucketRevenue, 2),
                'bookings' => $bucketBookings,
            ]);
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peakBookingHours(Carbon $dateFrom, Carbon $dateTo): array
    {
        $dayLabels = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        $hourRange = range(6, 19);
        $details = BookingDetail::query()
            ->whereBetween('booking_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get(['booking_date', 'start_time']);

        $countsBySlot = [];

        foreach ($details as $detail) {
            if (! $detail->booking_date || ! $detail->start_time) {
                continue;
            }

            $dayIndex = (int) $detail->booking_date->dayOfWeek;
            $startHour = (int) Carbon::parse((string) $detail->start_time)->format('G');

            if (! in_array($startHour, $hourRange, true)) {
                continue;
            }

            $slotKey = $dayIndex.'-'.$startHour;
            $countsBySlot[$slotKey] = ($countsBySlot[$slotKey] ?? 0) + 1;
        }

        $matrix = [];
        $maxCount = 1;

        foreach ($hourRange as $hour) {
            $row = [
                'hour' => Carbon::createFromTime($hour)->format('gA'),
                'cells' => [],
            ];

            foreach ($dayLabels as $dayIndex => $label) {
                $count = (int) ($countsBySlot[$dayIndex.'-'.$hour] ?? 0);

                $maxCount = max($maxCount, $count);

                $row['cells'][] = [
                    'day' => $label,
                    'count' => $count,
                ];
            }

            $matrix[] = $row;
        }

        return array_map(function (array $row) use ($maxCount): array {
            $row['cells'] = array_map(function (array $cell) use ($maxCount): array {
                $cell['intensity'] = $maxCount > 0 ? round(($cell['count'] / $maxCount) * 100, 1) : 0;

                return $cell;
            }, $row['cells']);

            return $row;
        }, $matrix);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function dailyBookingSummaries(Carbon $dateFrom, Carbon $dateTo)
    {
        $rows = BookingHeader::query()
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw(
                "DATE(created_at) as report_date, COUNT(*) as total_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count",
                ['confirmed']
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('report_date');

        $days = collect();

        foreach ($dateFrom->copy()->daysUntil($dateTo->copy()->addDay()) as $day) {
            $dateKey = $day->toDateString();
            $row = $rows->get($dateKey);

            $days->push([
                'date_key' => $dateKey,
                'date' => $day->format('M j'),
                'count' => (int) ($row->total_count ?? 0),
                'approved' => (int) ($row->approved_count ?? 0),
            ]);
        }

        return $days;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function dailyPaymentSummaries(Carbon $dateFrom, Carbon $dateTo)
    {
        $rows = BookingPayment::query()
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw(
                "DATE(created_at) as report_date,
                SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as approved_total,
                SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as pending_total",
                [BookingPayment::STATUS_APPROVED, BookingPayment::STATUS_PENDING]
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('report_date');

        $days = collect();

        foreach ($dateFrom->copy()->daysUntil($dateTo->copy()->addDay()) as $day) {
            $dateKey = $day->toDateString();
            $row = $rows->get($dateKey);

            $days->push([
                'date_key' => $dateKey,
                'date' => $day->format('M j'),
                'approved_total' => round((float) ($row->approved_total ?? 0), 2),
                'pending_total' => round((float) ($row->pending_total ?? 0), 2),
            ]);
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $occupancy
     * @param  array<int, array<string, mixed>>  $topRooms
     * @param  array<int, array<string, mixed>>  $topMembers
     * @param  array<int, array<string, mixed>>  $shiftCollections
     * @param  array<int, array<string, mixed>>  $dailyBookings
     * @param  array<int, array<string, mixed>>  $dailyPayments
     * @param  array<string, mixed>  $monthlySummary
     */
    private function downloadReportsCsv(
        Carbon $dateFrom,
        Carbon $dateTo,
        array $summary,
        array $occupancy,
        array $topRooms,
        array $topMembers,
        array $shiftCollections,
        array $dailyBookings,
        array $dailyPayments,
        array $monthlySummary,
    ): StreamedResponse {
        $fileName = 'hyve-reports-'.$dateFrom->format('Ymd').'-to-'.$dateTo->format('Ymd').'.csv';

        return response()->streamDownload(function () use (
            $dateFrom,
            $dateTo,
            $summary,
            $occupancy,
            $topRooms,
            $topMembers,
            $shiftCollections,
            $dailyBookings,
            $dailyPayments,
            $monthlySummary,
        ): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['HYVE Reports']);
            fputcsv($handle, ['Date range', $dateFrom->toDateString(), $dateTo->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            foreach ($summary as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Occupancy']);
            foreach ($occupancy as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Monthly summary']);
            fputcsv($handle, ['Period', 'Bookings', 'Approved sales']);
            foreach ($monthlySummary as $period) {
                fputcsv($handle, [$period['label'], $period['bookings'], $period['sales']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Top rooms']);
            fputcsv($handle, ['Room', 'Bookings', 'Revenue']);
            foreach ($topRooms as $room) {
                fputcsv($handle, [$room['room'], $room['bookings'], $room['revenue']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Top members']);
            fputcsv($handle, ['Name', 'Email', 'Bookings', 'Revenue']);
            foreach ($topMembers as $member) {
                fputcsv($handle, [$member['name'], $member['email'], $member['bookings'], $member['revenue']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Shift collection report']);
            fputcsv($handle, ['Staff name', 'Role', 'Cash transactions', 'Cash total', 'Online transactions', 'Online total', 'All transactions', 'Grand total', 'Last verified']);
            foreach ($shiftCollections as $shift) {
                fputcsv($handle, [
                    $shift['name'],
                    $shift['role'],
                    $shift['cash_count'],
                    $shift['cash_total'],
                    $shift['online_count'],
                    $shift['online_total'],
                    $shift['transactions_count'],
                    $shift['grand_total'],
                    $shift['last_verified_at'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Daily bookings']);
            fputcsv($handle, ['Date', 'Total bookings', 'Approved']);
            foreach ($dailyBookings as $day) {
                fputcsv($handle, [$day['date'], $day['count'], $day['approved']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Daily payments']);
            fputcsv($handle, ['Date', 'Approved total', 'Pending total']);
            foreach ($dailyPayments as $day) {
                fputcsv($handle, [$day['date'], $day['approved_total'], $day['pending_total']]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $shiftCollections
     * @param  array<int, array<string, mixed>>  $shiftTransactions
     */
    private function downloadShiftCollectionsExcel(
        Carbon $dateFrom,
        Carbon $dateTo,
        array $shiftCollections,
        array $shiftTransactions,
    ): StreamedResponse {
        $fileName = 'hyve-shift-collections-'.$dateFrom->format('Ymd').'-to-'.$dateTo->format('Ymd').'.xls';

        return response()->streamDownload(function () use ($dateFrom, $dateTo, $shiftCollections, $shiftTransactions): void {
            $grandCash = round((float) collect($shiftCollections)->sum('cash_total'), 2);
            $grandOnline = round((float) collect($shiftCollections)->sum('online_total'), 2);
            $grandTotal = round((float) collect($shiftCollections)->sum('grand_total'), 2);
            $grandTransactions = (int) collect($shiftCollections)->sum('transactions_count');
            $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $summaryRows = [];
            $summaryRows[] = '<Row ss:AutoFitHeight="0" ss:Height="28">'.
                '<Cell ss:MergeAcross="8" ss:StyleID="title"><Data ss:Type="String">HYVE Shift Collection Report</Data></Cell>'.
                '</Row>';
            $summaryRows[] = '<Row>'.
                '<Cell ss:MergeAcross="8" ss:StyleID="subtitle"><Data ss:Type="String">Date range: '.$escape($dateFrom->format('F j, Y').' to '.$dateTo->format('F j, Y')).'</Data></Cell>'.
                '</Row>';
            $summaryRows[] = '<Row/>';
            $summaryRows[] = '<Row>'.
                '<Cell ss:StyleID="summaryLabel"><Data ss:Type="String">Total cash</Data></Cell>'.
                '<Cell ss:StyleID="currency"><Data ss:Type="Number">'.$grandCash.'</Data></Cell>'.
                '<Cell ss:StyleID="summaryLabel"><Data ss:Type="String">Total online</Data></Cell>'.
                '<Cell ss:StyleID="currency"><Data ss:Type="Number">'.$grandOnline.'</Data></Cell>'.
                '<Cell ss:StyleID="summaryLabel"><Data ss:Type="String">Grand total</Data></Cell>'.
                '<Cell ss:StyleID="currencyStrong"><Data ss:Type="Number">'.$grandTotal.'</Data></Cell>'.
                '<Cell ss:StyleID="summaryLabel"><Data ss:Type="String">Transactions</Data></Cell>'.
                '<Cell ss:StyleID="summaryValue"><Data ss:Type="Number">'.$grandTransactions.'</Data></Cell>'.
                '</Row>';
            $summaryRows[] = '<Row/>';
            $summaryRows[] = '<Row>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Staff Name</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Role</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Cash Txns</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Cash Total</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Online Txns</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Online Total</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">All Txns</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Grand Total</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Last Verified</Data></Cell>'.
                '</Row>';

            foreach ($shiftCollections as $shift) {
                $summaryRows[] = '<Row>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $shift['name']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $shift['role']).'</Data></Cell>'.
                    '<Cell ss:StyleID="number"><Data ss:Type="Number">'.(int) $shift['cash_count'].'</Data></Cell>'.
                    '<Cell ss:StyleID="currency"><Data ss:Type="Number">'.round((float) $shift['cash_total'], 2).'</Data></Cell>'.
                    '<Cell ss:StyleID="number"><Data ss:Type="Number">'.(int) $shift['online_count'].'</Data></Cell>'.
                    '<Cell ss:StyleID="currency"><Data ss:Type="Number">'.round((float) $shift['online_total'], 2).'</Data></Cell>'.
                    '<Cell ss:StyleID="number"><Data ss:Type="Number">'.(int) $shift['transactions_count'].'</Data></Cell>'.
                    '<Cell ss:StyleID="currencyStrong"><Data ss:Type="Number">'.round((float) $shift['grand_total'], 2).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $shift['last_verified_at']).'</Data></Cell>'.
                    '</Row>';
            }

            $summaryRows[] = '<Row>'.
                '<Cell ss:MergeAcross="1" ss:StyleID="totalLabel"><Data ss:Type="String">Totals</Data></Cell>'.
                '<Cell ss:StyleID="totalNumber"><Data ss:Type="Number">'.(int) collect($shiftCollections)->sum('cash_count').'</Data></Cell>'.
                '<Cell ss:StyleID="totalCurrency"><Data ss:Type="Number">'.$grandCash.'</Data></Cell>'.
                '<Cell ss:StyleID="totalNumber"><Data ss:Type="Number">'.(int) collect($shiftCollections)->sum('online_count').'</Data></Cell>'.
                '<Cell ss:StyleID="totalCurrency"><Data ss:Type="Number">'.$grandOnline.'</Data></Cell>'.
                '<Cell ss:StyleID="totalNumber"><Data ss:Type="Number">'.$grandTransactions.'</Data></Cell>'.
                '<Cell ss:StyleID="totalCurrency"><Data ss:Type="Number">'.$grandTotal.'</Data></Cell>'.
                '<Cell ss:StyleID="totalLabel"><Data ss:Type="String">--</Data></Cell>'.
                '</Row>';

            $detailRows = [];
            $detailRows[] = '<Row ss:AutoFitHeight="0" ss:Height="28">'.
                '<Cell ss:MergeAcross="11" ss:StyleID="title"><Data ss:Type="String">HYVE Shift Transaction Details</Data></Cell>'.
                '</Row>';
            $detailRows[] = '<Row>'.
                '<Cell ss:MergeAcross="11" ss:StyleID="subtitle"><Data ss:Type="String">Each approved payment included in the shift collections report.</Data></Cell>'.
                '</Row>';
            $detailRows[] = '<Row/>';
            $detailRows[] = '<Row>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Payment ID</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Booking ID</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Reference No</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Customer</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Payment Type</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Method</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Channel</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Amount</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Verified By</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Role</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Verified At</Data></Cell>'.
                '<Cell ss:StyleID="header"><Data ss:Type="String">Paid At</Data></Cell>'.
                '</Row>';

            foreach ($shiftTransactions as $transaction) {
                $detailRows[] = '<Row>'.
                    '<Cell ss:StyleID="number"><Data ss:Type="Number">'.(int) $transaction['payment_id'].'</Data></Cell>'.
                    '<Cell ss:StyleID="number"><Data ss:Type="Number">'.(int) $transaction['booking_id'].'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['reference_no']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['customer_name']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['payment_type']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['payment_method']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['channel']).'</Data></Cell>'.
                    '<Cell ss:StyleID="currencyStrong"><Data ss:Type="Number">'.round((float) $transaction['amount'], 2).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['verified_by']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['verified_role']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['verified_at']).'</Data></Cell>'.
                    '<Cell ss:StyleID="text"><Data ss:Type="String">'.$escape((string) $transaction['paid_at']).'</Data></Cell>'.
                    '</Row>';
            }

            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
            echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';
            echo '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>HYVE</Author><Company>HYVE</Company></DocumentProperties>';
            echo '<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel"><ProtectStructure>False</ProtectStructure><ProtectWindows>False</ProtectWindows></ExcelWorkbook>';
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Borders/><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#1F2D24"/><Interior/><NumberFormat/><Protection/></Style>';
            echo '<Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#173029"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="subtitle"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#6F7D72"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
            echo '<Style ss:ID="summaryLabel"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#173029"/><Interior ss:Color="#EEF6E7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '<Style ss:ID="summaryValue"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '<Style ss:ID="header"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#467436" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '<Style ss:ID="text"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/></Borders></Style>';
            echo '<Style ss:ID="number"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/></Borders></Style>';
            echo '<Style ss:ID="currency"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#25472D"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/></Borders></Style>';
            echo '<Style ss:ID="currencyStrong"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#173029"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E6EDE2"/></Borders></Style>';
            echo '<Style ss:ID="totalLabel"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#173029"/><Interior ss:Color="#EEF6E7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '<Style ss:ID="totalNumber"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#173029"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#EEF6E7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '<Style ss:ID="totalCurrency"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#173029"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/><Interior ss:Color="#EEF6E7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D7E4D0"/></Borders></Style>';
            echo '</Styles>';
            echo '<Worksheet ss:Name="Shift Collections"><Table>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="160"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="100"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="78"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="92"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="84"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="96"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="72"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="96"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="128"/>';
            echo implode('', $summaryRows);
            echo '</Table>';
            echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>6</SplitHorizontal><TopRowBottomPane>6</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions>';
            echo '</Worksheet>';
            echo '<Worksheet ss:Name="Transaction Details"><Table>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="70"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="72"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="145"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="150"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="90"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="96"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="80"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="90"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="140"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="95"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="130"/>';
            echo '<Column ss:AutoFitWidth="0" ss:Width="130"/>';
            echo implode('', $detailRows);
            echo '</Table>';
            echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions>';
            echo '</Worksheet></Workbook>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, array{title:string,eyebrow:string,description:string,note:string}>
     */
    private function sections(): array
    {
        return [
            'rooms' => [
                'title' => 'Rooms',
                'eyebrow' => 'Room setup',
                'description' => 'Manage room records, labels, and operational setup from one place.',
                'note' => 'This page is ready for the room management tools you want to add next.',
            ],
            'room-schedule' => [
                'title' => 'Room Schedule',
                'eyebrow' => 'Schedule control',
                'description' => 'Review time slots, booking density, and room usage in a dedicated schedule workspace.',
                'note' => 'We can turn this into your full schedule board next.',
            ],
            'calendar-events' => [
                'title' => 'Calendar & Events',
                'eyebrow' => 'Date planning',
                'description' => 'Track special dates, closures, promotions, and events that affect room availability.',
                'note' => 'This placeholder is ready for event-based blocking and announcements.',
            ],
            'pricing-rules' => [
                'title' => 'Pricing Rules',
                'eyebrow' => 'Rate controls',
                'description' => 'Manage day use, night use, special pricing, and future pricing policies here.',
                'note' => 'Later we can connect this to your real rate cards and admin editing tools.',
            ],
            'payments' => [
                'title' => 'Payments',
                'eyebrow' => 'Payment review',
                'description' => 'Review payment proofs, pending balances, and verification tasks in one page.',
                'note' => 'Perfect place for payment approval and proof verification later.',
            ],
            'credits' => [
                'title' => 'Credits',
                'eyebrow' => 'Member credit tools',
                'description' => 'Handle member credit balances, manual adjustments, and future top-up tracking.',
                'note' => 'We can add actual credit transactions here once you are ready.',
            ],
            'venue-stock' => [
                'title' => 'Venue Stock',
                'eyebrow' => 'Inventory overview',
                'description' => 'Monitor workspace supplies, consumables, and restock activity for venue operations.',
                'note' => 'This section is ready for stock lists and movement logs.',
            ],
            'shop-products' => [
                'title' => 'Shop Products',
                'eyebrow' => 'Store management',
                'description' => 'Prepare product listings, pricing, and POS-related controls for the admin side.',
                'note' => 'We can connect actual products and ordering later.',
            ],
            'reports' => [
                'title' => 'Reports',
                'eyebrow' => 'Business insights',
                'description' => 'Review sales, occupancy, booking performance, and other reports from one view.',
                'note' => 'This is where summary reports and charts can live later.',
            ],
            'admin-roles' => [
                'title' => 'Admin Roles',
                'eyebrow' => 'Role controls',
                'description' => 'Define access rules, admin permissions, and who can manage sensitive system tools.',
                'note' => 'For now this is reserved for super admin role management.',
            ],
            'settings' => [
                'title' => 'Settings',
                'eyebrow' => 'System setup',
                'description' => 'Control global admin preferences, payment instructions, and future platform settings.',
                'note' => 'We can plug in system configuration modules here next.',
            ],
        ];
    }
}
