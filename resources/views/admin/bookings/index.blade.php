@extends('layouts.admin')

@section('content')
    <style>
        .admin-bookings-page {
            display: grid;
            gap: 1rem;
        }

        .admin-bookings-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
        }

        .admin-bookings-toolbar__form {
            display: grid;
            gap: 0.85rem;
            width: 100%;
        }

        .admin-bookings-toolbar__actions,
        .admin-bookings-toolbar__filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
        }

        .admin-bookings-toolbar__tab,
        .admin-bookings-toolbar__chip {
            border: 1px solid #dfe6da;
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.65rem 1rem;
            color: #667267;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1;
        }

        .admin-bookings-toolbar__tab.is-active,
        .admin-bookings-toolbar__chip.is-active {
            border-color: #e2edd7;
            background: #eff6e5;
            color: #2f5830;
        }

        .admin-bookings-toolbar__search {
            min-width: 15rem;
            border: 1px solid #dfe6da;
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.78rem 1rem;
            color: #132320;
            font-size: 0.8rem;
            outline: none;
        }

        .admin-bookings-toolbar__select {
            min-width: 10.5rem;
            border: 1px solid #dfe6da;
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.74rem 0.95rem;
            color: #28452f;
            font-size: 0.78rem;
            font-weight: 600;
            outline: none;
        }

        .admin-bookings-toolbar__select--wide {
            min-width: 12rem;
        }

        .admin-bookings-toolbar__search-wrap {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
        }

        .admin-bookings-toolbar__search-button,
        .admin-bookings-toolbar__clear {
            border: 1px solid #dfe6da;
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.78rem 1rem;
            color: #28452f;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1;
        }

        .admin-bookings-toolbar__clear {
            color: #7a6c57;
        }

        .admin-bookings-toolbar__helper {
            color: #7f857c;
            font-size: 0.73rem;
            line-height: 1.45;
        }

        .admin-bookings-notify {
            position: relative;
        }

        .admin-bookings-notify__button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid #dfe6da;
            border-radius: 0.9rem;
            background: #fff;
            padding: 0.7rem 0.95rem;
            color: #28452f;
            font-size: 0.79rem;
            font-weight: 700;
            line-height: 1;
        }

        .admin-bookings-notify__badge {
            min-width: 1.2rem;
            border-radius: 999px;
            background: #44793b;
            padding: 0.2rem 0.38rem;
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
            text-align: center;
        }

        .admin-bookings-notify__panel {
            position: absolute;
            top: calc(100% + 0.55rem);
            right: 0;
            z-index: 30;
            width: min(24rem, calc(100vw - 2rem));
            overflow: hidden;
            border: 1px solid #dfe7d8;
            border-radius: 1.2rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 24px 60px rgba(19, 35, 32, 0.12);
            backdrop-filter: blur(16px);
        }

        .admin-bookings-notify__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.95rem 1rem 0.8rem;
            border-bottom: 1px solid #edf1ea;
            color: #728072;
            font-size: 0.72rem;
        }

        .admin-bookings-notify__header strong {
            color: #132320;
            font-size: 0.86rem;
        }

        .admin-bookings-notify__list {
            max-height: 23rem;
            overflow-y: auto;
        }

        .admin-bookings-notify__item,
        .admin-bookings-notify__empty {
            display: grid;
            gap: 0.35rem;
            padding: 0.95rem 1rem;
            border-bottom: 1px solid #f0f3ee;
        }

        .admin-bookings-notify__item {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .admin-bookings-notify__item:last-child,
        .admin-bookings-notify__empty:last-child {
            border-bottom: none;
        }

        .admin-bookings-notify__meta {
            display: grid;
            gap: 0.22rem;
        }

        .admin-bookings-notify__meta strong {
            color: #132320;
            font-size: 0.81rem;
            font-weight: 700;
        }

        .admin-bookings-notify__meta span {
            color: #516257;
            font-size: 0.74rem;
            line-height: 1.45;
        }

        .admin-bookings-notify__meta small,
        .admin-bookings-notify__time,
        .admin-bookings-notify__empty {
            color: #8b8d84;
            font-size: 0.7rem;
            line-height: 1.4;
        }

        .admin-bookings-card {
            overflow: hidden;
            border: 1px solid #dfe7d8;
            border-radius: 1.25rem;
            background: #fff;
        }

        .admin-bookings-empty {
            padding: 1.2rem 1rem;
            color: #7f857c;
            font-size: 0.82rem;
            text-align: center;
        }

        .admin-bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-bookings-table thead th {
            padding: 0.68rem 0.8rem;
            border-bottom: 1px solid #edf1ea;
            color: #b0a694;
            font-size: 0.69rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .admin-bookings-table tbody tr {
            cursor: pointer;
            transition: background 0.18s ease;
        }

        .admin-bookings-table tbody tr:hover {
            background: #fbfcfa;
        }

        .admin-bookings-table tbody td {
            padding: 0.62rem 0.8rem;
            border-bottom: 1px solid #edf1ea;
            color: #253833;
            font-size: 0.78rem;
            vertical-align: middle;
        }

        .admin-bookings-table tbody tr:last-child td {
            border-bottom: none;
        }

        .admin-bookings-table__reference {
            color: #47594f;
            font-size: 0.76rem;
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        .admin-bookings-table__customer {
            display: grid;
            gap: 0.08rem;
        }

        .admin-bookings-table__customer strong {
            color: #132320;
            font-size: 0.82rem;
            font-weight: 500;
        }

        .admin-bookings-table__customer span,
        .admin-bookings-table__details,
        .admin-bookings-table__date {
            color: #837c71;
            font-size: 0.72rem;
            line-height: 1.35;
        }

        .admin-bookings-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.38rem 0.72rem;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .admin-bookings-badge--booking {
            background: #e9f0ff;
            color: #3156cb;
        }

        .admin-bookings-badge--member {
            background: #edf6e8;
            color: #3f6a34;
        }

        .admin-bookings-badge--guest {
            background: #f5f4ee;
            color: #6f766f;
        }

        .admin-bookings-badge--confirmed,
        .admin-bookings-badge--paid {
            background: #eef8df;
            color: #3f6a34;
        }

        .admin-bookings-badge--cancelled,
        .admin-bookings-badge--rejected {
            background: #fde9e5;
            color: #b14635;
        }

        .admin-bookings-badge--pending,
        .admin-bookings-badge--partial {
            background: #f8efdd;
            color: #9a7832;
        }

        .admin-bookings-badge--progress {
            background: #e8f2ff;
            color: #3156cb;
        }

        .admin-bookings-amount {
            color: #132320;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .admin-bookings-scroll {
            overflow-x: auto;
        }

        .admin-bookings-scroll--fixed {
            overflow: hidden;
        }

        .admin-bookings-scroll--fixed .admin-bookings-table {
            table-layout: fixed;
        }

        .admin-bookings-scroll--fixed .admin-bookings-table thead,
        .admin-bookings-scroll--fixed .admin-bookings-table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .admin-bookings-scroll--fixed .admin-bookings-table tbody {
            display: block;
            max-height: 31.5rem;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .admin-bookings-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1.1rem;
        }

        .admin-bookings-modal.hidden {
            display: none;
        }

        .admin-bookings-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(18, 24, 21, 0.38);
            backdrop-filter: blur(10px);
        }

        .admin-bookings-modal__card {
            position: relative;
            z-index: 1;
            width: min(74rem, 100%);
            max-height: calc(100vh - 2.2rem);
            overflow-y: auto;
            border: 1px solid rgba(17, 52, 44, 0.1);
            border-radius: 1.45rem;
            background: rgba(255, 251, 245, 0.98);
            box-shadow: 0 28px 80px rgba(17, 28, 24, 0.18);
            padding: 1.3rem;
        }

        .admin-bookings-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .admin-bookings-modal__eyebrow {
            margin: 0;
            color: #9d7832;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-bookings-modal__title {
            margin: 0.38rem 0 0;
            color: #132320;
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .admin-bookings-modal__subtitle {
            margin: 0.32rem 0 0;
            color: #837d73;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .admin-bookings-modal__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            border: 1px solid rgba(17, 52, 44, 0.08);
            border-radius: 999px;
            background: #fff;
            color: #274239;
            font-size: 1.2rem;
            line-height: 1;
            text-decoration: none;
        }

        .admin-bookings-modal__grid {
            display: grid;
            gap: 1rem;
            margin-top: 1.1rem;
        }

        .admin-bookings-modal__panel {
            border: 1px solid #e5e9e1;
            border-radius: 1rem;
            background: #fff;
            padding: 1rem;
        }

        .admin-bookings-modal__panel-title {
            margin: 0;
            color: #132320;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .admin-bookings-modal__details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem 1rem;
            margin-top: 0.9rem;
        }

        .admin-bookings-modal__details dt {
            margin: 0 0 0.18rem;
            color: #9a948a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .admin-bookings-modal__details dd {
            margin: 0;
            color: #173029;
            font-size: 0.84rem;
            font-weight: 600;
            line-height: 1.55;
        }

        .admin-bookings-modal__booking-list {
            margin-top: 0.95rem;
            border: 1px solid #e7ece4;
            border-radius: 0.95rem;
            overflow: hidden;
        }

        .admin-bookings-modal__booking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-bookings-modal__booking-table thead th {
            padding: 0.72rem 0.8rem;
            border-bottom: 1px solid #edf1ea;
            background: #f8faf6;
            color: #b0a694;
            font-size: 0.67rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .admin-bookings-modal__booking-table tbody td {
            padding: 0.78rem 0.8rem;
            border-bottom: 1px solid #edf1ea;
            color: #253833;
            font-size: 0.76rem;
            vertical-align: top;
        }

        .admin-bookings-modal__booking-table tbody tr:last-child td {
            border-bottom: none;
        }

        .admin-bookings-modal__booking-reference {
            display: grid;
            gap: 0.12rem;
        }

        .admin-bookings-modal__booking-reference strong {
            color: #132320;
            font-size: 0.79rem;
            font-weight: 700;
        }

        .admin-bookings-modal__booking-reference span,
        .admin-bookings-modal__booking-room-list,
        .admin-bookings-modal__booking-proof-text {
            color: #837c71;
            font-size: 0.72rem;
            line-height: 1.45;
        }

        .admin-bookings-modal__chips {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.9rem;
        }

        .admin-bookings-modal__proof {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.8rem;
            padding: 0.46rem 0.72rem;
            background: #eff6ea;
            color: #2f6a42;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
        }

        .admin-bookings-modal__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ecefe8;
        }

        .admin-bookings-modal__actions.hidden {
            display: none;
        }

        .admin-bookings-modal__button {
            border: 1px solid rgba(17, 52, 44, 0.12);
            border-radius: 0.82rem;
            padding: 0.82rem 1.1rem;
            background: #fff;
            color: #173029;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }

        .admin-bookings-modal__button--primary {
            border-color: #44793b;
            background: #44793b;
            color: #fff;
        }

        .admin-bookings-modal__button--danger {
            border-color: #efc7bf;
            background: #fff5f3;
            color: #b14635;
        }

        .admin-bookings-modal__button[disabled] {
            opacity: 0.65;
            cursor: wait;
        }

        .admin-bookings-modal__notice {
            margin-top: 0.9rem;
            border: 1px solid #d9ebcf;
            border-radius: 0.85rem;
            background: #f4faea;
            padding: 0.75rem 0.9rem;
            color: #3f6a34;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .admin-bookings-modal__notice.hidden {
            display: none;
        }

        @media (max-width: 980px) {
            .admin-bookings-toolbar {
                align-items: stretch;
            }

            .admin-bookings-toolbar__form {
                gap: 0.75rem;
            }

            .admin-bookings-toolbar__actions {
                width: 100%;
                justify-content: space-between;
            }

            .admin-bookings-modal__details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 860px) {
            .admin-bookings-modal__booking-list {
                overflow-x: auto;
            }

            .admin-bookings-modal__booking-table {
                min-width: 42rem;
            }
        }

        @media (max-width: 760px) {
            .admin-bookings-toolbar__actions,
            .admin-bookings-toolbar__filters,
            .admin-bookings-toolbar__search-wrap {
                width: 100%;
            }

            .admin-bookings-toolbar__search {
                min-width: 100%;
            }

            .admin-bookings-toolbar__select {
                width: 100%;
            }

            .admin-bookings-table thead th,
            .admin-bookings-table tbody td {
                padding: 0.76rem 0.74rem;
            }

            .admin-bookings-modal {
                padding: 0.7rem;
            }

            .admin-bookings-modal__card {
                max-height: calc(100vh - 1.4rem);
                padding: 1rem;
                border-radius: 1.05rem;
            }

            .admin-bookings-modal__details {
                grid-template-columns: minmax(0, 1fr);
            }

            .admin-bookings-modal__actions form,
            .admin-bookings-modal__actions .admin-bookings-modal__button {
                width: 100%;
            }
        }
    </style>

    @php
        $summaryCount = $bookings->total();
        $canManageBookings = $adminUser->hasPermission('bookings.manage');
        $activeFilters = collect([
            ($filters['view'] ?? 'all') !== 'all' ? ucwords(str_replace('_', ' ', (string) $filters['view'])) : null,
            ($filters['type'] ?? 'all') !== 'all' ? ucfirst((string) $filters['type']) : null,
            filled($filters['search'] ?? '') ? 'Search: '.($filters['search']) : null,
        ])->filter()->values();
    @endphp

    <div class="admin-bookings-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">Booking approvals</p>
                <h1 class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em] text-[#132320]">Bookings</h1>
                <p class="mt-1 text-[0.84rem] text-[#8b897f]">Clean listing view for submitted reservations. Click a row to open the full details and review actions.</p>
            </div>

            @if ($canManageBookings)
                <a
                    href="{{ route('admin.bookings.create') }}"
                    class="inline-flex items-center justify-center rounded-[0.9rem] bg-[#44793b] px-4 py-2.5 text-[0.8rem] font-semibold text-white transition hover:bg-[#396733]"
                >
                    Add Booking
                </a>
            @endif
        </div>

        @if (session('admin_success'))
            <div class="rounded-[1rem] border border-[#d9ebcf] bg-[#f4faea] px-4 py-3 text-[0.82rem] font-semibold text-[#3f6a34]">
                {{ session('admin_success') }}
            </div>
        @endif

        <form method="GET" class="admin-bookings-toolbar" data-admin-bookings-filters>
            <input type="hidden" name="view" value="{{ $filters['view'] ?? 'all' }}" data-admin-bookings-filter-input="view">
            <div class="admin-bookings-toolbar__actions">
                <button type="button" class="admin-bookings-toolbar__tab {{ ($filters['view'] ?? 'all') === 'all' ? 'is-active' : '' }}" data-admin-bookings-filter-set="view" data-filter-value="all">All bookings</button>
                <span class="admin-bookings-toolbar__chip is-active" data-admin-bookings-results>{{ $summaryCount }} result{{ $summaryCount !== 1 ? 's' : '' }}</span>
                <select name="view" class="admin-bookings-toolbar__select admin-bookings-toolbar__select--wide" data-admin-bookings-view>
                    <option value="all" @selected(($filters['view'] ?? 'all') === 'all')>All statuses</option>
                    <option value="pending" @selected(($filters['view'] ?? '') === 'pending')>Pending review</option>
                    <option value="paid" @selected(($filters['view'] ?? '') === 'paid')>Paid</option>
                    <option value="with_balance" @selected(($filters['view'] ?? '') === 'with_balance')>With balance</option>
                </select>
                <select name="type" class="admin-bookings-toolbar__select" data-admin-bookings-type>
                    <option value="all" @selected(($filters['type'] ?? 'all') === 'all')>All types</option>
                    <option value="member" @selected(($filters['type'] ?? '') === 'member')>Members</option>
                    <option value="guest" @selected(($filters['type'] ?? '') === 'guest')>Guests</option>
                </select>
                <div class="admin-bookings-notify" data-admin-bookings-notify>
                    <button type="button" class="admin-bookings-notify__button" data-admin-bookings-notify-toggle aria-expanded="false">
                        <span>Notifications</span>
                        @if (($activityUnreadCount ?? 0) > 0)
                            <span class="admin-bookings-notify__badge" data-admin-bookings-notify-badge>{{ $activityUnreadCount }}</span>
                        @endif
                    </button>

                    <div class="admin-bookings-notify__panel hidden" data-admin-bookings-notify-panel>
                        <div class="admin-bookings-notify__header">
                            <strong>Booking notifications</strong>
                            <span>{{ ($activities ?? collect())->count() }} recent</span>
                        </div>

                        <div class="admin-bookings-notify__list">
                            @forelse (($activities ?? collect()) as $activity)
                                <button
                                    type="button"
                                    class="admin-bookings-notify__item"
                                    data-admin-bookings-notify-item
                                    data-booking-header-id="{{ $activity->booking_header_id ?? '' }}"
                                >
                                    <div class="admin-bookings-notify__meta">
                                        <strong>{{ $activity->event_label }}</strong>
                                        <span>{{ $activity->message }}</span>
                                        <small>
                                            {{ $activity->customer_name ?: 'Customer' }}
                                            @if ($activity->room_name)
                                                • {{ $activity->room_name }}
                                            @endif
                                            @if ($activity->booking_date)
                                                • {{ $activity->booking_date->format('M j, Y') }}
                                            @endif
                                            @if ($activity->time_range)
                                                • {{ $activity->time_range }}
                                            @endif
                                        </small>
                                    </div>
                                    <time class="admin-bookings-notify__time">{{ optional($activity->created_at)->diffForHumans() }}</time>
                                </button>
                            @empty
                                <div class="admin-bookings-notify__empty">No booking notifications yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="admin-bookings-toolbar__search" placeholder="Search ID, reference, name, email, or phone..." data-admin-bookings-search>
                <button type="submit" class="admin-bookings-toolbar__search-button">Search</button>
                <button type="button" class="admin-bookings-toolbar__clear" data-admin-bookings-clear>Clear</button>
                @if ($activeFilters->isNotEmpty())
                    <span class="admin-bookings-toolbar__helper">Active: {{ $activeFilters->implode(' | ') }}</span>
                @else
                    <span class="admin-bookings-toolbar__helper">Use the quick filters for fast review without losing pagination.</span>
                @endif
            </div>
        </form>

        <div class="admin-bookings-card">
            <div class="admin-bookings-scroll admin-bookings-scroll--fixed">
                <table class="admin-bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Source</th>
                            <th>Details</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody data-admin-bookings-table-body>
                        @forelse ($bookings as $group)
                            <tr
                                data-admin-booking-open
                                data-id="{{ $group['id'] }}"
                                data-reference="{{ $group['reference'] }}"
                                data-name="{{ $group['customer_name'] }}"
                                data-email="{{ $group['email'] }}"
                                data-phone="{{ $group['phone'] }}"
                                data-booking-type="{{ $group['booking_type'] }}"
                                data-payment-method="{{ $group['payment_method'] }}"
                                data-total="{{ $group['total_amount'] }}"
                                data-downpayment="{{ $group['downpayment_amount'] }}"
                                data-balance="{{ $group['balance_amount'] }}"
                                data-status="{{ $group['status'] }}"
                                data-status-class="{{ $group['status_class'] }}"
                                data-payment-status="{{ $group['payment_status'] }}"
                                data-payment-status-key="{{ $group['payment_status_key'] }}"
                                data-payment-status-class="{{ $group['payment_status_class'] }}"
                                data-booking-count="{{ $group['booking_count'] }}"
                                data-slot-count="{{ $group['slot_count'] }}"
                                data-bookings='@json($group['bookings'])'
                            >
                                <td class="admin-bookings-table__reference">#{{ $group['id'] }}</td>
                                <td>
                                    <div class="admin-bookings-table__customer">
                                        <strong>{{ $group['customer_name'] }}</strong>
                                        <span>{{ $group['email'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-bookings-badge admin-bookings-badge--booking">{{ $group['booking_type'] }}</span>
                                </td>
                                <td class="admin-bookings-table__details">
                                    @if (! empty($group['has_long_stay']) && ! empty($group['preview_summary']))
                                        {{ $group['preview_summary'] }}
                                    @elseif (! empty($group['preview_rooms']))
                                        {{ implode(', ', $group['preview_rooms']) }} - {{ $group['slot_count'] }} slot{{ $group['slot_count'] !== 1 ? 's' : '' }}
                                    @else
                                        Room booking
                                    @endif
                                </td>
                                <td class="admin-bookings-table__date">
                                    {{ $group['latest_date'] }}
                                </td>
                                <td class="admin-bookings-amount">{{ $group['total_amount'] }}</td>
                                <td>
                                    <span class="admin-bookings-badge admin-bookings-badge--member">
                                        {{ $group['payment_method'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-bookings-badge {{ $group['payment_status_class'] }}">
                                        {{ $group['payment_status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="admin-bookings-empty">No bookings matched your current search and filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $bookings->links() }}
        </div>
    </div>

    <div class="admin-bookings-modal hidden" data-admin-bookings-modal>
        <button type="button" class="admin-bookings-modal__backdrop" data-admin-bookings-close aria-label="Close booking modal"></button>

        <div class="admin-bookings-modal__card">
            <div class="admin-bookings-modal__top">
                <div>
                    <p class="admin-bookings-modal__eyebrow" data-admin-booking-reference>Reference</p>
                    <h2 class="admin-bookings-modal__title" data-admin-booking-name>Customer</h2>
                    <p class="admin-bookings-modal__subtitle" data-admin-booking-subtitle>Booking details</p>
                </div>

                <button type="button" class="admin-bookings-modal__close" data-admin-bookings-close aria-label="Close booking modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="admin-bookings-modal__grid">
                <section class="admin-bookings-modal__panel">
                    <h3 class="admin-bookings-modal__panel-title">Customer details</h3>

                    <dl class="admin-bookings-modal__details">
                        <div>
                            <dt>Email</dt>
                            <dd data-admin-booking-email></dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd data-admin-booking-phone></dd>
                        </div>
                        <div>
                            <dt>Booking type</dt>
                            <dd data-admin-booking-type></dd>
                        </div>
                        <div>
                            <dt>Payment method</dt>
                            <dd data-admin-booking-payment-method></dd>
                        </div>
                        <div>
                            <dt>Total amount</dt>
                            <dd data-admin-booking-total></dd>
                        </div>
                        <div>
                            <dt>Downpayment</dt>
                            <dd data-admin-booking-downpayment></dd>
                        </div>
                        <div>
                            <dt>Balance</dt>
                            <dd data-admin-booking-balance></dd>
                        </div>
                    </dl>

                    <div class="admin-bookings-modal__chips">
                        <span class="admin-bookings-badge" data-admin-booking-status></span>
                        <span class="admin-bookings-badge" data-admin-booking-payment-status></span>
                    </div>

                    <a href="#" target="_blank" class="admin-bookings-modal__proof hidden" data-admin-booking-proof>
                        View payment proof
                    </a>

                    <div class="admin-bookings-modal__notice hidden" data-admin-booking-wifi-panel>
                        <strong data-admin-booking-wifi-code>HYVE-WIFI</strong><br>
                        <span data-admin-booking-wifi-window>Access window</span><br>
                        <span data-admin-booking-wifi-meta>Status</span>
                    </div>
                </section>

                <section class="admin-bookings-modal__panel">
                    <h3 class="admin-bookings-modal__panel-title">Customer bookings</h3>
                    <div class="admin-bookings-modal__notice hidden" data-admin-booking-notice></div>
                    <div class="admin-bookings-modal__booking-list" data-admin-booking-slots></div>
                </section>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-admin-bookings-modal]');
            const notifyWrap = document.querySelector('[data-admin-bookings-notify]');
            const filterForm = document.querySelector('[data-admin-bookings-filters]');
            const resultsChip = document.querySelector('[data-admin-bookings-results]');
            const tableBody = document.querySelector('[data-admin-bookings-table-body]');

            if (!modal) {
                return;
            }

                    const status = modal.querySelector('[data-admin-booking-status]');
                    const paymentStatus = modal.querySelector('[data-admin-booking-payment-status]');
                    const slotsWrap = modal.querySelector('[data-admin-booking-slots]');
                    const notice = modal.querySelector('[data-admin-booking-notice]');
                    const csrfToken = @json(csrf_token());
                    const bookingsFeedUrl = @json(route('admin.bookings.feed'));
                    const readNotificationsUrl = @json(route('admin.bookings.notifications.read'));
                    const notificationsFeedUrl = @json(route('admin.bookings.notifications.feed'));
                    const bookingSummaryUrlTemplate = @json(route('admin.bookings.summary', ['bookingHeader' => '__BOOKING__']));
                    let activeRow = null;
                    let currentBookings = [];
                    let notificationsMarkedRead = false;

                    if (filterForm) {
                        const searchInput = filterForm.querySelector('[data-admin-bookings-search]');
                        const clearButton = filterForm.querySelector('[data-admin-bookings-clear]');
                        const viewSelect = filterForm.querySelector('[data-admin-bookings-view]');
                        const typeSelect = filterForm.querySelector('[data-admin-bookings-type]');

                        filterForm.querySelectorAll('[data-admin-bookings-filter-set]').forEach((button) => {
                            button.addEventListener('click', () => {
                                const group = button.getAttribute('data-admin-bookings-filter-set');
                                const value = button.getAttribute('data-filter-value') || 'all';
                                const input = filterForm.querySelector(`[data-admin-bookings-filter-input="${group}"]`);

                                if (!input) {
                                    return;
                                }

                                input.value = value;
                                filterForm.submit();
                            });
                        });

                        viewSelect?.addEventListener('change', () => {
                            filterForm.submit();
                        });

                        typeSelect?.addEventListener('change', () => {
                            filterForm.submit();
                        });

                        clearButton?.addEventListener('click', () => {
                            filterForm.querySelectorAll('[data-admin-bookings-filter-input]').forEach((input) => {
                                input.value = 'all';
                            });

                            if (viewSelect) {
                                viewSelect.value = 'all';
                            }

                            if (typeSelect) {
                                typeSelect.value = 'all';
                            }

                            if (searchInput) {
                                searchInput.value = '';
                            }

                            filterForm.submit();
                        });
                    }

                    const applyChipClass = (element, className) => {
                        if (!element) return;
                        element.className = `admin-bookings-badge ${className}`.trim();
                    };

                    const setNotice = (message = '', isVisible = false) => {
                        if (!notice) return;
                        notice.textContent = message;
                        notice.classList.toggle('hidden', !isVisible);
                    };

                    const notificationMeta = (activity) => {
                        const parts = [activity.customer_name || 'Customer'];

                        if (activity.room_name) {
                            parts.push(activity.room_name);
                        }

                        if (activity.booking_date) {
                            parts.push(activity.booking_date);
                        }

                        if (activity.time_range) {
                            parts.push(activity.time_range);
                        }

                        return parts.join(' | ');
                    };

                    const renderNotificationItems = (activities = []) => {
                        if (!notifyWrap) {
                            return;
                        }

                        const notifyList = notifyWrap.querySelector('.admin-bookings-notify__list');

                        if (!notifyList) {
                            return;
                        }

                        if (!activities.length) {
                            notifyList.innerHTML = '<div class="admin-bookings-notify__empty">No booking notifications yet.</div>';
                            return;
                        }

                        notifyList.innerHTML = activities.map((activity) => `
                            <button
                                type="button"
                                class="admin-bookings-notify__item"
                                data-admin-bookings-notify-item
                                data-booking-header-id="${escapeHtml(activity.booking_header_id ?? '')}"
                            >
                                <div class="admin-bookings-notify__meta">
                                    <strong>${activity.event_label ?? 'Booking update'}</strong>
                                    <span>${activity.message ?? ''}</span>
                                    <small>${notificationMeta(activity)}</small>
                                </div>
                                <time class="admin-bookings-notify__time">${activity.created_at_human ?? '--'}</time>
                            </button>
                        `).join('');
                    };

                    const applyBookingRowDataset = (row, group) => {
                        row.dataset.id = String(group.id ?? '');
                        row.dataset.reference = String(group.reference ?? '');
                        row.dataset.name = String(group.customer_name ?? '');
                        row.dataset.email = String(group.email ?? '');
                        row.dataset.phone = String(group.phone ?? '');
                        row.dataset.bookingType = String(group.booking_type ?? '');
                        row.dataset.paymentMethod = String(group.payment_method ?? '');
                        row.dataset.total = String(group.total_amount ?? '');
                        row.dataset.downpayment = String(group.downpayment_amount ?? '');
                        row.dataset.balance = String(group.balance_amount ?? '');
                        row.dataset.status = String(group.status ?? '');
                        row.dataset.statusClass = String(group.status_class ?? '');
                        row.dataset.paymentStatus = String(group.payment_status ?? '');
                        row.dataset.paymentStatusKey = String(group.payment_status_key ?? '');
                        row.dataset.paymentStatusClass = String(group.payment_status_class ?? '');
                        row.dataset.bookingCount = String(group.booking_count ?? '');
                        row.dataset.slotCount = String(group.slot_count ?? '');
                        row.dataset.wifiVoucher = JSON.stringify(group.wifi_voucher || null);
                        row.dataset.bookings = JSON.stringify(group.bookings || []);

                        return row;
                    };

                    const openBookingFromNotification = async (bookingHeaderId) => {
                        if (!bookingHeaderId) {
                            return;
                        }

                        const existingRow = document.querySelector(`[data-admin-booking-open][data-id="${String(bookingHeaderId)}"]`);

                        if (existingRow) {
                            openBookingRow(existingRow);
                            return;
                        }

                        try {
                            const response = await fetch(bookingSummaryUrlTemplate.replace('__BOOKING__', encodeURIComponent(String(bookingHeaderId))), {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                return;
                            }

                            const payload = await response.json();
                            const booking = payload.booking || null;

                            if (!booking) {
                                return;
                            }

                            const tempRow = applyBookingRowDataset(document.createElement('tr'), booking);
                            openBookingRow(tempRow);
                        } catch (error) {
                            // Ignore notification click failures quietly.
                        }
                    };

                    const syncNotificationBadge = (unreadCount = 0) => {
                        if (!notifyWrap) {
                            return;
                        }

                        const notifyButton = notifyWrap.querySelector('[data-admin-bookings-notify-toggle]');
                        let badge = notifyWrap.querySelector('[data-admin-bookings-notify-badge]');

                        if (!notifyButton) {
                            return;
                        }

                        if (unreadCount > 0) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'admin-bookings-notify__badge';
                                badge.setAttribute('data-admin-bookings-notify-badge', '');
                                notifyButton.appendChild(badge);
                            }

                            badge.textContent = String(unreadCount);
                            notificationsMarkedRead = false;
                            return;
                        }

                        badge?.remove();
                    };

                    const pollNotifications = async () => {
                        if (!notifyWrap) {
                            return;
                        }

                        try {
                            const response = await fetch(notificationsFeedUrl, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                return;
                            }

                            const payload = await response.json();
                            renderNotificationItems(Array.isArray(payload.activities) ? payload.activities : []);
                            syncNotificationBadge(Number(payload.unread_count || 0));
                        } catch (error) {
                            // Keep the existing list if polling fails momentarily.
                        }
                    };

                    const resultLabel = (total = 0) => `${total} result${Number(total) === 1 ? '' : 's'}`;

                    const escapeHtml = (value) => String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');

                    const bookingRowMarkup = (group) => {
                        const preview = Array.isArray(group.preview_rooms) ? group.preview_rooms.filter(Boolean) : [];
                        const detailText = preview.length
                            ? `${escapeHtml(preview.join(', '))} - ${group.slot_count} slot${Number(group.slot_count) !== 1 ? 's' : ''}`
                            : 'Room booking';

                        return `
                            <tr
                                data-admin-booking-open
                                data-id="${escapeHtml(group.id)}"
                                data-reference="${escapeHtml(group.reference)}"
                                data-name="${escapeHtml(group.customer_name)}"
                                data-email="${escapeHtml(group.email)}"
                                data-phone="${escapeHtml(group.phone)}"
                                data-booking-type="${escapeHtml(group.booking_type)}"
                                data-payment-method="${escapeHtml(group.payment_method)}"
                                data-total="${escapeHtml(group.total_amount)}"
                                data-downpayment="${escapeHtml(group.downpayment_amount)}"
                                data-balance="${escapeHtml(group.balance_amount)}"
                                data-status="${escapeHtml(group.status)}"
                                data-status-class="${escapeHtml(group.status_class)}"
                                data-payment-status="${escapeHtml(group.payment_status)}"
                                data-payment-status-key="${escapeHtml(group.payment_status_key || '')}"
                                data-payment-status-class="${escapeHtml(group.payment_status_class)}"
                                data-booking-count="${escapeHtml(group.booking_count)}"
                                data-slot-count="${escapeHtml(group.slot_count)}"
                                data-wifi-voucher='${escapeHtml(JSON.stringify(group.wifi_voucher || null))}'
                                data-bookings='${escapeHtml(JSON.stringify(group.bookings || []))}'
                            >
                                <td class="admin-bookings-table__reference">#${escapeHtml(group.id)}</td>
                                <td>
                                    <div class="admin-bookings-table__customer">
                                        <strong>${escapeHtml(group.customer_name)}</strong>
                                        <span>${escapeHtml(group.email)}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-bookings-badge admin-bookings-badge--booking">${escapeHtml(group.booking_type)}</span>
                                </td>
                                <td class="admin-bookings-table__details">${detailText}</td>
                                <td class="admin-bookings-table__date">${escapeHtml(group.latest_date)}</td>
                                <td class="admin-bookings-amount">${escapeHtml(group.total_amount)}</td>
                                <td>
                                    <span class="admin-bookings-badge admin-bookings-badge--member">${escapeHtml(group.payment_method)}</span>
                                </td>
                                <td>
                                    <span class="admin-bookings-badge ${escapeHtml(group.payment_status_class)}">${escapeHtml(group.payment_status)}</span>
                                </td>
                            </tr>
                        `;
                    };

                    const openBookingRow = (row) => {
                        activeRow = row;
                        modal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                        setNotice('', false);

                        const modalReference = row.dataset.reference
                            ? `Reference ${row.dataset.reference}`
                            : (row.dataset.id ? `Booking ID #${row.dataset.id}` : 'Reference');

                        modal.querySelector('[data-admin-booking-reference]').textContent = modalReference;
                        modal.querySelector('[data-admin-booking-name]').textContent = row.dataset.name || 'Customer';
                        modal.querySelector('[data-admin-booking-subtitle]').textContent = `${row.dataset.email || ''} - ${row.dataset.phone || ''}`;
                        modal.querySelector('[data-admin-booking-email]').textContent = row.dataset.email || '--';
                        modal.querySelector('[data-admin-booking-phone]').textContent = row.dataset.phone || '--';
                        modal.querySelector('[data-admin-booking-type]').textContent = row.dataset.bookingType || '--';
                        modal.querySelector('[data-admin-booking-payment-method]').textContent = row.dataset.paymentMethod || '--';
                        modal.querySelector('[data-admin-booking-total]').textContent = row.dataset.total || '--';
                        modal.querySelector('[data-admin-booking-downpayment]').textContent = row.dataset.downpayment || '--';
                        modal.querySelector('[data-admin-booking-balance]').textContent = row.dataset.balance || '--';

                        status.textContent = row.dataset.status || 'Pending';
                        paymentStatus.textContent = row.dataset.paymentStatus || 'Pending';
                        applyChipClass(status, row.dataset.statusClass || 'admin-bookings-badge--pending');
                        applyChipClass(paymentStatus, row.dataset.paymentStatusClass || 'admin-bookings-badge--pending');

                        slotsWrap.innerHTML = '';
                        const wifiPanel = modal.querySelector('[data-admin-booking-wifi-panel]');
                        const wifiCode = modal.querySelector('[data-admin-booking-wifi-code]');
                        const wifiWindow = modal.querySelector('[data-admin-booking-wifi-window]');
                        const wifiMeta = modal.querySelector('[data-admin-booking-wifi-meta]');

                        let bookings = [];
                        try {
                            bookings = JSON.parse(row.dataset.bookings || '[]');
                        } catch (error) {
                            bookings = [];
                        }

                        let wifiVoucher = null;
                        try {
                            wifiVoucher = JSON.parse(row.dataset.wifiVoucher || 'null');
                        } catch (error) {
                            wifiVoucher = null;
                        }

                        if (wifiVoucher && wifiPanel && wifiCode && wifiWindow && wifiMeta) {
                            wifiCode.textContent = `WiFi voucher: ${wifiVoucher.code || '--'}`;
                            wifiWindow.textContent = `${wifiVoucher.valid_from || '--'} to ${wifiVoucher.valid_until || '--'}`;
                            wifiMeta.textContent = `${wifiVoucher.status_label || 'Ready'} - ${wifiVoucher.sync_status || 'Waiting for MikroTik device'}`;
                            wifiPanel.classList.remove('hidden');
                        } else {
                            wifiPanel?.classList.add('hidden');
                        }

                        currentBookings = bookings;
                        renderBookings();
                    };

                    const bindBookingRowEvents = () => {
                        document.querySelectorAll('[data-admin-booking-open]').forEach((row) => {
                            if (row.dataset.boundClick === 'true') {
                                return;
                            }

                            row.dataset.boundClick = 'true';
                            row.addEventListener('click', () => openBookingRow(row));
                        });
                    };

                    const renderBookingsTable = (bookings = [], total = null) => {
                        if (!tableBody) {
                            return;
                        }

                        const activeBookingId = !modal.classList.contains('hidden')
                            ? (activeRow?.dataset?.id || '')
                            : '';

                        if (!bookings.length) {
                            tableBody.innerHTML = '<tr><td colspan="8" class="admin-bookings-empty">No bookings matched your current search and filters.</td></tr>';
                        } else {
                            tableBody.innerHTML = bookings.map((booking) => bookingRowMarkup(booking)).join('');
                        }

                        if (resultsChip && total !== null) {
                            resultsChip.textContent = resultLabel(total);
                        }

                        bindBookingRowEvents();

                        if (activeBookingId) {
                            const refreshedRow = document.querySelector(`[data-admin-booking-open][data-id="${activeBookingId}"]`);

                            if (refreshedRow) {
                                openBookingRow(refreshedRow);
                            }
                        }
                    };

                    const pollBookings = async () => {
                        if (!tableBody || !filterForm) {
                            return;
                        }

                        try {
                            const params = new URLSearchParams(new FormData(filterForm));
                            const response = await fetch(`${bookingsFeedUrl}?${params.toString()}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                return;
                            }

                            const payload = await response.json();
                            renderBookingsTable(Array.isArray(payload.bookings) ? payload.bookings : [], Number(payload.total || 0));
                        } catch (error) {
                            // Keep the current table if polling fails.
                        }
                    };

                    const buildFinalStatus = (booking) => {
                        const bookingStatus = String(booking.status || '').toLowerCase();
                        const paymentState = String(booking.payment_status_key || booking.payment_status || '').toLowerCase();

                        if (paymentState === 'rejected' || bookingStatus === 'cancelled') {
                            return {
                                label: 'Rejected',
                                className: 'admin-bookings-badge--rejected',
                            };
                        }

                        if (paymentState === 'paid') {
                            return {
                                label: 'Fully Paid',
                                className: 'admin-bookings-badge--paid',
                            };
                        }

                        if (paymentState === 'partially_paid' || paymentState === 'partial') {
                            return {
                                label: 'Partially Paid',
                                className: 'admin-bookings-badge--partial',
                            };
                        }

                        if (bookingStatus === 'confirmed') {
                            return {
                                label: 'Confirmed',
                                className: 'admin-bookings-badge--confirmed',
                            };
                        }

                        return {
                            label: booking.status || booking.payment_status || 'Pending',
                            className: booking.status_class || booking.payment_status_class || 'admin-bookings-badge--pending',
                        };
                    };

                    const rebuildSummary = () => {
                        if (!activeRow) {
                            return;
                        }

                        const finalStatuses = currentBookings.map((booking) => buildFinalStatus(booking).label.toLowerCase());
                        const paymentStatuses = currentBookings.map((booking) => String(booking.payment_status_key || booking.payment_status || '').toLowerCase());

                        let nextStatus = 'Pending';
                        let nextStatusClass = 'admin-bookings-badge--pending';

                        if (finalStatuses.length && finalStatuses.every((label) => label === 'approved')) {
                            nextStatus = 'Confirmed';
                            nextStatusClass = 'admin-bookings-badge--confirmed';
                        } else if (finalStatuses.length && finalStatuses.every((label) => label === 'rejected')) {
                            nextStatus = 'Rejected';
                            nextStatusClass = 'admin-bookings-badge--rejected';
                        } else if (finalStatuses.some((label) => label === 'approved' || label === 'rejected')) {
                            nextStatus = 'Mixed';
                            nextStatusClass = 'admin-bookings-badge--partial';
                        }

                        let nextPayment = 'Waiting Payment';
                        let nextPaymentClass = 'admin-bookings-badge--pending';

                        if (paymentStatuses.length && paymentStatuses.every((value) => value === 'paid')) {
                            nextPayment = 'Fully Paid';
                            nextPaymentClass = 'admin-bookings-badge--paid';
                        } else if (paymentStatuses.some((value) => value === 'partially_paid' || value === 'partial')) {
                            nextPayment = 'Partially Paid';
                            nextPaymentClass = 'admin-bookings-badge--partial';
                        } else if (paymentStatuses.some((value) => value === 'pending_balance_verification')) {
                            nextPayment = 'Payment Submitted';
                            nextPaymentClass = 'admin-bookings-badge--pending';
                        } else if (paymentStatuses.length && paymentStatuses.every((value) => value === 'rejected')) {
                            nextPayment = 'Payment Rejected';
                            nextPaymentClass = 'admin-bookings-badge--rejected';
                        }

                        status.textContent = nextStatus;
                        paymentStatus.textContent = nextPayment;
                        applyChipClass(status, nextStatusClass);
                        applyChipClass(paymentStatus, nextPaymentClass);

                        activeRow.dataset.status = nextStatus;
                        activeRow.dataset.statusClass = nextStatusClass;
                        activeRow.dataset.paymentStatus = nextPayment;
                        activeRow.dataset.paymentStatusKey = paymentStatuses[0] || '';
                        activeRow.dataset.paymentStatusClass = nextPaymentClass;
                        activeRow.dataset.bookings = JSON.stringify(currentBookings);
                    };

                    const renderBookings = () => {
                        if (!currentBookings.length) {
                            slotsWrap.innerHTML = '<div class="px-4 py-4 text-[0.76rem] text-[#817b71]">No booked reservations found for this customer.</div>';
                            return;
                        }

                        const rows = currentBookings.map((booking) => {
                            const finalStatus = buildFinalStatus(booking);
                            const proofButton = booking.proof_visible && booking.proof
                                ? `<a href="${booking.proof}" target="_blank" class="admin-bookings-modal__proof">View proof</a>`
                                : '<span class="admin-bookings-modal__booking-proof-text">No proof</span>';

                            const actionButtons = booking.can_review
                                ? `
                                    <div class="admin-bookings-modal__actions">
                                        <button type="button" class="admin-bookings-modal__button admin-bookings-modal__button--primary" data-admin-booking-action="approve" data-booking-id="${booking.id}" data-url="${booking.approve_url}">Approve</button>
                                        <button type="button" class="admin-bookings-modal__button admin-bookings-modal__button--danger" data-admin-booking-action="reject" data-booking-id="${booking.id}" data-url="${booking.reject_url}">Reject</button>
                                    </div>
                                `
                                : '';

                            const progressButtons = !booking.can_review
                                ? `
                                    <div class="admin-bookings-modal__actions">
                                        ${booking.can_start ? `<button type="button" class="admin-bookings-modal__button admin-bookings-modal__button--primary" data-admin-booking-action="start" data-booking-id="${booking.id}" data-url="${booking.start_url}">Start</button>` : ''}
                                        ${booking.can_end ? `<button type="button" class="admin-bookings-modal__button" data-admin-booking-action="end" data-booking-id="${booking.id}" data-url="${booking.end_url}">End</button>` : ''}
                                    </div>
                                `
                                : '';

                            return `
                                <tr>
                                    <td>
                                        <div class="admin-bookings-modal__booking-reference">
                                            <strong>${booking.reference ?? 'Reference'}</strong>
                                            <span>${booking.created_at ?? '--'}</span>
                                        </div>
                                    </td>
                                    <td class="admin-bookings-modal__booking-room-list">
                                        ${booking.room ?? 'Room'} - ${booking.date ?? '--'} - ${booking.time ?? '--'}
                                        <br><span>Scheduled start: ${booking.scheduled_start ?? '--'}</span>
                                        <br><span>Scheduled end: ${booking.scheduled_end ?? '--'}</span>
                                        <br><span>Actual start: ${booking.actual_start ?? '--'}</span>
                                        <br><span>Actual end: ${booking.actual_end ?? '--'}</span>
                                    </td>
                                    <td>
                                        <div class="admin-bookings-table__customer">
                                            <strong>${booking.amount ?? '--'}</strong>
                                            <span>Header total: ${booking.header_total ?? '--'}</span>
                                            <span>Down: ${booking.downpayment ?? '--'} | Bal: ${booking.balance ?? '--'}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="admin-bookings-table__customer">
                                            <strong>${booking.payment_method ?? '--'}</strong>
                                            <span>${booking.booking_type ?? '--'}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="admin-bookings-modal__chips">
                                            <span class="admin-bookings-badge ${finalStatus.className}">${finalStatus.label}</span>
                                            <span class="admin-bookings-badge ${booking.progress_class || 'admin-bookings-badge--progress'}">${booking.progress || 'Scheduled'}</span>
                                        </div>
                                    </td>
                                    <td>
                                        ${proofButton}
                                        ${actionButtons}
                                        ${progressButtons}
                                    </td>
                                </tr>
                            `;
                        }).join('');

                        slotsWrap.innerHTML = `
                            <table class="admin-bookings-modal__booking-table">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Booked line</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        `;
                    };

            if (notifyWrap) {
                const notifyToggle = notifyWrap.querySelector('[data-admin-bookings-notify-toggle]');
                const notifyPanel = notifyWrap.querySelector('[data-admin-bookings-notify-panel]');

                const closeNotifyPanel = () => {
                    if (!notifyPanel || !notifyToggle) {
                        return;
                    }

                    notifyPanel.classList.add('hidden');
                    notifyToggle.setAttribute('aria-expanded', 'false');
                };

                notifyToggle?.addEventListener('click', async (event) => {
                    event.stopPropagation();

                    if (!notifyPanel) {
                        return;
                    }

                    const isHidden = notifyPanel.classList.contains('hidden');
                    notifyPanel.classList.toggle('hidden', !isHidden);
                    notifyToggle.setAttribute('aria-expanded', String(isHidden));

                    if (isHidden && !notificationsMarkedRead) {
                        notificationsMarkedRead = true;

                        try {
                            const response = await fetch(readNotificationsUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({}),
                            });

                            if (response.ok) {
                                syncNotificationBadge(0);
                                pollNotifications();
                            }
                        } catch (error) {
                            notificationsMarkedRead = false;
                        }
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!notifyWrap.contains(event.target)) {
                        closeNotifyPanel();
                    }
                });

                notifyWrap.querySelector('.admin-bookings-notify__list')?.addEventListener('click', (event) => {
                    const item = event.target.closest('[data-admin-bookings-notify-item]');

                    if (!item) {
                        return;
                    }

                    const bookingHeaderId = item.getAttribute('data-booking-header-id') || '';

                    if (!bookingHeaderId) {
                        return;
                    }

                    closeNotifyPanel();
                    openBookingFromNotification(bookingHeaderId);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeNotifyPanel();
                    }
                });

                pollNotifications();
                window.setInterval(() => {
                    pollNotifications();
                }, 10000);
            }

            pollBookings();
            window.setInterval(() => {
                if (document.hidden) {
                    return;
                }

                pollBookings();
            }, 10000);

            window.addEventListener('storage', (event) => {
                if (event.key !== 'hyve-admin-bookings-refresh' || !event.newValue) {
                    return;
                }

                pollBookings();
            });

            bindBookingRowEvents();

            slotsWrap.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-admin-booking-action]');

                if (!button) {
                    return;
                }

                const bookingId = Number(button.dataset.bookingId);
                const url = button.dataset.url;

                if (!bookingId || !url) {
                    return;
                }

                button.disabled = true;
                setNotice('', false);

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({}),
                    });

                    if (!response.ok) {
                        throw new Error('Unable to update booking line.');
                    }

                    const payload = await response.json();
                    const bookingIndex = currentBookings.findIndex((booking) => Number(booking.id) === bookingId);

                    if (bookingIndex !== -1) {
                        currentBookings[bookingIndex] = {
                            ...currentBookings[bookingIndex],
                            status: payload.detail?.status ?? currentBookings[bookingIndex].status,
                            status_class: payload.detail?.status_class ?? currentBookings[bookingIndex].status_class,
                            can_review: Boolean(payload.detail?.can_review ?? false),
                            payment_status: payload.header?.payment_status ?? currentBookings[bookingIndex].payment_status,
                            payment_status_key: payload.header?.payment_status_key ?? currentBookings[bookingIndex].payment_status_key,
                            payment_status_class: payload.header?.payment_status_class ?? currentBookings[bookingIndex].payment_status_class,
                            progress: payload.detail?.progress ?? currentBookings[bookingIndex].progress,
                            progress_class: payload.detail?.progress_class ?? currentBookings[bookingIndex].progress_class,
                            progress_key: payload.detail?.progress_key ?? currentBookings[bookingIndex].progress_key,
                            actual_start: payload.detail?.actual_start ?? currentBookings[bookingIndex].actual_start,
                            actual_end: payload.detail?.actual_end ?? currentBookings[bookingIndex].actual_end,
                            can_start: Boolean(payload.detail?.can_start ?? currentBookings[bookingIndex].can_start),
                            can_end: Boolean(payload.detail?.can_end ?? currentBookings[bookingIndex].can_end),
                        };
                    }

                    rebuildSummary();
                    renderBookings();
                    setNotice(payload.message ?? 'Booking line updated successfully.', true);
                } catch (error) {
                    setNotice('Unable to save the action right now. Please try again.', true);
                } finally {
                    button.disabled = false;
                }
            });

            modal.querySelectorAll('[data-admin-bookings-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    activeRow = null;
                    currentBookings = [];
                    modal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    setNotice('', false);
                });
            });
        })();
    </script>
@endsection
