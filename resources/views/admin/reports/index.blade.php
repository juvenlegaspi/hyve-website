@extends('layouts.admin')

@section('content')
    @php
        $rangeLinks = [
            'month' => route('admin.sections.reports', ['range' => 'month']),
            'week' => route('admin.sections.reports', ['range' => 'week']),
            'today' => route('admin.sections.reports', ['range' => 'today']),
            'year' => route('admin.sections.reports', ['range' => 'year']),
        ];

        $exportParams = ['export' => 'csv'];
        $shiftExportParams = ['export' => 'shift_excel'];

        if ($selectedRange === 'custom') {
            $exportParams['date_from'] = $dateFrom;
            $exportParams['date_to'] = $dateTo;
            $shiftExportParams['date_from'] = $dateFrom;
            $shiftExportParams['date_to'] = $dateTo;
        } else {
            $exportParams['range'] = $selectedRange;
            $shiftExportParams['range'] = $selectedRange;
        }

        $maxRevenue = max(1, (float) collect($chartSeries)->max('revenue'));
        $maxBreakdown = max(1, (float) collect($paymentBreakdown)->max('amount'));
        $chartCount = max(1, count($chartSeries));
        $chartWidth = 900;
        $chartHeight = 260;
        $chartPaddingX = 58;
        $chartPaddingTop = 22;
        $chartPaddingBottom = 44;
        $chartInnerWidth = $chartWidth - ($chartPaddingX * 2);
        $chartInnerHeight = $chartHeight - $chartPaddingTop - $chartPaddingBottom;
        $points = [];

        foreach ($chartSeries as $index => $point) {
            $x = $chartCount === 1
                ? ($chartPaddingX + ($chartInnerWidth / 2))
                : ($chartPaddingX + (($chartInnerWidth / ($chartCount - 1)) * $index));

            $y = $chartPaddingTop + $chartInnerHeight - (($point['revenue'] / $maxRevenue) * $chartInnerHeight);

            $points[] = [
                'label' => $point['label'],
                'revenue' => (float) $point['revenue'],
                'bookings' => (int) $point['bookings'],
                'x' => round($x, 2),
                'y' => round($y, 2),
            ];
        }

        $linePoints = collect($points)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
        $areaBaseY = $chartPaddingTop + $chartInnerHeight;
        $areaEndX = $chartPaddingX + $chartInnerWidth;
        $areaPoints = trim($linePoints.' '.$areaEndX.','.$areaBaseY.' '.$chartPaddingX.','.$areaBaseY);
        $heatmapDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    @endphp

    <style>
        .reports-shell {
            display: grid;
            gap: 1.35rem;
        }

        .reports-panel {
            border: 1px solid #dfe7d8;
            border-radius: 1.55rem;
            background: #fff;
            box-shadow: 0 18px 45px rgba(17, 33, 25, 0.05);
        }

        .reports-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid #d7e0d0;
            background: #fff;
            color: #5d695d;
            padding: 0.72rem 1.1rem;
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .reports-pill:hover {
            border-color: #6a8f47;
            color: #3f612d;
        }

        .reports-pill.is-active {
            border-color: #467436;
            background: #467436;
            color: #fff;
        }

        .reports-kpi {
            display: grid;
            gap: 0.65rem;
            padding: 1.35rem 1.4rem;
        }

        .reports-kpi__label {
            font-size: 0.8rem;
            color: #8e8d82;
        }

        .reports-kpi__value {
            font-size: clamp(1.8rem, 2vw, 2.35rem);
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.04em;
            color: #11211a;
        }

        .reports-kpi__delta {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            border-radius: 999px;
            padding: 0.34rem 0.6rem;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1;
        }

        .reports-kpi__delta--up {
            background: #edf8e3;
            color: #3e7b36;
        }

        .reports-kpi__delta--down {
            background: #fff1eb;
            color: #b05f42;
        }

        .reports-kpi__delta--neutral {
            background: #f3f4ef;
            color: #8b8d83;
        }

        .reports-muted {
            color: #8e8d82;
            font-size: 0.82rem;
        }

        .reports-chart-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid #dfe7d8;
            border-radius: 999px;
            padding: 0.25rem;
            background: #fff;
        }

        .reports-chart-toggle__item {
            border-radius: 999px;
            padding: 0.55rem 1rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #566458;
        }

        .reports-chart-toggle__item.is-active {
            background: #eff8e4;
            color: #467436;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .reports-table thead th {
            padding: 0 0 0.9rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #aca699;
        }

        .reports-table tbody td {
            padding: 0.85rem 0;
            border-top: 1px solid #edf1e9;
            color: #34443a;
        }

        .reports-progress {
            width: 100%;
            height: 0.42rem;
            border-radius: 999px;
            background: #edf1e9;
            overflow: hidden;
        }

        .reports-progress__fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #507d3c 0%, #8dc868 100%);
        }

        .reports-heatmap {
            display: grid;
            gap: 0.5rem;
        }

        .reports-heatmap__head,
        .reports-heatmap__row {
            display: grid;
            grid-template-columns: 2.4rem repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
            align-items: center;
        }

        .reports-heatmap__label {
            font-size: 0.75rem;
            color: #a09c92;
        }

        .reports-heatmap__cell {
            height: 1.05rem;
            border-radius: 0.3rem;
            background: #eef1ea;
        }

        .reports-custom-range {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: end;
        }

        .reports-custom-range__field {
            display: grid;
            gap: 0.35rem;
        }

        .reports-custom-range__field span {
            font-size: 0.74rem;
            font-weight: 700;
            color: #8e8d82;
        }

        .reports-custom-range__field input {
            min-width: 12rem;
            border: 1px solid #dfe7d8;
            border-radius: 0.95rem;
            background: #fff;
            padding: 0.8rem 0.95rem;
            color: #23352d;
            font-size: 0.86rem;
        }

        .reports-custom-range__button {
            border: 1px solid #467436;
            border-radius: 0.95rem;
            background: #467436;
            color: #fff;
            padding: 0.82rem 1.1rem;
            font-size: 0.85rem;
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .reports-heatmap__head,
            .reports-heatmap__row {
                grid-template-columns: 2rem repeat(7, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .reports-custom-range__field input {
                min-width: 100%;
            }
        }
    </style>

    <div class="reports-shell">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h1 class="text-[2rem] font-semibold tracking-[-0.05em] text-[#11211a]">Reports</h1>
                <p class="mt-1 text-[0.95rem] text-[#8e8d82]">Revenue and analytics</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $rangeLinks['month'] }}" class="reports-pill {{ $selectedRange === 'month' ? 'is-active' : '' }}">This month</a>
                <a href="{{ $rangeLinks['week'] }}" class="reports-pill {{ $selectedRange === 'week' ? 'is-active' : '' }}">This week</a>
                <a href="{{ $rangeLinks['today'] }}" class="reports-pill {{ $selectedRange === 'today' ? 'is-active' : '' }}">Today</a>
                <a href="{{ $rangeLinks['year'] }}" class="reports-pill {{ $selectedRange === 'year' ? 'is-active' : '' }}">This year</a>
                <a href="{{ route('admin.sections.reports', $exportParams) }}" class="reports-pill">Export CSV</a>
            </div>
        </section>

        <section class="reports-panel p-5">
            <form method="GET" class="reports-custom-range">
                <label class="reports-custom-range__field">
                    <span>Date from</span>
                    <input type="date" name="date_from" value="{{ $dateFrom }}">
                </label>
                <label class="reports-custom-range__field">
                    <span>Date to</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}">
                </label>
                <button type="submit" class="reports-custom-range__button">Apply custom range</button>
                <a href="{{ route('admin.sections.reports') }}" class="reports-pill">Reset</a>
                <button type="button" onclick="window.print()" class="reports-pill">Print</button>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <article class="reports-panel reports-kpi">
                    <p class="reports-kpi__label">{{ $kpi['label'] }}</p>
                    <strong class="reports-kpi__value">{{ $kpi['value'] }}</strong>
                    <span class="reports-kpi__delta reports-kpi__delta--{{ $kpi['delta']['tone'] }}">{{ $kpi['delta']['value'] }}</span>
                </article>
            @endforeach
        </section>

        <section class="reports-panel p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-[1.5rem] font-semibold tracking-[-0.04em] text-[#11211a]">Revenue trend</h2>
                    <p class="mt-1 text-[0.88rem] text-[#8e8d82]">Approved payment totals across the selected report window.</p>
                </div>

                <div class="reports-chart-toggle">
                    <span class="reports-chart-toggle__item is-active">Revenue</span>
                    <span class="reports-chart-toggle__item">Bookings</span>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="min-w-[760px] w-full" role="img" aria-label="Revenue trend chart">
                    <defs>
                        <linearGradient id="reportsRevenueFill" x1="0%" x2="0%" y1="0%" y2="100%">
                            <stop offset="0%" stop-color="#b8dd7d" stop-opacity="0.65" />
                            <stop offset="100%" stop-color="#f7fbf1" stop-opacity="0.15" />
                        </linearGradient>
                    </defs>

                    <line x1="{{ $chartPaddingX }}" y1="{{ $chartPaddingTop + $chartInnerHeight }}" x2="{{ $chartPaddingX + $chartInnerWidth }}" y2="{{ $chartPaddingTop + $chartInnerHeight }}" stroke="#e6ebe2" stroke-width="1" />
                    <polygon points="{{ $areaPoints }}" fill="url(#reportsRevenueFill)" />
                    <polyline points="{{ $linePoints }}" fill="none" stroke="#466f35" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />

                    @foreach ($points as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="8" fill="#466f35" stroke="#ffffff" stroke-width="4" />
                        <text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 14) }}" text-anchor="middle" font-size="20" font-weight="700" fill="#485244">
                            {{ number_format($point['revenue'] / 1000, $point['revenue'] >= 10000 ? 0 : 1) }}k
                        </text>
                        <text x="{{ $point['x'] }}" y="{{ $chartHeight - 12 }}" text-anchor="middle" font-size="18" fill="#9f9b91">
                            {{ $point['label'] }}
                        </text>
                    @endforeach
                </svg>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <article class="reports-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[1.35rem] font-semibold tracking-[-0.04em] text-[#11211a]">Room performance</h2>
                        <p class="mt-1 text-[0.86rem] text-[#8e8d82]">Best-performing rooms inside the selected range.</p>
                    </div>
                    <span class="text-[0.9rem] text-[#6d766c]">{{ $selectedRange === 'custom' ? 'Custom range' : 'This '.$selectedRange }}</span>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="reports-table min-w-[32rem]">
                        <thead>
                            <tr>
                                <th>Court</th>
                                <th>Bookings</th>
                                <th>Revenue</th>
                                <th>Util.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roomPerformance as $room)
                                <tr>
                                    <td class="font-semibold text-[#14251e]">{{ $room['room'] }}</td>
                                    <td>{{ number_format($room['bookings']) }}</td>
                                    <td>Php {{ number_format($room['revenue'], 2) }}</td>
                                    <td>{{ number_format($room['utilization_rate'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="!py-8 text-center text-[#8e8d82]">No room activity found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="reports-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[1.35rem] font-semibold tracking-[-0.04em] text-[#11211a]">Payment breakdown</h2>
                        <p class="mt-1 text-[0.86rem] text-[#8e8d82]">Approved collections grouped by payment method.</p>
                    </div>
                    <span class="text-[0.9rem] text-[#6d766c]">{{ $selectedRange === 'custom' ? 'Custom range' : 'This '.$selectedRange }}</span>
                </div>

                <div class="mt-5 grid gap-4">
                    @forelse ($paymentBreakdown as $payment)
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-[#23352d]">{{ $payment['method'] }}</span>
                                <span class="text-right text-[0.94rem] text-[#3d5834]">{{ number_format($payment['percentage'], 1) }}% - Php {{ number_format($payment['amount'], 2) }}</span>
                            </div>
                            <div class="reports-progress">
                                <div class="reports-progress__fill" style="width: {{ min(100, ($payment['amount'] / $maxBreakdown) * 100) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-[#d7ded1] bg-[#fbfcf8] p-4 text-[0.85rem] text-[#8e8d82]">
                            No approved payments found for this range yet.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="reports-panel p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-[1.35rem] font-semibold tracking-[-0.04em] text-[#11211a]">Shift collection report</h2>
                    <p class="mt-1 text-[0.86rem] text-[#8e8d82]">Approved collections grouped by the receptionist/admin who verified the payment.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[0.9rem] text-[#6d766c]">Cash and online per shift login</span>
                    <a href="{{ route('admin.sections.reports', $shiftExportParams) }}" class="reports-pill">Export Excel</a>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="reports-table min-w-[58rem]">
                    <thead>
                        <tr>
                            <th>Staff name</th>
                            <th>Role</th>
                            <th>Cash txns</th>
                            <th>Cash total</th>
                            <th>Online txns</th>
                            <th>Online total</th>
                            <th>All txns</th>
                            <th>Grand total</th>
                            <th>Last verified</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shiftCollections as $shift)
                            <tr>
                                <td class="font-semibold text-[#14251e]">{{ $shift['name'] }}</td>
                                <td>{{ $shift['role'] }}</td>
                                <td>{{ number_format($shift['cash_count']) }}</td>
                                <td>Php {{ number_format($shift['cash_total'], 2) }}</td>
                                <td>{{ number_format($shift['online_count']) }}</td>
                                <td>Php {{ number_format($shift['online_total'], 2) }}</td>
                                <td>{{ number_format($shift['transactions_count']) }}</td>
                                <td class="font-semibold text-[#3d5834]">Php {{ number_format($shift['grand_total'], 2) }}</td>
                                <td>{{ $shift['last_verified_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="!py-8 text-center text-[#8e8d82]">No verified shift collections found for this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.96fr_1.04fr]">
            <article class="reports-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[1.35rem] font-semibold tracking-[-0.04em] text-[#11211a]">Top members</h2>
                        <p class="mt-1 text-[0.86rem] text-[#8e8d82]">Members with the strongest booking value contribution.</p>
                    </div>
                    <span class="reports-pill">{{ number_format(count($topMembers)) }} listed</span>
                </div>

                <div class="mt-5 grid gap-4">
                    @forelse ($topMembers as $member)
                        <article class="rounded-[1.15rem] border border-[#e7ece2] bg-[#fcfdf9] px-4 py-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-[1rem] font-semibold text-[#14251e]">{{ $member['name'] }}</h3>
                                    <p class="mt-1 text-[0.83rem] text-[#8e8d82]">{{ $member['email'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[0.92rem] font-semibold text-[#3d5834]">Php {{ number_format($member['revenue'], 2) }}</p>
                                    <p class="mt-1 text-[0.8rem] text-[#8e8d82]">{{ number_format($member['bookings']) }} bookings</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-[#d7ded1] bg-[#fbfcf8] p-4 text-[0.85rem] text-[#8e8d82]">
                            No member activity found for this range yet.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="reports-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[1.35rem] font-semibold tracking-[-0.04em] text-[#11211a]">Peak booking hours</h2>
                        <p class="mt-1 text-[0.86rem] text-[#8e8d82]">Quick heatmap of the busiest booking start times.</p>
                    </div>
                    <span class="text-[0.9rem] text-[#6d766c]">Avg per week</span>
                </div>

                <div class="mt-5 reports-heatmap">
                    <div class="reports-heatmap__head">
                        <span></span>
                        @foreach ($heatmapDays as $day)
                            <span class="text-center text-[0.74rem] text-[#a09c92]">{{ $day }}</span>
                        @endforeach
                    </div>

                    @foreach ($peakBookingHours as $row)
                        <div class="reports-heatmap__row">
                            <span class="reports-heatmap__label">{{ $row['hour'] }}</span>
                            @foreach ($row['cells'] as $cell)
                                <span
                                    class="reports-heatmap__cell"
                                    title="{{ $row['hour'] }} {{ $cell['day'] }} - {{ $cell['count'] }} booking(s)"
                                    style="background: rgba(92, 154, 62, {{ max(0.08, $cell['intensity'] / 100) }});"
                                ></span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </article>
        </section>
    </div>
@endsection
