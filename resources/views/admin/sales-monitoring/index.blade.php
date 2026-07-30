@extends('layouts.admin')

@section('content')
    @php
        $periodLabel = \Illuminate\Support\Carbon::parse($filters['date_from'])->format('M j, Y')
            .' - '.
            \Illuminate\Support\Carbon::parse($filters['date_to'])->format('M j, Y');
        $rangeLinks = collect([
            'today' => 'Today',
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ])->mapWithKeys(fn (string $label, string $range): array => [
            $range => [
                'label' => $label,
                'url' => route('admin.sales-monitoring.index', array_filter([
                    'range' => $range,
                    'source' => $filters['source'] !== 'all' ? $filters['source'] : null,
                    'method' => $filters['method'] !== 'all' ? $filters['method'] : null,
                ])),
            ],
        ]);
        $sourceLabel = match ($filters['source']) {
            'web' => 'Online bookings',
            'admin' => 'Walk-in bookings',
            default => 'All booking sources',
        };
        $methodLabel = $filters['method'] === 'all'
            ? 'All payment channels'
            : ucfirst(str_replace('_', ' ', $filters['method']));
        $excelUrl = route('admin.sales-monitoring.export', [
            'range' => $filters['range'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'source' => $filters['source'],
            'method' => $filters['method'],
        ]);
    @endphp

    <style>
        .executive-dashboard {
            --ink:#12271f;
            --muted:#788179;
            --line:#e4e8df;
            --green:#315e3d;
            --green-dark:#173b29;
            --green-soft:#edf4e8;
            --gold:#c59b50;
            display:grid;
            gap:1.1rem;
        }
        .executive-card {
            border:1px solid var(--line);
            border-radius:1.15rem;
            background:#fff;
            box-shadow:0 12px 35px rgba(27,48,37,.045);
        }
        .executive-hero {
            position:relative;
            overflow:hidden;
            border-radius:1.35rem;
            background:
                radial-gradient(circle at 88% 18%,rgba(210,178,112,.18),transparent 28%),
                linear-gradient(125deg,#102f22 0%,#26543a 58%,#3e7149 100%);
            color:#fff;
            box-shadow:0 24px 55px rgba(22,58,39,.17);
        }
        .executive-hero::after {
            position:absolute;
            right:-4rem;
            bottom:-7rem;
            width:19rem;
            height:19rem;
            border:1px solid rgba(255,255,255,.1);
            border-radius:999px;
            content:"";
            box-shadow:0 0 0 3rem rgba(255,255,255,.025),0 0 0 6rem rgba(255,255,255,.018);
        }
        .executive-overline {
            color:var(--gold);
            font-size:.65rem;
            font-weight:800;
            letter-spacing:.2em;
            text-transform:uppercase;
        }
        .executive-live {
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            border:1px solid rgba(255,255,255,.18);
            border-radius:999px;
            background:rgba(255,255,255,.08);
            padding:.42rem .72rem;
            color:#e8f1e7;
            font-size:.65rem;
            font-weight:650;
            backdrop-filter:blur(10px);
        }
        .executive-live::before {
            width:.42rem;
            height:.42rem;
            border-radius:999px;
            background:#b7dc8e;
            box-shadow:0 0 0 .22rem rgba(183,220,142,.12);
            content:"";
        }
        .executive-toolbar {
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            justify-content:space-between;
            gap:.8rem;
            padding:.8rem;
        }
        .executive-periods {
            display:flex;
            flex-wrap:wrap;
            gap:.3rem;
            border-radius:.82rem;
            background:#f3f5f0;
            padding:.25rem;
        }
        .executive-button {
            display:inline-flex;
            min-height:2.25rem;
            align-items:center;
            justify-content:center;
            gap:.42rem;
            border:1px solid transparent;
            border-radius:.65rem;
            padding:0 .8rem;
            color:#657068;
            font-size:.68rem;
            font-weight:700;
            transition:.18s ease;
        }
        .executive-button:hover { color:var(--ink); background:#fff; }
        .executive-button.is-active { color:#fff; background:var(--green-dark); box-shadow:0 5px 15px rgba(23,59,41,.14); }
        .executive-button--border { border-color:#dfe5dc; background:#fff; color:#365442; }
        .executive-button--primary { color:#fff; background:var(--green); }
        .executive-button--excel { border-color:#315e3d; color:#fff; background:#315e3d; box-shadow:0 6px 16px rgba(49,94,61,.14); }
        .executive-button--excel:hover { color:#fff; background:#244d32; }
        .executive-filters { display:flex; flex-wrap:wrap; align-items:end; gap:.55rem; }
        .executive-field { display:grid; gap:.3rem; }
        .executive-field span { color:#8b918a; font-size:.6rem; font-weight:750; letter-spacing:.04em; text-transform:uppercase; }
        .executive-field input,.executive-field select {
            height:2.35rem;
            border:1px solid #dfe5dc;
            border-radius:.68rem;
            outline:none;
            background:#fff;
            padding:0 .7rem;
            color:#30443a;
            font-size:.68rem;
        }
        .executive-field input:focus,.executive-field select:focus { border-color:#7c9b77; box-shadow:0 0 0 3px rgba(75,119,70,.08); }
        .executive-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.85rem; }
        .executive-kpi { position:relative; overflow:hidden; padding:1.15rem; }
        .executive-kpi__head { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .executive-kpi__label { color:#7f877f; font-size:.67rem; font-weight:700; }
        .executive-kpi__icon {
            display:flex;
            width:2rem;
            height:2rem;
            align-items:center;
            justify-content:center;
            border-radius:.65rem;
            color:#3d7046;
            background:#edf5e8;
        }
        .executive-kpi__value { display:block; margin-top:.75rem; color:var(--ink); font-size:1.45rem; font-weight:720; letter-spacing:-.045em; line-height:1; }
        .executive-kpi__note { display:block; margin-top:.65rem; color:#8a918a; font-size:.62rem; line-height:1.45; }
        .executive-delta { display:inline-flex; align-items:center; border-radius:999px; padding:.23rem .5rem; font-weight:700; }
        .executive-delta--up { color:#37703e; background:#edf6e8; }
        .executive-delta--down { color:#a64b3b; background:#fff0ec; }
        .executive-delta--neutral { color:#6f7770; background:#f1f3ef; }
        .executive-live-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(18rem,.6fr); gap:1rem; }
        .executive-funnel { display:grid; gap:.7rem; margin-top:1rem; }
        .executive-funnel__row { display:grid; grid-template-columns:8.5rem minmax(5rem,1fr) 8rem; align-items:center; gap:.75rem; }
        .executive-funnel__row span { color:#657168; font-size:.64rem; font-weight:680; }
        .executive-funnel__track { height:1.45rem; overflow:hidden; border-radius:.42rem; background:#eef1ec; }
        .executive-funnel__track i { display:block; height:100%; min-width:3px; border-radius:inherit; }
        .executive-funnel__row strong { color:#21392d; font-size:.7rem; text-align:right; }
        .executive-today-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.55rem; margin-top:1rem; }
        .executive-today-stat { border:1px solid #e7ebe4; border-radius:.75rem; background:#f8faf6; padding:.8rem; }
        .executive-today-stat span { display:block; color:#8b938c; font-size:.55rem; text-transform:uppercase; }
        .executive-today-stat strong { display:block; margin-top:.45rem; color:#173b29; font-size:.82rem; }
        .executive-alerts { display:grid; gap:.55rem; }
        .executive-alert { display:flex; align-items:flex-start; gap:.7rem; border:1px solid #e5e9e2; border-radius:.8rem; padding:.8rem .9rem; background:#fafbf8; }
        .executive-alert::before { width:.5rem; height:.5rem; flex:0 0 auto; margin-top:.18rem; border-radius:999px; background:#7d9bc4; content:""; }
        .executive-alert--danger::before { background:#c75b4d; }
        .executive-alert--warning::before { background:#d39a38; }
        .executive-alert--watch::before { background:#ad7040; }
        .executive-alert--healthy::before { background:#4e8245; }
        .executive-alert strong { display:block; color:#263e32; font-size:.66rem; }
        .executive-alert span { display:block; margin-top:.18rem; color:#7f8881; font-size:.58rem; line-height:1.4; }
        .executive-collection-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
        .executive-balance-list { display:grid; gap:.65rem; margin-top:1rem; }
        .executive-balance-row { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.8rem; border-bottom:1px solid #edf0ea; padding-bottom:.65rem; }
        .executive-balance-row:last-child { border:0; padding-bottom:0; }
        .executive-balance-row span { color:#66736b; font-size:.64rem; }
        .executive-balance-row span small { display:block; margin-top:.15rem; color:#9aa099; font-size:.54rem; }
        .executive-balance-row strong { color:#263f32; font-size:.72rem; }
        .executive-utilization { overflow-x:auto; margin-top:1rem; }
        .executive-utilization table { width:100%; min-width:48rem; border-collapse:collapse; }
        .executive-utilization th,.executive-utilization td { padding:.72rem .65rem; border-bottom:1px solid #edf0eb; color:#59675f; font-size:.61rem; text-align:right; white-space:nowrap; }
        .executive-utilization th:first-child,.executive-utilization td:first-child { text-align:left; }
        .executive-utilization th { color:#939a93; font-size:.55rem; letter-spacing:.06em; text-transform:uppercase; }
        .executive-utilization__meter { display:inline-flex; width:7rem; height:.42rem; overflow:hidden; border-radius:999px; background:#edf0eb; vertical-align:middle; }
        .executive-utilization__meter i { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#315e3d,#8caf72); }
        .executive-section-head { display:flex; align-items:start; justify-content:space-between; gap:1rem; }
        .executive-section-head h2 { color:var(--ink); font-size:.9rem; font-weight:720; letter-spacing:-.02em; }
        .executive-section-head p { margin-top:.25rem; color:#8a918a; font-size:.64rem; }
        .executive-amount { color:#315e3d; font-size:.76rem; font-weight:750; }
        .executive-main-grid { display:grid; grid-template-columns:minmax(0,1.75fr) minmax(17rem,.75fr); gap:1rem; }
        .executive-chart {
            display:flex;
            height:15rem;
            align-items:end;
            gap:.4rem;
            overflow-x:auto;
            margin-top:1rem;
            padding-top:.5rem;
            background:repeating-linear-gradient(to bottom,transparent 0,transparent calc(25% - 1px),#eef1ec 25%);
        }
        .executive-chart__item { display:grid; min-width:2.1rem; height:100%; flex:1 0 2.1rem; grid-template-rows:1fr auto; align-items:end; gap:.52rem; text-align:center; }
        .executive-chart__track { display:flex; height:100%; align-items:end; justify-content:center; }
        .executive-chart__bar {
            position:relative;
            width:min(1.45rem,68%);
            min-height:.2rem;
            border-radius:.35rem .35rem .1rem .1rem;
            background:linear-gradient(180deg,#6d9c61,#2d613d);
            box-shadow:0 7px 14px rgba(45,97,61,.12);
            transition:.2s ease;
        }
        .executive-chart__bar:hover { filter:brightness(1.08); transform:translateY(-2px); }
        .executive-chart__label { padding-bottom:.1rem; color:#929890; font-size:.57rem; white-space:nowrap; }
        .executive-summary {
            position:relative;
            overflow:hidden;
            background:linear-gradient(145deg,#fbfcf9,#f4f7f1);
        }
        .executive-summary__row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.85rem 0; border-bottom:1px solid #e7ebe3; }
        .executive-summary__row:last-child { border:0; }
        .executive-summary__row span { color:#79827a; font-size:.66rem; }
        .executive-summary__row strong { color:#21392d; font-size:.76rem; }
        .executive-analytics-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
        .executive-space-legend { display:flex; flex-wrap:wrap; gap:.65rem 1rem; margin-top:.8rem; }
        .executive-space-legend span { display:inline-flex; align-items:center; gap:.35rem; color:#7d867e; font-size:.6rem; }
        .executive-space-legend i { width:.55rem; height:.55rem; border-radius:.18rem; }
        .executive-graph {
            overflow-x:auto;
            margin-top:1rem;
            border-radius:.9rem;
            background:linear-gradient(135deg,#292d2b,#4a4d4b);
            padding:1.1rem 1rem .85rem;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.07);
        }
        .executive-stacked-chart {
            display:flex;
            min-width:31rem;
            height:17rem;
            align-items:flex-end;
            gap:1rem;
            padding:.75rem .5rem 0;
            background:repeating-linear-gradient(to bottom,transparent 0,transparent calc(20% - 1px),rgba(255,255,255,.11) 20%);
        }
        .executive-stacked-chart--weekly { min-width:43rem; }
        .executive-stacked-chart--demand { min-width:47rem; }
        .executive-stack-column {
            display:grid;
            min-width:4.25rem;
            height:100%;
            flex:1 0 4.25rem;
            grid-template-rows:1fr auto;
            gap:.55rem;
            text-align:center;
        }
        .executive-stack-column__plot { display:flex; min-height:0; align-items:flex-end; justify-content:center; }
        .executive-stack-column__bar {
            display:flex;
            width:min(6rem,72%);
            height:100%;
            min-width:2.7rem;
            flex-direction:column-reverse;
            justify-content:flex-start;
            filter:drop-shadow(0 7px 8px rgba(0,0,0,.22));
        }
        .executive-stack-segment {
            display:flex;
            min-height:0;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            color:#fff;
            font-size:.55rem;
            font-weight:750;
            line-height:1;
            text-shadow:0 1px 2px rgba(0,0,0,.28);
            transition:filter .18s ease;
        }
        .executive-stack-segment:first-child { border-radius:0 0 .2rem .2rem; }
        .executive-stack-segment:last-child { border-radius:.25rem .25rem 0 0; }
        .executive-stack-segment:hover { filter:brightness(1.14); }
        .executive-stack-column__label { min-height:1.7rem; color:#e6e9e7; font-size:.58rem; line-height:1.25; }
        .executive-graph .executive-space-legend { justify-content:center; margin-top:.85rem; }
        .executive-graph .executive-space-legend span { color:#e2e5e3; }
        .executive-graph-note { margin-top:.55rem; color:#aeb5b0; font-size:.54rem; text-align:center; }
        .executive-lifecycle-chart { min-width:18rem; }
        .executive-booking-grid { display:grid; grid-template-columns:minmax(0,.75fr) minmax(0,1.25fr); gap:1rem; }
        .executive-donut-wrap { display:flex; align-items:center; gap:1.25rem; margin-top:1rem; }
        .executive-donut { display:grid; width:8.5rem; aspect-ratio:1; flex:0 0 auto; place-items:center; border-radius:999px; }
        .executive-donut::after { width:5.2rem; aspect-ratio:1; border-radius:999px; background:#fff; content:""; box-shadow:inset 0 0 0 1px #edf0eb; }
        .executive-source-list { display:grid; flex:1; gap:.75rem; }
        .executive-source-list__row { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .executive-source-list__row span { color:#69756d; font-size:.64rem; }
        .executive-source-list__row strong { color:#263e32; font-size:.72rem; }
        .executive-mix { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
        .executive-breakdown { display:grid; gap:.9rem; margin-top:1.1rem; }
        .executive-breakdown__top { display:flex; align-items:center; justify-content:space-between; gap:.7rem; }
        .executive-breakdown__top strong { color:#284035; font-size:.7rem; font-weight:680; }
        .executive-breakdown__top span { color:#7d867e; font-size:.62rem; white-space:nowrap; }
        .executive-progress { height:.38rem; overflow:hidden; border-radius:999px; background:#edf0eb; }
        .executive-progress span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#315e3d,#8caf72); }
        .executive-table { width:100%; border-collapse:collapse; }
        .executive-table th { padding:.72rem .9rem; border-top:1px solid #ebeee8; border-bottom:1px solid #e6eae3; background:#fafbf8; color:#9aa097; font-size:.57rem; font-weight:800; letter-spacing:.1em; text-align:left; text-transform:uppercase; }
        .executive-table td { padding:.82rem .9rem; border-bottom:1px solid #eef1ec; color:#4c5e54; font-size:.66rem; vertical-align:middle; }
        .executive-table tbody tr:last-child td { border-bottom:0; }
        .executive-table tbody tr:hover { background:#fbfcfa; }
        .executive-table strong { display:block; color:#1d3529; font-size:.7rem; font-weight:680; }
        .executive-table small { display:block; margin-top:.12rem; color:#989e97; font-size:.58rem; }
        .executive-badge { display:inline-flex; border-radius:999px; padding:.3rem .55rem; color:#42633f; background:#edf5e9; font-size:.58rem; font-weight:750; }
        .executive-badge--online { color:#4666a2; background:#edf3ff; }
        @media (max-width:1200px) {
            .executive-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .executive-main-grid { grid-template-columns:1fr; }
            .executive-live-grid,.executive-analytics-grid,.executive-booking-grid { grid-template-columns:1fr; }
            .executive-mix { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:700px) {
            .executive-hero { border-radius:1rem; }
            .executive-kpis,.executive-mix,.executive-collection-grid { grid-template-columns:1fr; }
            .executive-funnel__row { grid-template-columns:6.5rem minmax(4rem,1fr); }
            .executive-funnel__row strong { grid-column:2; }
            .executive-today-grid { grid-template-columns:1fr; }
            .executive-donut-wrap { align-items:flex-start; flex-direction:column; }
            .executive-toolbar { align-items:stretch; }
            .executive-filters { display:grid; grid-template-columns:1fr 1fr; width:100%; }
            .executive-field input,.executive-field select { width:100%; }
        }
        @media print {
            aside,.executive-toolbar,.executive-print { display:none !important; }
            main { padding:0 !important; }
            .executive-card { box-shadow:none; break-inside:avoid; }
            .executive-hero { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
        }
    </style>

    <div class="executive-dashboard">
        <section class="executive-hero px-5 py-6 lg:px-7 lg:py-7">
            <div class="relative z-[1] flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="executive-overline">Executive revenue overview</p>
                    <h1 class="mt-3 text-[1.7rem] font-semibold tracking-[-0.05em] lg:text-[2rem]">Sales Monitoring</h1>
                    <p class="mt-2 max-w-[38rem] text-[.7rem] leading-relaxed text-[#d8e5d9]">
                        A consolidated view of verified collections, payment activity, and booking revenue performance.
                    </p>
                </div>
                <span class="executive-live">Verified financial data</span>
            </div>

            <div class="relative z-[1] mt-7 grid gap-5 border-t border-white/10 pt-5 sm:grid-cols-3">
                <div>
                    <span class="block text-[.6rem] font-semibold uppercase tracking-[.12em] text-[#aebfb1]">Reporting period</span>
                    <strong class="mt-2 block text-[.78rem] font-semibold">{{ $periodLabel }}</strong>
                </div>
                <div>
                    <span class="block text-[.6rem] font-semibold uppercase tracking-[.12em] text-[#aebfb1]">Booking source</span>
                    <strong class="mt-2 block text-[.78rem] font-semibold">{{ $sourceLabel }}</strong>
                </div>
                <div>
                    <span class="block text-[.6rem] font-semibold uppercase tracking-[.12em] text-[#aebfb1]">Payment channel</span>
                    <strong class="mt-2 block text-[.78rem] font-semibold">{{ $methodLabel }}</strong>
                </div>
            </div>
        </section>

        <section class="executive-card executive-toolbar">
            <div class="executive-periods">
                @foreach ($rangeLinks as $key => $link)
                    <a href="{{ $link['url'] }}" class="executive-button {{ $filters['range'] === $key ? 'is-active' : '' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.sales-monitoring.index') }}" class="executive-filters">
                <input type="hidden" name="range" value="custom">
                <label class="executive-field">
                    <span>From</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" required>
                </label>
                <label class="executive-field">
                    <span>To</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" required>
                </label>
                <label class="executive-field">
                    <span>Source</span>
                    <select name="source">
                        <option value="all" @selected($filters['source'] === 'all')>All sources</option>
                        <option value="web" @selected($filters['source'] === 'web')>Online</option>
                        <option value="admin" @selected($filters['source'] === 'admin')>Walk-in</option>
                    </select>
                </label>
                <label class="executive-field">
                    <span>Method</span>
                    <select name="method">
                        <option value="all" @selected($filters['method'] === 'all')>All methods</option>
                        <option value="cash" @selected($filters['method'] === 'cash')>Cash</option>
                        <option value="gcash" @selected($filters['method'] === 'gcash')>GCash</option>
                        <option value="bank_transfer" @selected($filters['method'] === 'bank_transfer')>Bank transfer</option>
                    </select>
                </label>
                <button type="submit" class="executive-button executive-button--primary">Apply</button>
                <a href="{{ route('admin.sales-monitoring.index') }}" class="executive-button executive-button--border">Reset</a>
                <a href="{{ $excelUrl }}" class="executive-button executive-button--excel">
                    <svg viewBox="0 0 16 16" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 2.5h7l3 3v8H3zM10 2.5v3h3M5.2 8l3.6 3M8.8 8l-3.6 3"></path>
                    </svg>
                    Export Excel
                </a>
                <button type="button" class="executive-button executive-button--border executive-print" onclick="window.print()">
                    <svg viewBox="0 0 16 16" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 6V2.5h8V6M4 11H2.5V6.5h11V11H12M4 9.5h8v4H4z"></path>
                    </svg>
                    Print
                </button>
            </form>
        </section>

        <section class="executive-kpis">
            <article class="executive-card executive-kpi">
                <div class="executive-kpi__head">
                    <span class="executive-kpi__label">Verified collections</span>
                    <span class="executive-kpi__icon">
                        <svg viewBox="0 0 18 18" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 5.5h13v9h-13zM2.5 8.3h13M5.2 11.4h2.4"></path></svg>
                    </span>
                </div>
                <strong class="executive-kpi__value">Php {{ number_format($summary['gross_sales'], 2) }}</strong>
                <span class="executive-kpi__note">
                    <span class="executive-delta executive-delta--{{ $summary['sales_delta']['tone'] }}">{{ $summary['sales_delta']['value'] }}</span>
                </span>
            </article>

            <article class="executive-card executive-kpi">
                <div class="executive-kpi__head">
                    <span class="executive-kpi__label">Verified transactions</span>
                    <span class="executive-kpi__icon">
                        <svg viewBox="0 0 18 18" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4.5h12M3 9h12M3 13.5h7M13 12v4M11 14h4"></path></svg>
                    </span>
                </div>
                <strong class="executive-kpi__value">{{ number_format($summary['transactions']) }}</strong>
                <span class="executive-kpi__note">Average value: Php {{ number_format($summary['average_transaction'], 2) }}</span>
            </article>

            <article class="executive-card executive-kpi">
                <div class="executive-kpi__head">
                    <span class="executive-kpi__label">Pending verification</span>
                    <span class="executive-kpi__icon">
                        <svg viewBox="0 0 18 18" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="9" r="6.5"></circle><path d="M9 5.3V9l2.6 1.7"></path></svg>
                    </span>
                </div>
                <strong class="executive-kpi__value">Php {{ number_format($summary['pending_amount'], 2) }}</strong>
                <span class="executive-kpi__note">{{ number_format($summary['pending_count']) }} payment{{ $summary['pending_count'] !== 1 ? 's' : '' }} awaiting review</span>
            </article>

            <article class="executive-card executive-kpi">
                <div class="executive-kpi__head">
                    <span class="executive-kpi__label">Outstanding balance</span>
                    <span class="executive-kpi__icon">
                        <svg viewBox="0 0 18 18" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3.5h12v11H3zM6 7h6M6 10h4"></path></svg>
                    </span>
                </div>
                <strong class="executive-kpi__value">Php {{ number_format($summary['outstanding_balance'], 2) }}</strong>
                <span class="executive-kpi__note">Remaining from confirmed bookings in period</span>
            </article>
        </section>

        @php
            $funnelMax = max(1, $salesFunnel['booked'], $salesFunnel['collected'], $salesFunnel['outstanding']);
        @endphp

        <section class="executive-live-grid">
            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Sales funnel</h2>
                        <p>Booked value, actual verified collections, and open balances for the selected period</p>
                    </div>
                    <strong class="executive-amount">{{ number_format($salesFunnel['collection_rate'], 1) }}% collection ratio</strong>
                </div>
                <div class="executive-funnel">
                    @foreach ([
                        ['label' => 'Booked value', 'value' => $salesFunnel['booked'], 'color' => '#7d9bc4'],
                        ['label' => 'Verified collected', 'value' => $salesFunnel['collected'], 'color' => '#315e3d'],
                        ['label' => 'Outstanding', 'value' => $salesFunnel['outstanding'], 'color' => '#c59b50'],
                    ] as $funnelRow)
                        <div class="executive-funnel__row">
                            <span>{{ $funnelRow['label'] }}</span>
                            <div class="executive-funnel__track">
                                <i style="width:{{ min(100, ($funnelRow['value'] / $funnelMax) * 100) }}%;background:{{ $funnelRow['color'] }}"></i>
                            </div>
                            <strong>Php {{ number_format($funnelRow['value'], 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Today's live sales</h2>
                        <p>Approved payments verified today, updated on every page refresh</p>
                    </div>
                    <span class="executive-delta executive-delta--{{ $todayLiveSales['delta_tone'] }}">{{ $todayLiveSales['delta_value'] }}</span>
                </div>
                <div class="executive-today-grid">
                    <div class="executive-today-stat">
                        <span>Collected</span>
                        <strong>Php {{ number_format($todayLiveSales['sales'], 2) }}</strong>
                    </div>
                    <div class="executive-today-stat">
                        <span>Transactions</span>
                        <strong>{{ number_format($todayLiveSales['transactions']) }}</strong>
                    </div>
                    <div class="executive-today-stat">
                        <span>Average</span>
                        <strong>Php {{ number_format($todayLiveSales['average'], 2) }}</strong>
                    </div>
                </div>
            </article>
        </section>

        <section class="executive-card p-5 lg:p-6">
            <div class="executive-section-head">
                <div>
                    <h2>Live monitoring alerts</h2>
                    <p>Items that may need payment verification or collection follow-up</p>
                </div>
                <strong class="executive-amount">{{ number_format($liveAlerts->count()) }} active</strong>
            </div>
            <div class="executive-alerts mt-4">
                @forelse ($liveAlerts as $alert)
                    <div class="executive-alert executive-alert--{{ $alert['tone'] }}">
                        <div>
                            <strong>{{ $alert['title'] }}</strong>
                            <span>{{ $alert['message'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="executive-alert executive-alert--healthy">
                        <div>
                            <strong>No urgent sales alerts</strong>
                            <span>Payment verification and balance collections are currently clear.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="executive-collection-grid">
            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Outstanding balance aging</h2>
                        <p>Confirmed booking balances grouped by how long they are overdue</p>
                    </div>
                    <strong class="executive-amount">Php {{ number_format($outstandingAging->sum('amount'), 2) }}</strong>
                </div>
                <div class="executive-balance-list">
                    @foreach ($outstandingAging as $row)
                        <div class="executive-balance-row">
                            <span>{{ $row['label'] }}<small>{{ number_format($row['count']) }} booking(s)</small></span>
                            <strong>Php {{ number_format($row['amount'], 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Upcoming expected collections</h2>
                        <p>Open balances from confirmed bookings scheduled within the next 30 days</p>
                    </div>
                    <strong class="executive-amount">Php {{ number_format($upcomingCollections->sum('amount'), 2) }}</strong>
                </div>
                <div class="executive-balance-list">
                    @foreach ($upcomingCollections as $row)
                        <div class="executive-balance-row">
                            <span>{{ $row['label'] }}<small>{{ number_format($row['count']) }} booking(s)</small></span>
                            <strong>Php {{ number_format($row['amount'], 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="executive-card p-5 lg:p-6">
            <div class="executive-section-head">
                <div>
                    <h2>Space utilization and revenue efficiency</h2>
                    <p>Booked hours against 24/7 available room-hours, with verified revenue per booked hour</p>
                </div>
            </div>
            <div class="executive-utilization">
                <table>
                    <thead>
                        <tr>
                            <th>Space</th>
                            <th>Booked hours</th>
                            <th>Available hours</th>
                            <th>Utilization</th>
                            <th>Verified revenue</th>
                            <th>Revenue / hour</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roomUtilization as $row)
                            <tr>
                                <td><strong>{{ $row['label'] }}</strong></td>
                                <td>{{ number_format($row['booked_hours'], 1) }}</td>
                                <td>{{ number_format($row['available_hours'], 1) }}</td>
                                <td>
                                    <span class="executive-utilization__meter"><i style="width:{{ $row['utilization'] }}%"></i></span>
                                    {{ number_format($row['utilization'], 1) }}%
                                </td>
                                <td>Php {{ number_format($row['revenue'], 2) }}</td>
                                <td>Php {{ number_format($row['revenue_per_hour'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="executive-main-grid">
            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Collection performance</h2>
                        <p>Approved payments grouped by their verification date</p>
                    </div>
                    <strong class="executive-amount">Php {{ number_format($summary['gross_sales'], 2) }}</strong>
                </div>
                <div class="executive-chart">
                    @foreach ($trend as $point)
                        @php
                            $height = $maxTrend > 0
                                ? max(2, ($point['amount'] / $maxTrend) * 100)
                                : 2;
                        @endphp
                        <div class="executive-chart__item" title="{{ $point['label'] }} - Php {{ number_format($point['amount'], 2) }} - {{ $point['transactions'] }} transaction(s)">
                            <div class="executive-chart__track">
                                <div class="executive-chart__bar" style="height:{{ $height }}%"></div>
                            </div>
                            <span class="executive-chart__label">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="executive-card executive-summary p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Executive summary</h2>
                        <p>Key commercial figures for the period</p>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="executive-summary__row">
                        <span>New booked value</span>
                        <strong>Php {{ number_format($summary['booked_value'], 2) }}</strong>
                    </div>
                    <div class="executive-summary__row">
                        <span>Verified collections</span>
                        <strong>Php {{ number_format($summary['gross_sales'], 2) }}</strong>
                    </div>
                    <div class="executive-summary__row">
                        <span>Outstanding balance</span>
                        <strong>Php {{ number_format($summary['outstanding_balance'], 2) }}</strong>
                    </div>
                    <div class="executive-summary__row">
                        <span>Discounts granted</span>
                        <strong>Php {{ number_format($summary['discounts'], 2) }}</strong>
                    </div>
                    <div class="executive-summary__row">
                        <span>Average transaction</span>
                        <strong>Php {{ number_format($summary['average_transaction'], 2) }}</strong>
                    </div>
                </div>
            </article>
        </section>

        @php
            $spaceColors = [
                'Common Area' => '#17698a',
                'Private Room for Two' => '#ef6c24',
                'Private Room for Four' => '#25823a',
                'Conference Room' => '#19a9df',
            ];
            $monthlyColumns = collect([
                [
                    'label' => $spaceMonthlyComparison['previous_label'],
                    'values' => $spaceMonthlyComparison['rows']->pluck('previous', 'label'),
                ],
                [
                    'label' => $spaceMonthlyComparison['current_label'],
                    'values' => $spaceMonthlyComparison['rows']->pluck('current', 'label'),
                ],
            ]);
            $monthlyMax = max(1, (float) $monthlyColumns->max(fn ($column) => $column['values']->sum()));
            $weeklyMax = max(1, (float) $spaceWeeklySales->max('total'));
            $demandColors = ['#17698a', '#ef6c24', '#25823a', '#19a9df', '#8b6bb1'];
            $demandDayTotals = collect($demandHeatmap['days'])->mapWithKeys(fn ($day) => [
                $day => collect($demandHeatmap['rows'])->sum(fn ($row) => $row['cells'][$day]['bookings']),
            ]);
            $demandMax = max(1, (int) $demandDayTotals->max());
            $walkInSource = $bookingSourceCounts->firstWhere('key', 'admin');
            $walkInPercentage = (float) ($walkInSource['percentage'] ?? 0);
            $lifecycleColors = [
                'confirmed' => '#25823a',
                'pending' => '#c59b50',
                'cancelled' => '#17698a',
                'rescheduled' => '#ef6c24',
            ];
            $lifecycleStackMax = max(1, (int) $bookingLifecycle['rows']->sum('count'));
        @endphp

        <section class="executive-analytics-grid">
            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Monthly sales by space</h2>
                        <p>
                            {{ $spaceMonthlyComparison['current_label'] }} versus {{ $spaceMonthlyComparison['previous_label'] }}
                            using the same elapsed-day window
                        </p>
                    </div>
                </div>

                <div class="executive-graph">
                    <div class="executive-stacked-chart">
                        @foreach ($monthlyColumns as $column)
                            <div class="executive-stack-column">
                                <div class="executive-stack-column__plot">
                                    <div class="executive-stack-column__bar">
                                        @foreach ($spaceColors as $label => $color)
                                            @php
                                                $value = (float) ($column['values'][$label] ?? 0);
                                            @endphp
                                            <div
                                                class="executive-stack-segment"
                                                style="height:{{ ($value / $monthlyMax) * 100 }}%;background:{{ $color }}"
                                                title="{{ $column['label'] }} - {{ $label }}: Php {{ number_format($value, 2) }}"
                                            >{{ $value > 0 ? number_format($value, 2) : '' }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="executive-stack-column__label">{{ $column['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="executive-space-legend">
                        @foreach ($spaceColors as $label => $color)
                            <span><i style="background:{{ $color }}"></i>{{ $label }}</span>
                        @endforeach
                    </div>
                    <p class="executive-graph-note">{{ $spaceMonthlyComparison['previous_period'] }} versus {{ $spaceMonthlyComparison['current_period'] }}</p>
                </div>
            </article>

            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Weekly sales by space</h2>
                        <p>Verified collections grouped Monday to Sunday; up to the latest 12 weeks</p>
                    </div>
                    <strong class="executive-amount">{{ $spaceWeeklySales->count() }} week{{ $spaceWeeklySales->count() === 1 ? '' : 's' }}</strong>
                </div>

                <div class="executive-graph">
                    <div class="executive-stacked-chart executive-stacked-chart--weekly">
                        @foreach ($spaceWeeklySales as $week)
                            <div class="executive-stack-column">
                                <div class="executive-stack-column__plot">
                                    <div class="executive-stack-column__bar">
                                        @foreach ($spaceColors as $label => $color)
                                            @php
                                                $value = (float) ($week['spaces'][$label] ?? 0);
                                            @endphp
                                            <div
                                                class="executive-stack-segment"
                                                style="height:{{ ($value / $weeklyMax) * 100 }}%;background:{{ $color }}"
                                                title="{{ $week['label'] }} - {{ $label }}: Php {{ number_format($value, 2) }}"
                                            >{{ $value > 0 ? number_format($value, 0) : '' }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="executive-stack-column__label">{{ $week['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="executive-space-legend">
                        @foreach ($spaceColors as $label => $color)
                            <span><i style="background:{{ $color }}"></i>{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            </article>
        </section>

        <section class="executive-card p-5 lg:p-6">
            <div class="executive-section-head">
                <div>
                    <h2>Customer demand by day and time</h2>
                    <p>Confirmed and active booking demand across the selected period, including the complete 24/7 overnight window</p>
                </div>
                <strong class="executive-amount">
                    {{ number_format($demandHeatmap['booking_total']) }} bookings &middot;
                    {{ number_format($demandHeatmap['guest_total']) }} guests
                </strong>
            </div>

            <div class="executive-graph">
                <div class="executive-stacked-chart executive-stacked-chart--demand">
                    @foreach ($demandHeatmap['days'] as $day)
                        <div class="executive-stack-column">
                            <div class="executive-stack-column__plot">
                                <div class="executive-stack-column__bar">
                                    @foreach ($demandHeatmap['rows'] as $rowIndex => $row)
                                        @php
                                            $cell = $row['cells'][$day];
                                        @endphp
                                        <div
                                            class="executive-stack-segment"
                                            style="height:{{ ($cell['bookings'] / $demandMax) * 100 }}%;background:{{ $demandColors[$rowIndex] }}"
                                            title="{{ $day }} - {{ $row['label'] }}: {{ $cell['bookings'] }} booking(s), {{ $cell['guests'] }} guest(s)"
                                        >{{ $cell['bookings'] > 0 ? $cell['bookings'] : '' }}</div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="executive-stack-column__label">{{ $day }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="executive-space-legend">
                    @foreach ($demandHeatmap['rows'] as $rowIndex => $row)
                        <span><i style="background:{{ $demandColors[$rowIndex] }}"></i>{{ $row['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="executive-booking-grid">
            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Booking source</h2>
                        <p>Walk-in versus online/system bookings created in the selected period</p>
                    </div>
                </div>

                <div class="executive-donut-wrap">
                    <div
                        class="executive-donut"
                        style="background:conic-gradient(#315e3d 0 {{ $walkInPercentage }}%,#7d9bc4 {{ $walkInPercentage }}% 100%)"
                        aria-label="{{ number_format($walkInPercentage, 1) }} percent walk-in bookings"
                    ></div>
                    <div class="executive-source-list">
                        @foreach ($bookingSourceCounts as $sourceRow)
                            <div class="executive-source-list__row">
                                <span>{{ $sourceRow['label'] }}</span>
                                <strong>{{ number_format($sourceRow['count']) }} &middot; {{ number_format($sourceRow['percentage'], 1) }}%</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="executive-card p-5 lg:p-6">
                <div class="executive-section-head">
                    <div>
                        <h2>Booking lifecycle</h2>
                        <p>Status counts for bookings created in the period; rescheduled is tracked separately from final status</p>
                    </div>
                    <strong class="executive-amount">{{ number_format($bookingLifecycle['total']) }} total</strong>
                </div>

                <div class="executive-graph">
                    <div class="executive-stacked-chart executive-lifecycle-chart">
                        <div class="executive-stack-column">
                            <div class="executive-stack-column__plot">
                                <div class="executive-stack-column__bar">
                                    @foreach ($bookingLifecycle['rows'] as $row)
                                        <div
                                            class="executive-stack-segment"
                                            style="height:{{ ($row['count'] / $lifecycleStackMax) * 100 }}%;background:{{ $lifecycleColors[$row['key']] }}"
                                            title="{{ $row['label'] }}: {{ number_format($row['count']) }}"
                                        >{{ $row['count'] > 0 ? number_format($row['count']) : '' }}</div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="executive-stack-column__label">Selected period</div>
                        </div>
                    </div>
                    <div class="executive-space-legend">
                        @foreach ($bookingLifecycle['rows'] as $row)
                            <span><i style="background:{{ $lifecycleColors[$row['key']] }}"></i>{{ $row['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </article>
        </section>

        <section class="executive-mix">
            @foreach ([
                ['title' => 'Payment channel mix', 'subtitle' => 'Collected revenue by payment method', 'items' => $methodBreakdown],
                ['title' => 'Booking source mix', 'subtitle' => 'Online versus walk-in collections', 'items' => $sourceBreakdown],
                ['title' => 'Top revenue spaces', 'subtitle' => 'Highest collected sales by room', 'items' => $roomBreakdown],
            ] as $panel)
                <article class="executive-card p-5">
                    <div class="executive-section-head">
                        <div>
                            <h2>{{ $panel['title'] }}</h2>
                            <p>{{ $panel['subtitle'] }}</p>
                        </div>
                    </div>
                    <div class="executive-breakdown">
                        @forelse ($panel['items'] as $item)
                            <div>
                                <div class="executive-breakdown__top">
                                    <strong>{{ $item['label'] }}</strong>
                                    <span>Php {{ number_format($item['amount'], 2) }} &middot; {{ $item['transactions'] }}</span>
                                </div>
                                <div class="executive-progress mt-2">
                                    <span style="width:{{ min(100, ($item['amount'] / $maxBreakdown) * 100) }}%"></span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[.66rem] text-[#8a918a]">No verified sales in this reporting period.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>

        <section class="executive-card overflow-hidden">
            <div class="executive-section-head p-5 lg:px-6">
                <div>
                    <h2>Recent verified transactions</h2>
                    <p>Latest approved collections within the selected reporting period</p>
                </div>
                <a href="{{ route('admin.sections.payments') }}" class="executive-button executive-button--border">View all payments</a>
            </div>
            <div class="overflow-x-auto">
                <table class="executive-table min-w-[62rem]">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Customer</th>
                            <th>Source</th>
                            <th>Payment method</th>
                            <th>Verified by</th>
                            <th>Verified date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTransactions as $transaction)
                            <tr>
                                <td>
                                    <strong>#{{ $transaction['id'] }} &middot; {{ $transaction['type'] }}</strong>
                                    <small>{{ $transaction['reference'] }}</small>
                                </td>
                                <td>
                                    <strong>{{ $transaction['customer'] }}</strong>
                                    <small>Booking #{{ $transaction['booking_id'] }}</small>
                                </td>
                                <td>
                                    <span class="executive-badge {{ $transaction['source'] === 'Online' ? 'executive-badge--online' : '' }}">{{ $transaction['source'] }}</span>
                                </td>
                                <td>{{ $transaction['method'] }}</td>
                                <td>{{ $transaction['verified_by'] }}</td>
                                <td>{{ $transaction['verified_at'] }}</td>
                                <td class="text-right"><strong>Php {{ number_format($transaction['amount'], 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-[#8a918a]">No verified transactions found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
