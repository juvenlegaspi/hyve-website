<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Support\SalesMonitoringExcelExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSalesMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        [$dateFrom, $dateTo, $range] = $this->dateRange($request);
        $source = in_array($request->query('source'), ['web', 'admin'], true)
            ? (string) $request->query('source')
            : 'all';
        $method = in_array($request->query('method'), ['cash', 'gcash', 'bank_transfer'], true)
            ? (string) $request->query('method')
            : 'all';
        [$previousFrom, $previousTo] = $this->previousDateRange($dateFrom, $dateTo);

        $payments = $this->approvedPaymentsQuery($dateFrom, $dateTo, $source, $method)
            ->with([
                'bookingHeader.details.hyveRoom',
                'bookingHeader.details.space',
                'bookingDetail.hyveRoom',
                'bookingDetail.space',
                'verifiedByUser',
            ])
            ->latest('verified_at')
            ->latest('id')
            ->get();
        $previousSales = (float) $this->approvedPaymentsQuery($previousFrom, $previousTo, $source, $method)->sum('amount');
        $grossSales = round((float) $payments->sum('amount'), 2);
        $transactions = $payments->count();

        $pendingPayments = BookingPayment::query()
            ->with('bookingHeader')
            ->where('status', BookingPayment::STATUS_PENDING)
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);
        $this->applyPaymentFilters($pendingPayments, $source, $method);
        $pendingPayments = $pendingPayments->get();

        $headers = BookingHeader::query()
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->when($source !== 'all', fn (Builder $query) => $query->where('source', $source))
            ->when($method !== 'all', fn (Builder $query) => $query->where('payment_method', $method))
            ->get();
        $confirmedHeaders = $headers->where('status', 'confirmed');
        $outstandingBalance = round((float) $confirmedHeaders->sum('balance_amount'), 2);
        $discounts = round((float) $headers->sum('discount_amount'), 2);
        $bookedValue = round((float) $headers->sum(fn (BookingHeader $header): float => $header->effectiveTotalAmount()), 2);

        $methodBreakdown = $payments
            ->groupBy('payment_method')
            ->map(fn (Collection $group, string $key): array => [
                'key' => $key,
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'amount' => round((float) $group->sum('amount'), 2),
                'transactions' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();
        $sourceBreakdown = $payments
            ->groupBy(fn (BookingPayment $payment): string => (string) ($payment->bookingHeader?->source ?? 'web'))
            ->map(fn (Collection $group, string $key): array => [
                'key' => $key,
                'label' => $key === BookingHeader::SOURCE_ADMIN ? 'Walk-in' : 'Online',
                'amount' => round((float) $group->sum('amount'), 2),
                'transactions' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();
        $roomBreakdown = $this->roomSalesBreakdown($payments);
        $spaceMonthlyComparison = $this->spaceMonthlyComparison($dateTo, $source, $method);
        $spaceWeeklySales = $this->spaceWeeklySales($payments, $dateFrom, $dateTo);
        $demandHeatmap = $this->demandHeatmap($dateFrom, $dateTo, $source, $method);
        $bookingSourceCounts = $this->bookingSourceCounts($headers);
        $bookingLifecycle = $this->bookingLifecycle($headers);
        $salesFunnel = $this->salesFunnel($bookedValue, $grossSales, $outstandingBalance);
        $todayLiveSales = $this->todayLiveSales($source, $method);
        $outstandingAging = $this->outstandingAging($source, $method);
        $upcomingCollections = $this->upcomingCollections($source, $method);
        $roomUtilization = $this->roomUtilization($payments, $dateFrom, $dateTo, $source, $method);
        $liveAlerts = $this->liveAlerts(
            [
                'count' => $pendingPayments->count(),
                'amount' => round((float) $pendingPayments->sum('amount'), 2),
            ],
            $todayLiveSales,
            $outstandingAging,
            $upcomingCollections,
        );
        $trend = $this->salesTrend($payments, $dateFrom, $dateTo);
        $maxBreakdown = max(
            1,
            (float) $methodBreakdown->max('amount'),
            (float) $sourceBreakdown->max('amount'),
            (float) $roomBreakdown->max('amount'),
        );
        $transactionRows = $payments->map(fn (BookingPayment $payment): array => [
            'id' => $payment->getKey(),
            'booking_id' => $payment->booking_header_id,
            'reference' => (string) ($payment->bookingHeader?->reference_no ?? '--'),
            'customer' => (string) ($payment->bookingHeader?->customer_name ?? 'Customer'),
            'source' => $payment->bookingHeader?->source === BookingHeader::SOURCE_ADMIN ? 'Walk-in' : 'Online',
            'method' => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
            'type' => ucfirst((string) $payment->payment_type),
            'amount' => round((float) $payment->amount, 2),
            'verified_at' => optional($payment->verified_at)->format('M j, Y g:i A') ?? '--',
            'verified_by' => (string) ($payment->verifiedByUser?->name ?? 'System'),
        ])->values();

        return view('admin.sales-monitoring.index', [
            'meta' => [
                'title' => 'Sales Monitoring | HYVE Admin',
                'description' => 'Monitor verified collections, payment channels, booking sources, and room sales performance.',
            ],
            'adminUser' => $request->user(),
            'filters' => [
                'range' => $range,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'source' => $source,
                'method' => $method,
            ],
            'summary' => [
                'gross_sales' => $grossSales,
                'transactions' => $transactions,
                'average_transaction' => $transactions > 0 ? round($grossSales / $transactions, 2) : 0,
                'pending_amount' => round((float) $pendingPayments->sum('amount'), 2),
                'pending_count' => $pendingPayments->count(),
                'outstanding_balance' => $outstandingBalance,
                'booked_value' => $bookedValue,
                'discounts' => $discounts,
                'sales_delta' => $this->percentDelta($grossSales, $previousSales),
            ],
            'methodBreakdown' => $methodBreakdown,
            'sourceBreakdown' => $sourceBreakdown,
            'roomBreakdown' => $roomBreakdown,
            'spaceMonthlyComparison' => $spaceMonthlyComparison,
            'spaceWeeklySales' => $spaceWeeklySales,
            'demandHeatmap' => $demandHeatmap,
            'bookingSourceCounts' => $bookingSourceCounts,
            'bookingLifecycle' => $bookingLifecycle,
            'salesFunnel' => $salesFunnel,
            'todayLiveSales' => $todayLiveSales,
            'outstandingAging' => $outstandingAging,
            'upcomingCollections' => $upcomingCollections,
            'roomUtilization' => $roomUtilization,
            'liveAlerts' => $liveAlerts,
            'trend' => $trend,
            'maxTrend' => max(1, (float) $trend->max('amount')),
            'maxBreakdown' => $maxBreakdown,
            'recentTransactions' => $transactionRows->take(12),
            'exportTransactions' => $transactionRows,
        ]);
    }

    public function export(Request $request, SalesMonitoringExcelExport $exporter): StreamedResponse
    {
        return $exporter->download($this->index($request)->getData());
    }

    private function approvedPaymentsQuery(Carbon $from, Carbon $to, string $source, string $method): Builder
    {
        $query = BookingPayment::query()
            ->where('status', BookingPayment::STATUS_APPROVED)
            ->whereNotNull('verified_at')
            ->whereBetween('verified_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $this->applyPaymentFilters($query, $source, $method);

        return $query;
    }

    private function applyPaymentFilters(Builder $query, string $source, string $method): void
    {
        $query
            ->when($source !== 'all', fn (Builder $builder) => $builder->whereHas(
                'bookingHeader',
                fn (Builder $headerQuery) => $headerQuery->where('source', $source)
            ))
            ->when($method !== 'all', fn (Builder $builder) => $builder->where('payment_method', $method));
    }

    /** @return array{Carbon, Carbon, string} */
    private function dateRange(Request $request): array
    {
        $today = today();
        $range = in_array($request->query('range'), ['today', 'week', 'month', 'year', 'custom'], true)
            ? (string) $request->query('range')
            : 'month';

        return match ($range) {
            'today' => [$today->copy(), $today->copy(), $range],
            'week' => [$today->copy()->startOfWeek(), $today->copy(), $range],
            'year' => [$today->copy()->startOfYear(), $today->copy(), $range],
            'custom' => $this->customDateRange($request, $today),
            default => [$today->copy()->startOfMonth(), $today->copy(), 'month'],
        };
    }

    /** @return array{Carbon, Carbon, string} */
    private function customDateRange(Request $request, Carbon $today): array
    {
        try {
            $from = Carbon::parse((string) $request->query('date_from'))->startOfDay();
            $to = Carbon::parse((string) $request->query('date_to'))->startOfDay();
        } catch (\Throwable) {
            return [$today->copy()->startOfMonth(), $today->copy(), 'month'];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from, $to, 'custom'];
    }

    /** @return array{Carbon, Carbon} */
    private function previousDateRange(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();

        return [$previousTo->copy()->subDays($days - 1), $previousTo];
    }

    private function salesTrend(Collection $payments, Carbon $from, Carbon $to): Collection
    {
        if ($from->diffInDays($to) <= 45) {
            $byDate = $payments->groupBy(fn (BookingPayment $payment): string => $payment->verified_at->toDateString());
            $rows = collect();
            $cursor = $from->copy();

            while ($cursor->lte($to)) {
                $group = $byDate->get($cursor->toDateString(), collect());
                $rows->push([
                    'label' => $cursor->format('M j'),
                    'amount' => round((float) $group->sum('amount'), 2),
                    'transactions' => $group->count(),
                ]);
                $cursor->addDay();
            }

            return $rows;
        }

        $byMonth = $payments->groupBy(fn (BookingPayment $payment): string => $payment->verified_at->format('Y-m'));
        $rows = collect();
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $group = $byMonth->get($cursor->format('Y-m'), collect());
            $rows->push([
                'label' => $cursor->format('M Y'),
                'amount' => round((float) $group->sum('amount'), 2),
                'transactions' => $group->count(),
            ]);
            $cursor->addMonth();
        }

        return $rows;
    }

    private function roomSalesBreakdown(Collection $payments): Collection
    {
        $amounts = [];
        $transactions = [];

        foreach ($payments as $payment) {
            $details = $payment->bookingDetail
                ? collect([$payment->bookingDetail])
                : ($payment->bookingHeader?->details ?? collect());

            if ($details->isEmpty()) {
                $amounts['Unassigned'] = ($amounts['Unassigned'] ?? 0) + (float) $payment->amount;
                $transactions['Unassigned'] = ($transactions['Unassigned'] ?? 0) + 1;
                continue;
            }

            $subtotal = (float) $details->sum('subtotal');
            $splitCount = max(1, $details->count());

            foreach ($details as $detail) {
                $room = $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room';
                $weight = $subtotal > 0 ? ((float) $detail->subtotal / $subtotal) : (1 / $splitCount);
                $amounts[$room] = ($amounts[$room] ?? 0) + ((float) $payment->amount * $weight);
                $transactions[$room] = ($transactions[$room] ?? 0) + 1;
            }
        }

        return collect($amounts)
            ->map(fn (float $amount, string $room): array => [
                'label' => $room,
                'amount' => round($amount, 2),
                'transactions' => $transactions[$room] ?? 0,
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->values();
    }

    private function spaceMonthlyComparison(Carbon $anchor, string $source, string $method): array
    {
        $currentFrom = $anchor->copy()->startOfMonth();
        $currentTo = $anchor->copy()->endOfDay();
        $previousFrom = $currentFrom->copy()->subMonthNoOverflow()->startOfMonth();
        $elapsedDays = $currentFrom->diffInDays($currentTo);
        $previousTo = $previousFrom->copy()->addDays($elapsedDays)->endOfDay();

        if ($previousTo->gt($previousFrom->copy()->endOfMonth())) {
            $previousTo = $previousFrom->copy()->endOfMonth();
        }

        $loadPayments = fn (Carbon $from, Carbon $to): Collection => $this
            ->approvedPaymentsQuery($from, $to, $source, $method)
            ->with([
                'bookingHeader.details.hyveRoom',
                'bookingHeader.details.space',
                'bookingDetail.hyveRoom',
                'bookingDetail.space',
            ])
            ->get();
        $currentTotals = $this->spaceSalesTotals($loadPayments($currentFrom, $currentTo));
        $previousTotals = $this->spaceSalesTotals($loadPayments($previousFrom, $previousTo));

        return [
            'current_label' => $currentFrom->format('F Y'),
            'previous_label' => $previousFrom->format('F Y'),
            'current_period' => $currentFrom->format('M j').' - '.$currentTo->format('M j'),
            'previous_period' => $previousFrom->format('M j').' - '.$previousTo->format('M j'),
            'rows' => collect($this->spaceLabels())->map(function (string $label) use ($currentTotals, $previousTotals): array {
                return [
                    'label' => $label,
                    'current' => round((float) ($currentTotals[$label] ?? 0), 2),
                    'previous' => round((float) ($previousTotals[$label] ?? 0), 2),
                ];
            })->values(),
        ];
    }

    private function spaceWeeklySales(Collection $payments, Carbon $from, Carbon $to): Collection
    {
        $visibleFrom = $from->copy();

        if ($visibleFrom->diffInWeeks($to) >= 12) {
            $visibleFrom = $to->copy()->subWeeks(11)->startOfWeek();
        }

        $allocations = $this->paymentSpaceAllocations($payments)
            ->filter(fn (array $row): bool => $row['verified_at']->gte($visibleFrom->copy()->startOfDay()));
        $rows = collect();
        $cursor = $visibleFrom->copy()->startOfWeek();
        $lastWeek = $to->copy()->startOfWeek();

        while ($cursor->lte($lastWeek)) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek();
            $bucket = $allocations->filter(fn (array $row): bool => $row['verified_at']->betweenIncluded($weekStart, $weekEnd));
            $bySpace = $bucket->groupBy('space')->map(fn (Collection $group): float => round((float) $group->sum('amount'), 2));

            $rows->push([
                'label' => $weekStart->format('M j').' - '.$weekEnd->format('M j'),
                'total' => round((float) $bucket->sum('amount'), 2),
                'spaces' => collect($this->spaceLabels())->mapWithKeys(
                    fn (string $label): array => [$label => (float) ($bySpace[$label] ?? 0)]
                )->all(),
            ]);
            $cursor->addWeek();
        }

        return $rows;
    }

    private function demandHeatmap(Carbon $from, Carbon $to, string $source, string $method): array
    {
        $details = BookingDetail::query()
            ->with('bookingHeader')
            ->whereDate('booking_date', '>=', $from->toDateString())
            ->whereDate('booking_date', '<=', $to->toDateString())
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->whereHas('bookingHeader', function (Builder $query) use ($source, $method): void {
                $query
                    ->when($source !== 'all', fn (Builder $builder) => $builder->where('source', $source))
                    ->when($method !== 'all', fn (Builder $builder) => $builder->where('payment_method', $method));
            })
            ->get();
        $blocks = [
            ['key' => 'overnight', 'label' => '2:00 AM - 8:00 AM'],
            ['key' => 'morning', 'label' => '8:00 AM - 12:00 NN'],
            ['key' => 'afternoon', 'label' => '12:00 NN - 5:00 PM'],
            ['key' => 'evening', 'label' => '5:00 PM - 10:00 PM'],
            ['key' => 'late_night', 'label' => '10:00 PM - 2:00 AM'],
        ];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $cells = [];
        $maxBookings = 1;

        foreach ($blocks as $block) {
            foreach ($days as $day) {
                $cells[$block['key']][$day] = ['bookings' => 0, 'guests' => 0];
            }
        }

        foreach ($details as $detail) {
            $minutes = ((int) Carbon::parse((string) $detail->start_time)->format('H') * 60)
                + (int) Carbon::parse((string) $detail->start_time)->format('i');
            $blockKey = match (true) {
                $minutes >= 120 && $minutes < 480 => 'overnight',
                $minutes >= 480 && $minutes < 720 => 'morning',
                $minutes >= 720 && $minutes < 1020 => 'afternoon',
                $minutes >= 1020 && $minutes < 1320 => 'evening',
                default => 'late_night',
            };
            $day = $detail->booking_date->format('l');
            $cells[$blockKey][$day]['bookings']++;
            $cells[$blockKey][$day]['guests'] += max(1, (int) $detail->guests);
            $maxBookings = max($maxBookings, $cells[$blockKey][$day]['bookings']);
        }

        return [
            'days' => $days,
            'rows' => collect($blocks)->map(fn (array $block): array => [
                ...$block,
                'cells' => $cells[$block['key']],
            ])->all(),
            'max_bookings' => $maxBookings,
            'booking_total' => $details->count(),
            'guest_total' => (int) $details->sum(fn (BookingDetail $detail): int => max(1, (int) $detail->guests)),
        ];
    }

    private function bookingSourceCounts(Collection $headers): Collection
    {
        $total = max(1, $headers->count());

        return collect([
            BookingHeader::SOURCE_ADMIN => 'Walk-in',
            BookingHeader::SOURCE_WEB => 'Online / System',
        ])->map(function (string $label, string $key) use ($headers, $total): array {
            $count = $headers->where('source', $key)->count();

            return [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        })->values();
    }

    private function bookingLifecycle(Collection $headers): array
    {
        $headerIds = $headers->pluck('id');
        $rescheduled = $headerIds->isEmpty()
            ? 0
            : BookingActivity::query()
                ->whereIn('booking_header_id', $headerIds)
                ->whereIn('event_key', ['booking_rescheduled', 'booking_rescheduled_by_admin'])
                ->distinct('booking_header_id')
                ->count('booking_header_id');

        return [
            'total' => $headers->count(),
            'rows' => collect([
                ['key' => 'confirmed', 'label' => 'Confirmed', 'count' => $headers->where('status', 'confirmed')->count()],
                ['key' => 'pending', 'label' => 'Pending', 'count' => $headers->where('status', BookingHeader::STATUS_PENDING)->count()],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $headers->where('status', 'cancelled')->count()],
                ['key' => 'rescheduled', 'label' => 'Rescheduled', 'count' => $rescheduled],
            ]),
        ];
    }

    /** @return array<string, float> */
    private function salesFunnel(float $bookedValue, float $collected, float $outstanding): array
    {
        return [
            'booked' => round($bookedValue, 2),
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'collection_rate' => $bookedValue > 0 ? round(($collected / $bookedValue) * 100, 1) : 0,
        ];
    }

    /** @return array<string, float|int|string> */
    private function todayLiveSales(string $source, string $method): array
    {
        $today = today();
        $current = $this->approvedPaymentsQuery($today, $today, $source, $method)->get();
        $yesterday = $today->copy()->subDay();
        $previous = $this->approvedPaymentsQuery($yesterday, $yesterday, $source, $method)->get();
        $sales = round((float) $current->sum('amount'), 2);
        $previousSales = round((float) $previous->sum('amount'), 2);
        $delta = $this->percentDelta($sales, $previousSales);

        return [
            'sales' => $sales,
            'transactions' => $current->count(),
            'average' => $current->isNotEmpty() ? round($sales / $current->count(), 2) : 0,
            'yesterday_sales' => $previousSales,
            'delta_value' => $delta['value'],
            'delta_tone' => $delta['tone'],
        ];
    }

    private function balanceHeaders(string $source, string $method): Collection
    {
        return BookingHeader::query()
            ->with(['details' => fn ($query) => $query->where('status', '!=', BookingDetail::STATUS_CANCELLED)])
            ->where('status', 'confirmed')
            ->where('balance_amount', '>', 0)
            ->when($source !== 'all', fn (Builder $query) => $query->where('source', $source))
            ->when($method !== 'all', fn (Builder $query) => $query->where('payment_method', $method))
            ->get()
            ->filter(fn (BookingHeader $header): bool => $header->details->isNotEmpty())
            ->values();
    }

    private function outstandingAging(string $source, string $method): Collection
    {
        $today = today();
        $buckets = collect([
            'due_today' => ['label' => 'Due today', 'count' => 0, 'amount' => 0.0, 'tone' => 'today'],
            'overdue_1_7' => ['label' => '1-7 days overdue', 'count' => 0, 'amount' => 0.0, 'tone' => 'watch'],
            'overdue_8_30' => ['label' => '8-30 days overdue', 'count' => 0, 'amount' => 0.0, 'tone' => 'warning'],
            'overdue_30_plus' => ['label' => 'Over 30 days overdue', 'count' => 0, 'amount' => 0.0, 'tone' => 'danger'],
        ]);

        foreach ($this->balanceHeaders($source, $method) as $header) {
            $dueDate = $header->details->min(
                fn (BookingDetail $detail): string => $detail->booking_date->toDateString()
            );
            $due = Carbon::parse($dueDate)->startOfDay();

            if ($due->isFuture()) {
                continue;
            }

            $age = $due->diffInDays($today);
            $key = match (true) {
                $age === 0 => 'due_today',
                $age <= 7 => 'overdue_1_7',
                $age <= 30 => 'overdue_8_30',
                default => 'overdue_30_plus',
            };
            $row = $buckets[$key];
            $row['count']++;
            $row['amount'] = round($row['amount'] + (float) $header->balance_amount, 2);
            $buckets[$key] = $row;
        }

        return $buckets->values();
    }

    private function upcomingCollections(string $source, string $method): Collection
    {
        $today = today();
        $buckets = collect([
            'today' => ['label' => 'Expected today', 'count' => 0, 'amount' => 0.0],
            'next_7' => ['label' => 'Next 7 days', 'count' => 0, 'amount' => 0.0],
            'next_30' => ['label' => 'Days 8-30', 'count' => 0, 'amount' => 0.0],
        ]);

        foreach ($this->balanceHeaders($source, $method) as $header) {
            $dueDate = $header->details->min(
                fn (BookingDetail $detail): string => $detail->booking_date->toDateString()
            );
            $due = Carbon::parse($dueDate)->startOfDay();
            $days = $today->diffInDays($due, false);

            if ($days < 0 || $days > 30) {
                continue;
            }

            $key = $days === 0 ? 'today' : ($days <= 7 ? 'next_7' : 'next_30');
            $row = $buckets[$key];
            $row['count']++;
            $row['amount'] = round($row['amount'] + (float) $header->balance_amount, 2);
            $buckets[$key] = $row;
        }

        return $buckets->values();
    }

    private function roomUtilization(
        Collection $payments,
        Carbon $from,
        Carbon $to,
        string $source,
        string $method,
    ): Collection {
        $details = BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->whereDate('booking_date', '<=', $to->toDateString())
            ->where(function (Builder $query) use ($from): void {
                $query
                    ->where(fn (Builder $sameDay) => $sameDay
                        ->whereNull('booking_end_date')
                        ->whereDate('booking_date', '>=', $from->toDateString()))
                    ->orWhere(fn (Builder $range) => $range
                        ->whereNotNull('booking_end_date')
                        ->whereDate('booking_end_date', '>=', $from->toDateString()));
            })
            ->whereHas('bookingHeader', function (Builder $query) use ($source, $method): void {
                $query
                    ->when($source !== 'all', fn (Builder $builder) => $builder->where('source', $source))
                    ->when($method !== 'all', fn (Builder $builder) => $builder->where('payment_method', $method));
            })
            ->get();
        $revenue = $this->spaceSalesTotals($payments);
        $roomCounts = HyveRoom::query()
            ->active()
            ->get()
            ->groupBy(fn (HyveRoom $room): string => $this->roomSpaceGroupLabel($room))
            ->map->count();
        $periodStart = $from->copy()->startOfDay();
        $periodEnd = $to->copy()->endOfDay();
        $days = $from->diffInDays($to) + 1;

        return collect($this->spaceLabels())->map(function (string $label) use (
            $details,
            $revenue,
            $roomCounts,
            $periodStart,
            $periodEnd,
            $days,
        ): array {
            $spaceDetails = $details->filter(fn (BookingDetail $detail): bool => $this->spaceGroupLabel($detail) === $label);
            $bookedHours = round((float) $spaceDetails->sum(
                fn (BookingDetail $detail): float => $this->detailHoursWithin($detail, $periodStart, $periodEnd)
            ), 2);
            $availableHours = max(1, (int) ($roomCounts[$label] ?? 1) * $days * 24);
            $sales = round((float) ($revenue[$label] ?? 0), 2);

            return [
                'label' => $label,
                'booked_hours' => $bookedHours,
                'available_hours' => $availableHours,
                'utilization' => round(min(100, ($bookedHours / $availableHours) * 100), 1),
                'revenue' => $sales,
                'revenue_per_hour' => $bookedHours > 0 ? round($sales / $bookedHours, 2) : 0,
            ];
        });
    }

    private function detailHoursWithin(BookingDetail $detail, Carbon $periodStart, Carbon $periodEnd): float
    {
        $start = $detail->booking_date->copy()->setTimeFromTimeString((string) $detail->start_time);
        $endDate = ($detail->booking_end_date ?: $detail->booking_date)->copy();
        $end = $endDate->setTimeFromTimeString((string) $detail->end_time);

        if ($end->lte($start)) {
            $end->addDay();
        }

        $clippedStart = $start->greaterThan($periodStart) ? $start : $periodStart;
        $clippedEnd = $end->lessThan($periodEnd) ? $end : $periodEnd;

        return $clippedEnd->gt($clippedStart)
            ? round($clippedStart->diffInMinutes($clippedEnd) / 60, 2)
            : 0;
    }

    /**
     * @param  array{count:int,amount:float}  $pending
     * @param  array<string, float|int|string>  $todaySales
     */
    private function liveAlerts(
        array $pending,
        array $todaySales,
        Collection $aging,
        Collection $forecast,
    ): Collection {
        $alerts = collect();
        $overdue = $aging->whereIn('tone', ['watch', 'warning', 'danger']);
        $dueSoon = $forecast->whereIn('label', ['Expected today', 'Next 7 days']);

        if ($pending['count'] > 0) {
            $alerts->push([
                'tone' => 'warning',
                'title' => $pending['count'].' payment(s) awaiting verification',
                'message' => 'Php '.number_format($pending['amount'], 2).' is not yet included in collected sales.',
            ]);
        }

        if ($overdue->sum('count') > 0) {
            $alerts->push([
                'tone' => 'danger',
                'title' => number_format($overdue->sum('count')).' overdue booking balance(s)',
                'message' => 'Php '.number_format($overdue->sum('amount'), 2).' needs collection follow-up.',
            ]);
        }

        if ($dueSoon->sum('count') > 0) {
            $alerts->push([
                'tone' => 'info',
                'title' => number_format($dueSoon->sum('count')).' upcoming balance collection(s)',
                'message' => 'Php '.number_format($dueSoon->sum('amount'), 2).' is expected within seven days.',
            ]);
        }

        if ($todaySales['delta_tone'] === 'down') {
            $alerts->push([
                'tone' => 'watch',
                'title' => 'Today\'s verified sales are below yesterday',
                'message' => $todaySales['delta_value'].' based on approved payments.',
            ]);
        }

        return $alerts;
    }

    private function spaceSalesTotals(Collection $payments): Collection
    {
        return $this->paymentSpaceAllocations($payments)
            ->groupBy('space')
            ->map(fn (Collection $rows): float => round((float) $rows->sum('amount'), 2));
    }

    private function paymentSpaceAllocations(Collection $payments): Collection
    {
        return $payments->flatMap(function (BookingPayment $payment): array {
            $details = $payment->bookingDetail
                ? collect([$payment->bookingDetail])
                : ($payment->bookingHeader?->details ?? collect());

            if ($details->isEmpty()) {
                return [[
                    'verified_at' => $payment->verified_at,
                    'space' => 'Unassigned',
                    'amount' => (float) $payment->amount,
                ]];
            }

            $subtotal = (float) $details->sum('subtotal');
            $splitCount = max(1, $details->count());

            return $details->map(function (BookingDetail $detail) use ($payment, $subtotal, $splitCount): array {
                $weight = $subtotal > 0 ? ((float) $detail->subtotal / $subtotal) : (1 / $splitCount);

                return [
                    'verified_at' => $payment->verified_at,
                    'space' => $this->spaceGroupLabel($detail),
                    'amount' => round((float) $payment->amount * $weight, 2),
                ];
            })->all();
        })->values();
    }

    /** @return list<string> */
    private function spaceLabels(): array
    {
        return ['Common Area', 'Private Room for Two', 'Private Room for Four', 'Conference Room'];
    }

    private function spaceGroupLabel(BookingDetail $detail): string
    {
        $room = $detail->hyveRoom;
        $label = strtolower((string) ($room?->mappedSpaceLabel() ?? $detail->space?->name ?? ''));

        return match (true) {
            $room?->isSharedTable(), str_contains($label, 'common') => 'Common Area',
            str_contains($label, '2 seat'), str_contains($label, 'two') => 'Private Room for Two',
            str_contains($label, '4 seat'), str_contains($label, 'four') => 'Private Room for Four',
            str_contains($label, 'conference'), str_contains($label, '8 seat') => 'Conference Room',
            default => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Unassigned',
        };
    }

    private function roomSpaceGroupLabel(HyveRoom $room): string
    {
        $label = strtolower($room->mappedSpaceLabel());

        return match (true) {
            $room->isSharedTable(), str_contains($label, 'common') => 'Common Area',
            str_contains($label, '2 seat'), str_contains($label, 'two') => 'Private Room for Two',
            str_contains($label, '4 seat'), str_contains($label, 'four') => 'Private Room for Four',
            str_contains($label, 'conference'), str_contains($label, '8 seat') => 'Conference Room',
            default => $room->room_name,
        };
    }

    /** @return array{value:string,tone:string} */
    private function percentDelta(float $current, float $previous): array
    {
        if (abs($previous) < 0.01) {
            return [
                'value' => $current > 0 ? 'New activity' : 'No change',
                'tone' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $delta = round((($current - $previous) / abs($previous)) * 100, 1);

        return [
            'value' => ($delta > 0 ? '+' : '').number_format($delta, 1).'% vs previous period',
            'tone' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral'),
        ];
    }
}
