@extends('layouts.admin')

@section('content')
    <style>
        .admin-payments-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1.1rem;
        }

        .admin-payments-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(18, 24, 21, 0.38);
            backdrop-filter: blur(10px);
        }

        .admin-payments-modal__card {
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

        .admin-payments-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .admin-payments-modal__eyebrow {
            margin: 0;
            color: #9d7832;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-payments-modal__title {
            margin: 0.38rem 0 0;
            color: #132320;
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .admin-payments-modal__subtitle {
            margin: 0.32rem 0 0;
            color: #837d73;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .admin-payments-modal__close {
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

        .admin-payments-modal__grid {
            display: grid;
            gap: 1rem;
            margin-top: 1.1rem;
        }

        .admin-payments-modal__panel {
            border: 1px solid #e5e9e1;
            border-radius: 1rem;
            background: #fff;
            padding: 1rem;
        }

        .admin-payments-modal__panel-title {
            margin: 0;
            color: #132320;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .admin-payments-modal__details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem 1rem;
            margin-top: 0.9rem;
        }

        .admin-payments-modal__details dt {
            margin: 0 0 0.18rem;
            color: #9a948a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .admin-payments-modal__details dd {
            margin: 0;
            color: #173029;
            font-size: 0.84rem;
            font-weight: 600;
            line-height: 1.55;
        }

        .admin-payments-modal__chips {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.9rem;
        }

        .admin-payments-modal__latest {
            margin-top: 0.95rem;
            border: 1px solid #edf1ea;
            border-radius: 0.95rem;
            background: #fbfcf8;
            padding: 0.8rem 0.9rem;
        }

        .admin-payments-modal__latest-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .admin-payments-modal__latest strong {
            display: block;
            color: #1a2a26;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .admin-payments-modal__latest span {
            display: block;
            margin-top: 0.32rem;
            color: #6c756c;
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .admin-payments-modal__receipt-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dce6d3;
            border-radius: 999px;
            background: #fff;
            color: #274239;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.55rem 0.9rem;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
        }

        .admin-payments-modal__receipt-button[hidden] {
            display: none;
        }

        .admin-payments-modal__discount-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.7rem;
            margin-top: 0.95rem;
            align-items: end;
        }

        .admin-payments-modal__discount-form[hidden] {
            display: none;
        }

        .admin-payments-modal__discount-form label {
            display: grid;
            gap: 0.3rem;
        }

        .admin-payments-modal__discount-form span {
            color: #9a948a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .admin-payments-modal__discount-form select {
            border: 1px solid #dfe7d8;
            border-radius: 0.82rem;
            padding: 0.75rem 0.85rem;
            color: #173029;
            font-size: 0.78rem;
            background: #fff;
        }

        .admin-payments-modal__discount-summary {
            margin-top: 0.8rem;
            display: grid;
            gap: 0.32rem;
            border: 1px solid #edf1ea;
            border-radius: 0.9rem;
            background: #fbfcf8;
            padding: 0.8rem 0.9rem;
        }

        .admin-payments-modal__discount-summary strong {
            color: #1a2a26;
            font-size: 0.8rem;
        }

        .admin-payments-modal__discount-summary span {
            color: #6c756c;
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .admin-payments-modal__record-note {
            margin-top: 0.95rem;
            border: 1px solid #d9ebcf;
            border-radius: 0.95rem;
            background: #f4faea;
            padding: 0.85rem 0.95rem;
            color: #3f6a34;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.55;
        }

        .admin-payments-modal__record-note[hidden] {
            display: none;
        }

        .admin-payments-modal__record-form {
            display: grid;
            grid-template-columns: 1.1fr 0.95fr 1.15fr auto;
            gap: 0.7rem;
            margin-top: 0.95rem;
            align-items: end;
        }

        .admin-payments-modal__record-form[hidden] {
            display: none;
        }

        .admin-payments-modal__record-form label {
            display: grid;
            gap: 0.3rem;
        }

        .admin-payments-modal__record-form span {
            color: #9a948a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .admin-payments-modal__record-form input,
        .admin-payments-modal__record-form select {
            border: 1px solid #dfe7d8;
            border-radius: 0.82rem;
            padding: 0.75rem 0.85rem;
            color: #173029;
            font-size: 0.78rem;
            background: #fff;
        }

        .admin-payments-modal__history {
            margin-top: 0.95rem;
            display: grid;
            gap: 0.9rem;
            max-height: 33rem;
            overflow-y: auto;
            padding-right: 0.15rem;
        }

        .admin-payments-modal__empty {
            border: 1px dashed #d7ddd1;
            border-radius: 0.9rem;
            background: #fbfcf8;
            padding: 1rem;
            color: #8f897d;
            font-size: 0.82rem;
            text-align: center;
        }

        .admin-payments-modal__payment-card {
            border: 1px solid #e7ece4;
            border-radius: 0.95rem;
            background: #fbfcf8;
            padding: 0.95rem;
        }

        .admin-payments-modal__payment-top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.9rem;
        }

        .admin-payments-modal__payment-meta {
            display: grid;
            gap: 0.24rem;
        }

        .admin-payments-modal__payment-meta strong {
            color: #132320;
            font-size: 0.88rem;
            font-weight: 700;
        }

        .admin-payments-modal__payment-meta p {
            margin: 0;
            color: #5f6f67;
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .admin-payments-modal__payment-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.65rem;
        }

        .admin-payments-modal__proof {
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

        .admin-payments-modal__button {
            border: 1px solid rgba(17, 52, 44, 0.12);
            border-radius: 0.82rem;
            padding: 0.82rem 1.1rem;
            background: #fff;
            color: #173029;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }

        .admin-payments-modal__button--primary {
            border-color: #44793b;
            background: #44793b;
            color: #fff;
        }

        .admin-payments-modal__button--danger {
            border-color: #efc7bf;
            background: #fff5f3;
            color: #b14635;
        }

        .admin-payments-modal__verified {
            color: #8f887d;
            font-size: 0.72rem;
            line-height: 1.45;
            text-align: right;
        }

        .admin-payments-modal__payment-notes {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 0.8rem;
        }

        .admin-payments-modal__payment-note {
            border: 1px solid #edf1ea;
            border-radius: 0.8rem;
            background: #fff;
            padding: 0.72rem 0.8rem;
        }

        .admin-payments-modal__payment-note strong {
            display: block;
            color: #1a2a26;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .admin-payments-modal__payment-note span {
            display: block;
            margin-top: 0.32rem;
            color: #6c756c;
            font-size: 0.74rem;
            line-height: 1.5;
        }

        .admin-payments-receipt-modal {
            position: fixed;
            inset: 0;
            z-index: 110;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .admin-payments-receipt-modal[hidden] {
            display: none;
        }

        .admin-payments-receipt-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(16, 25, 22, 0.48);
            backdrop-filter: blur(9px);
        }

        .admin-payments-receipt-modal__card {
            position: relative;
            z-index: 1;
            width: min(78rem, 100%);
            height: min(90vh, 58rem);
            border: 1px solid rgba(17, 52, 44, 0.1);
            border-radius: 1.3rem;
            background: #fffdf9;
            box-shadow: 0 26px 70px rgba(17, 28, 24, 0.22);
            padding: 1rem;
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 0.85rem;
        }

        .admin-payments-receipt-modal__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .admin-payments-receipt-modal__actions {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
        }

        .admin-payments-receipt-modal__frame {
            width: 100%;
            height: 100%;
            border: 1px solid #e6e0d4;
            border-radius: 1rem;
            background: #fff;
        }

        .admin-payments-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .admin-payments-pagination__summary {
            color: #7f857c;
            font-size: 0.76rem;
        }

        .admin-payments-pagination__controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
        }

        .admin-payments-pagination__link,
        .admin-payments-pagination__ellipsis {
            display: inline-flex;
            min-width: 2.35rem;
            height: 2.35rem;
            align-items: center;
            justify-content: center;
            border: 1px solid #dfe6da;
            border-radius: 0.72rem;
            background: #fff;
            padding: 0 0.72rem;
            color: #38543d;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .admin-payments-pagination__link:hover {
            border-color: #b9cfaa;
            background: #f4f8ee;
        }

        .admin-payments-pagination__link.is-current {
            border-color: #44793b;
            background: #44793b;
            color: #fff;
        }

        .admin-payments-pagination__link.is-disabled,
        .admin-payments-pagination__ellipsis {
            cursor: default;
            opacity: 0.48;
        }

        @media (max-width: 980px) {
            .admin-payments-modal__details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .admin-payments-modal {
                padding: 0.7rem;
            }

            .admin-payments-modal__card {
                max-height: calc(100vh - 1.4rem);
                padding: 1rem;
                border-radius: 1.05rem;
            }

            .admin-payments-modal__details,
            .admin-payments-modal__payment-notes,
            .admin-payments-modal__discount-form,
            .admin-payments-modal__record-form {
                grid-template-columns: minmax(0, 1fr);
            }

            .admin-payments-modal__payment-actions,
            .admin-payments-modal__payment-actions form,
            .admin-payments-modal__button {
                width: 100%;
            }
        }
    </style>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">Payment review</p>
            <h1 class="mt-2 text-[1.55rem] font-semibold tracking-[-0.04em] text-[#132320]">Payments</h1>
            <p class="mt-1 max-w-3xl text-[0.84rem] text-[#8a8478]">Approved bookings only</p>
        </div>
    </div>

    @if (session('admin_success'))
        <div class="mt-4 rounded-[1rem] border border-[#d9ebcf] bg-[#f4faea] px-4 py-3 text-[0.82rem] font-semibold text-[#3f6a34]">
            {{ session('admin_success') }}
        </div>
    @endif

    @if (session('admin_error'))
        <div class="mt-4 rounded-[1rem] border border-[#f1d7d2] bg-[#fff5f3] px-4 py-3 text-[0.82rem] font-semibold text-[#ab4f43]">
            {{ session('admin_error') }}
        </div>
    @endif

    <section class="mt-5 grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Pending reviews</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $paymentSummary['pending_count'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#9a7832]">Waiting for admin checking</p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Pending amount</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">Php {{ number_format($paymentSummary['pending_amount'], 2) }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#9a7832]">Submitted but not yet approved</p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Approved today</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">Php {{ number_format($paymentSummary['approved_today_amount'], 2) }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">{{ now()->format('F j, Y') }}</p>
        </article>

        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Members with balance</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $paymentSummary['members_with_balance'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">Active member balances to follow up</p>
        </article>
    </section>

    <section class="mt-4 rounded-[0.95rem] border border-[#e0e4db] bg-white px-5 py-4.5">
        <form method="GET" action="{{ route('admin.sections.payments') }}" class="flex flex-nowrap items-center gap-2 overflow-x-auto">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Search booking ID, reference, member, email, or phone..."
                class="min-w-[18rem] flex-[1_1_20rem] rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]"
            >
            <select name="status" class="min-w-[10.5rem] rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <option value="all" @selected($filters['status'] === 'all')>All payment status</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
            </select>
            <select name="method" class="min-w-[10rem] rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <option value="all" @selected($filters['method'] === 'all')>All method</option>
                <option value="gcash" @selected($filters['method'] === 'gcash')>GCash</option>
                <option value="bank_transfer" @selected($filters['method'] === 'bank_transfer')>Bank Transfer</option>
                <option value="cash" @selected($filters['method'] === 'cash')>Cash</option>
            </select>
            <button type="submit" class="shrink-0 inline-flex items-center justify-center rounded-[0.85rem] bg-[#44793b] px-4 py-2.5 text-[0.82rem] font-semibold text-white transition hover:bg-[#396733]">Search</button>
            <a href="{{ route('admin.sections.payments') }}" class="shrink-0 inline-flex items-center justify-center rounded-[0.85rem] border border-[#dfe7d8] px-4 py-2.5 text-[0.82rem] font-semibold text-[#5a665d]">Clear</a>
        </form>
    </section>

    <section class="mt-4 rounded-[0.95rem] border border-[#e0e4db] bg-white px-5 py-4.5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-[1rem] font-semibold tracking-[-0.03em] text-[#132320]">Booking payment listing</h2>
                <p class="mt-1 text-[0.78rem] text-[#8f897d]">Usa ka row per approved booking. Click a row to record or verify payment.</p>
            </div>
            <span class="text-[0.76rem] text-[#aaa398]">{{ $bookings->total() }} total</span>
        </div>

        <div class="mt-4 overflow-hidden rounded-[0.85rem] border border-[#edf1ea] bg-[#fcfdfb]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-[0.82rem]">
                    <thead class="bg-[#fcfdfb] text-[0.68rem] font-bold uppercase tracking-[0.11em] text-[#b3ada1]">
                        <tr>
                            <th class="px-3 py-3">ID</th>
                            <th class="py-3">Customer</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Details</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Method</th>
                            <th class="py-3">Payment</th>
                            <th class="px-3 py-3">Submissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1ea]">
                        @forelse ($bookingRows as $row)
                            @php
                                $rowStatusTone = match (strtolower((string) $row['payment_status_key'])) {
                                    'approved', 'paid' => 'bg-[#eef8df] text-[#3f6a34]',
                                    'rejected', 'cancelled' => 'bg-[#fde9e5] text-[#b14635]',
                                    'partially_paid' => 'bg-[#fff3d9] text-[#9a7832]',
                                    default => 'bg-[#f8efdd] text-[#9a7832]',
                                };
                                $rowSourceTone = $row['source_key'] === 'walk_in'
                                    ? 'bg-[#e9f7ea] text-[#39703d]'
                                    : 'bg-[#e9f0ff] text-[#3156cb]';
                            @endphp
                            <tr
                                class="cursor-pointer transition hover:bg-[#f9fbf5]"
                                data-payment-booking-open
                                data-id="{{ $row['id'] }}"
                                data-reference="{{ $row['reference'] }}"
                                data-name="{{ $row['customer_name'] }}"
                                data-email="{{ $row['email'] }}"
                                data-phone="{{ $row['phone'] }}"
                                data-source-label="{{ $row['source_label'] }}"
                                data-booking-type="{{ $row['booking_type'] }}"
                                data-is-new="{{ $row['is_new'] ? '1' : '0' }}"
                                data-payment-method="{{ $row['payment_method'] }}"
                                data-payment-status="{{ $row['payment_status'] }}"
                                data-payment-status-key="{{ $row['payment_status_key'] }}"
                                data-gross-total="{{ $row['gross_total_amount'] }}"
                                data-discount-label="{{ $row['discount_label'] }}"
                                data-discount-rate="{{ $row['discount_rate'] }}"
                                data-discount-amount="{{ $row['discount_amount'] }}"
                                data-payable-total="{{ $row['payable_total_amount'] }}"
                                data-discount-code="{{ $row['discount_code'] }}"
                                data-downpayment="{{ $row['downpayment_amount'] }}"
                                data-balance="{{ $row['balance_amount'] }}"
                                data-booking-count="{{ $row['booking_count'] }}"
                                data-approved-total="{{ $row['approved_total'] }}"
                                data-latest-payment="{{ $row['latest_payment_label'] }}"
                                data-receipt-url="{{ $row['receipt_url'] }}"
                                data-discount-apply-url="{{ $row['discount_apply_url'] }}"
                                data-record-payment-url="{{ $row['record_payment_url'] }}"
                                data-can-record-payment="{{ $row['can_record_payment'] ? '1' : '0' }}"
                                data-payments='@json($row['payments'])'
                            >
                                <td class="px-3 py-3 font-semibold text-[#1a2a26]">#{{ $row['id'] }}</td>
                                <td class="py-3">
                                    <div class="text-[#1a2a26]">
                                        <div class="font-semibold">{{ $row['customer_name'] }}</div>
                                        <div class="mt-1 text-[0.74rem] text-[#8f887d]">{{ $row['email'] }}</div>
                                        @if ($row['is_new'])
                                            <span class="mt-1.5 inline-flex rounded-full bg-[#e9f8df] px-2.5 py-1 text-[0.64rem] font-bold uppercase tracking-[0.04em] text-[#39702f]" data-new-payment-booking-badge>New booking</span>
                                        @endif
                                        <div class="mt-1 text-[0.68rem] text-[#a0988b]">Booked {{ $row['latest_date'] }} · {{ $row['latest_time'] }}</div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="grid justify-items-start gap-1">
                                        <span class="inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold {{ $rowSourceTone }}">{{ $row['source_label'] }}</span>
                                        <span class="text-[0.68rem] text-[#8f887d]">{{ $row['booking_type'] }}</span>
                                    </div>
                                </td>
                                <td class="py-3 text-[#5f6f67]">
                                    @if (! empty($row['preview_rooms']))
                                        {{ implode(', ', $row['preview_rooms']) }} - {{ $row['booking_count'] }} slot{{ $row['booking_count'] !== 1 ? 's' : '' }}
                                    @else
                                        Booking payment review
                                    @endif
                                </td>
                                <td class="py-3 font-semibold text-[#1a2a26]">
                                    {{ $row['payable_total_amount'] }}
                                    @if ($row['discount_code'] !== 'none')
                                        <div class="mt-1 text-[0.72rem] font-normal text-[#8f887d]">Orig {{ $row['gross_total_amount'] }}</div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex rounded-full bg-[#eef8df] px-2.5 py-0.75 text-[0.66rem] font-semibold text-[#3f6a34]">{{ $row['payment_method'] }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold {{ $rowStatusTone }}">
                                        {{ $row['payment_status'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-[#5f6f67]">
                                    @if ($row['pending_count'] > 0)
                                        <span class="font-semibold text-[#9a7832]">{{ $row['pending_count'] }} pending</span>
                                    @else
                                        <span>{{ count($row['payments']) }} total</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-[#8f897d]">No bookings matched your current payment filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($bookings->hasPages())
            <div class="admin-payments-pagination mt-4">
                <p class="admin-payments-pagination__summary">
                    Showing {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} of {{ $bookings->total() }} bookings
                </p>

                <nav class="admin-payments-pagination__controls" aria-label="Payment booking list pagination">
                    @php
                        $currentPage = $bookings->currentPage();
                        $lastPage = $bookings->lastPage();
                        $startPage = max(1, $currentPage - 1);
                        $endPage = min($lastPage, $currentPage + 1);
                    @endphp

                    @if ($bookings->onFirstPage())
                        <span class="admin-payments-pagination__link is-disabled" aria-disabled="true">Previous</span>
                    @else
                        <a class="admin-payments-pagination__link" href="{{ $bookings->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif

                    @if ($startPage > 1)
                        <a class="admin-payments-pagination__link" href="{{ $bookings->url(1) }}">1</a>
                        @if ($startPage > 2)
                            <span class="admin-payments-pagination__ellipsis">&hellip;</span>
                        @endif
                    @endif

                    @foreach (range($startPage, $endPage) as $page)
                        @if ($page === $currentPage)
                            <span class="admin-payments-pagination__link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="admin-payments-pagination__link" href="{{ $bookings->url($page) }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($endPage < $lastPage)
                        @if ($endPage < $lastPage - 1)
                            <span class="admin-payments-pagination__ellipsis">&hellip;</span>
                        @endif
                        <a class="admin-payments-pagination__link" href="{{ $bookings->url($lastPage) }}">{{ $lastPage }}</a>
                    @endif

                    @if ($bookings->hasMorePages())
                        <a class="admin-payments-pagination__link" href="{{ $bookings->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span class="admin-payments-pagination__link is-disabled" aria-disabled="true">Next</span>
                    @endif
                </nav>
            </div>
        @endif
    </section>

    <div class="admin-payments-modal" data-payment-booking-modal style="display:none;">
        <button type="button" class="admin-payments-modal__backdrop" data-payment-booking-close aria-label="Close payment modal"></button>

        <div class="admin-payments-modal__card">
            <div class="admin-payments-modal__top">
                <div>
                    <p class="admin-payments-modal__eyebrow" data-payment-modal-reference>Reference</p>
                    <h2 class="admin-payments-modal__title" data-payment-modal-name>Customer</h2>
                    <p class="admin-payments-modal__subtitle" data-payment-modal-subtitle>Booking payment details</p>
                </div>

                <button type="button" class="admin-payments-modal__close" data-payment-booking-close aria-label="Close payment modal">&times;</button>
            </div>

            <div class="admin-payments-modal__grid">
                <section class="admin-payments-modal__panel">
                    <h3 class="admin-payments-modal__panel-title">Booking summary</h3>

                    <dl class="admin-payments-modal__details">
                        <div>
                            <dt>Email</dt>
                            <dd data-payment-modal-email></dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd data-payment-modal-phone></dd>
                        </div>
                        <div>
                            <dt>Booking type</dt>
                            <dd data-payment-modal-type></dd>
                        </div>
                        <div>
                            <dt>Payment method</dt>
                            <dd data-payment-modal-method></dd>
                        </div>
                        <div>
                            <dt>Gross total</dt>
                            <dd data-payment-modal-total></dd>
                        </div>
                        <div>
                            <dt>Discount</dt>
                            <dd data-payment-modal-discount></dd>
                        </div>
                        <div>
                            <dt>Payable total</dt>
                            <dd data-payment-modal-payable-total></dd>
                        </div>
                        <div>
                            <dt>Paid so far</dt>
                            <dd data-payment-modal-downpayment></dd>
                        </div>
                        <div>
                            <dt>Remaining balance</dt>
                            <dd data-payment-modal-balance></dd>
                        </div>
                        <div>
                            <dt>Approved payments</dt>
                            <dd data-payment-modal-approved-total></dd>
                        </div>
                    </dl>

                    <div class="admin-payments-modal__chips">
                        <span class="inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold" data-payment-modal-status></span>
                        <span class="inline-flex rounded-full bg-[#eef2ff] px-2.5 py-0.75 text-[0.66rem] font-semibold text-[#4e5ec3]" data-payment-modal-booking-count></span>
                    </div>

                    <div class="admin-payments-modal__latest">
                        <div class="admin-payments-modal__latest-top">
                            <strong>Latest payment update</strong>
                            <button type="button" class="admin-payments-modal__receipt-button" data-payment-modal-receipt-button hidden>View receipt</button>
                        </div>
                        <span data-payment-modal-latest-payment></span>
                    </div>

                    <form method="POST" class="admin-payments-modal__discount-form" data-payment-modal-discount-form>
                        @csrf
                        <label>
                            <span>Discount type</span>
                            <select name="discount_code">
                                @foreach ($discountOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="admin-payments-modal__button">Apply Discount</button>
                    </form>

                    <div class="admin-payments-modal__discount-summary">
                        <strong data-payment-modal-discount-title>No discount applied</strong>
                        <span data-payment-modal-discount-copy>The selected booking currently uses the regular payable amount.</span>
                    </div>

                    <form method="POST" class="admin-payments-modal__record-form" data-payment-modal-record-form>
                        @csrf
                        <input type="hidden" name="payment_booking_id" value="">
                        <input type="hidden" name="payment_submission_token" value="{{ old('payment_submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
                        <label>
                            <span>Amount</span>
                            <input type="number" name="amount" min="0.01" step="0.01" required>
                        </label>
                        <label>
                            <span>Method</span>
                            <select name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </label>
                        <label>
                            <span>Discount</span>
                            <select name="discount_code">
                                @foreach ($discountOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="admin-payments-modal__button admin-payments-modal__button--primary" data-payment-record-submit>
                            <span data-payment-record-submit-label>Record Payment</span>
                        </button>
                    </form>

                    <div class="admin-payments-modal__record-note" data-payment-modal-record-note hidden>
                        Fully paid booking. No more payment submission is required for this booking.
                    </div>
                </section>

                <section class="admin-payments-modal__panel">
                    <h3 class="admin-payments-modal__panel-title">Payment history</h3>
                    <div class="admin-payments-modal__history" data-payment-modal-history></div>
                </section>
            </div>
        </div>
    </div>

    <div class="admin-payments-receipt-modal" data-payment-receipt-modal hidden>
        <button type="button" class="admin-payments-receipt-modal__backdrop" data-payment-receipt-close aria-label="Close receipt preview"></button>
        <div class="admin-payments-receipt-modal__card">
            <div class="admin-payments-receipt-modal__top">
                <div>
                    <p class="admin-payments-modal__eyebrow">Receipt Preview</p>
                    <h3 class="admin-payments-modal__title" style="font-size:1.1rem;">Unofficial Receipt</h3>
                </div>
                <div class="admin-payments-receipt-modal__actions">
                    <button type="button" class="admin-payments-modal__button admin-payments-modal__button--primary" data-payment-receipt-print>Print</button>
                    <button type="button" class="admin-payments-modal__close" data-payment-receipt-close aria-label="Close receipt preview">&times;</button>
                </div>
            </div>
            <iframe class="admin-payments-receipt-modal__frame" data-payment-receipt-frame title="Receipt preview"></iframe>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-payment-booking-modal]');
            const receiptModal = document.querySelector('[data-payment-receipt-modal]');

            if (!modal || !receiptModal) {
                return;
            }

            const name = modal.querySelector('[data-payment-modal-name]');
            const reference = modal.querySelector('[data-payment-modal-reference]');
            const subtitle = modal.querySelector('[data-payment-modal-subtitle]');
            const email = modal.querySelector('[data-payment-modal-email]');
            const phone = modal.querySelector('[data-payment-modal-phone]');
            const type = modal.querySelector('[data-payment-modal-type]');
            const method = modal.querySelector('[data-payment-modal-method]');
            const total = modal.querySelector('[data-payment-modal-total]');
            const discount = modal.querySelector('[data-payment-modal-discount]');
            const payableTotal = modal.querySelector('[data-payment-modal-payable-total]');
            const downpayment = modal.querySelector('[data-payment-modal-downpayment]');
            const balance = modal.querySelector('[data-payment-modal-balance]');
            const approvedTotal = modal.querySelector('[data-payment-modal-approved-total]');
            const status = modal.querySelector('[data-payment-modal-status]');
            const bookingCount = modal.querySelector('[data-payment-modal-booking-count]');
            const latestPayment = modal.querySelector('[data-payment-modal-latest-payment]');
            const receiptButton = modal.querySelector('[data-payment-modal-receipt-button]');
            const history = modal.querySelector('[data-payment-modal-history]');
            const discountForm = modal.querySelector('[data-payment-modal-discount-form]');
            const discountTitle = modal.querySelector('[data-payment-modal-discount-title]');
            const discountCopy = modal.querySelector('[data-payment-modal-discount-copy]');
            const recordForm = modal.querySelector('[data-payment-modal-record-form]');
            const recordNote = modal.querySelector('[data-payment-modal-record-note]');
            const receiptFrame = receiptModal.querySelector('[data-payment-receipt-frame]');
            const csrfToken = @json(csrf_token());
            const discountOptions = @json($discountOptions);
            const amountInput = recordForm?.querySelector('input[name="amount"]');
            const bookingIdInput = recordForm?.querySelector('input[name="payment_booking_id"]');
            const recordSubmitButton = recordForm?.querySelector('[data-payment-record-submit]');
            const recordSubmitLabel = recordForm?.querySelector('[data-payment-record-submit-label]');
            const reopenBookingId = @json(session('admin_open_payment_modal', old('payment_booking_id')));
            const oldAmount = @json(old('amount'));
            const oldMethod = @json(old('payment_method'));
            const oldDiscountCode = @json(old('discount_code'));
            const discountRateMap = Object.fromEntries(discountOptions.map((option) => [String(option.value), Number(option.rate || 0)]));
            const discountLabelMap = Object.fromEntries(discountOptions.map((option) => [String(option.value), String(option.label || option.value)]));
            let activeReceiptUrl = '';
            let activeFinancials = null;
            let paymentSubmissionInProgress = false;
            const viewedPaymentBookingsStorageKey = 'hyve-admin-viewed-payment-bookings';
            let viewedPaymentBookingIds = new Set();

            try {
                const storedViewedBookings = JSON.parse(window.localStorage.getItem(viewedPaymentBookingsStorageKey) || '[]');
                viewedPaymentBookingIds = new Set(Array.isArray(storedViewedBookings) ? storedViewedBookings.map(String) : []);
            } catch (error) {
                viewedPaymentBookingIds = new Set();
            }

            const markPaymentBookingViewed = (row) => {
                const bookingId = String(row?.dataset?.id || '');

                if (!bookingId || row.dataset.isNew !== '1') {
                    return;
                }

                viewedPaymentBookingIds.add(bookingId);
                row.dataset.isNew = '0';
                row.querySelector('[data-new-payment-booking-badge]')?.remove();

                try {
                    window.localStorage.setItem(viewedPaymentBookingsStorageKey, JSON.stringify(Array.from(viewedPaymentBookingIds).slice(-200)));
                } catch (error) {
                    // Keep the current page state even when browser storage is unavailable.
                }
            };

            const parseMoneyValue = (value) => {
                const cleaned = String(value ?? '').replace(/[^\d.]/g, '');
                const parsed = Number.parseFloat(cleaned);

                return Number.isFinite(parsed) ? parsed : 0;
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const badgeClass = (value) => {
                const text = String(value || '').toLowerCase();

                if (text.includes('approved') || text.includes('paid')) {
                    return 'bg-[#eef8df] text-[#3f6a34]';
                }

                if (text.includes('reject')) {
                    return 'bg-[#fde9e5] text-[#b14635]';
                }

                if (text.includes('partial')) {
                    return 'bg-[#fff3d9] text-[#9a7832]';
                }

                return 'bg-[#f8efdd] text-[#9a7832]';
            };

            const formatPeso = (value) => `Php ${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            const updateDiscountPreview = (selectedCode) => {
                if (!activeFinancials) {
                    return;
                }

                const rate = Number(discountRateMap[String(selectedCode)] || 0);
                const label = String(discountLabelMap[String(selectedCode)] || 'No discount');
                const discountAmountValue = rate > 0
                    ? Number((activeFinancials.grossTotal * (rate / 100)).toFixed(2))
                    : 0;
                const payableValue = Number(Math.max(0, activeFinancials.grossTotal - discountAmountValue).toFixed(2));
                const remainingValue = Number(Math.max(0, payableValue - activeFinancials.approvedTotal).toFixed(2));

                discount.textContent = rate > 0
                    ? `${label} - ${formatPeso(discountAmountValue)}`
                    : 'No discount';
                payableTotal.textContent = formatPeso(payableValue);
                balance.textContent = formatPeso(remainingValue);

                if (discountTitle && discountCopy) {
                    if (rate > 0) {
                        discountTitle.textContent = `${label} active`;
                        discountCopy.textContent = `${formatPeso(discountAmountValue)} discount applied. Payable total is now ${formatPeso(payableValue)}.`;
                    } else {
                        discountTitle.textContent = 'No discount applied';
                        discountCopy.textContent = 'The selected booking currently uses the regular payable amount.';
                    }
                }

                if (amountInput && !amountInput.disabled) {
                    amountInput.max = remainingValue > 0 ? remainingValue.toFixed(2) : '0';

                    const currentValue = Number.parseFloat(amountInput.value || '0');
                    if (!Number.isFinite(currentValue) || currentValue <= 0 || currentValue > remainingValue) {
                        amountInput.value = remainingValue > 0 ? remainingValue.toFixed(2) : '';
                    }
                }
            };

            const closeModal = () => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            };

            const openReceiptModal = () => {
                if (!activeReceiptUrl || !receiptFrame) {
                    return;
                }

                receiptFrame.src = activeReceiptUrl;
                receiptModal.hidden = false;
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            const closeReceiptModal = () => {
                receiptModal.hidden = true;

                if (receiptFrame) {
                    receiptFrame.src = '';
                }

                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            const openModal = (row) => {
                const payments = JSON.parse(row.dataset.payments || '[]');
                markPaymentBookingViewed(row);

                reference.textContent = `Booking ${row.dataset.reference || '--'}`;
                name.textContent = row.dataset.name || 'Customer';
                subtitle.textContent = `${row.dataset.email || '--'} - ${row.dataset.phone || '--'}`;
                email.textContent = row.dataset.email || '--';
                phone.textContent = row.dataset.phone || '--';
                type.textContent = `${row.dataset.sourceLabel || '--'} · ${row.dataset.bookingType || '--'}`;
                method.textContent = row.dataset.paymentMethod || '--';
                total.textContent = row.dataset.grossTotal || '--';
                discount.textContent = row.dataset.discountRate && Number.parseFloat(row.dataset.discountRate) > 0
                    ? `${row.dataset.discountLabel || 'Discount'} - ${row.dataset.discountAmount || 'Php 0.00'}`
                    : 'No discount';
                payableTotal.textContent = row.dataset.payableTotal || '--';
                downpayment.textContent = row.dataset.downpayment || '--';
                balance.textContent = row.dataset.balance || '--';
                approvedTotal.textContent = row.dataset.approvedTotal || '--';
                latestPayment.textContent = row.dataset.latestPayment || '--';
                activeReceiptUrl = row.dataset.receiptUrl || '';

                const remainingBalance = parseMoneyValue(row.dataset.balance || '');
                const isFullyPaid = remainingBalance <= 0.009 || String(row.dataset.paymentStatus || '').toLowerCase().includes('fully paid');
                const canRecordPayment = String(row.dataset.canRecordPayment || '0') === '1';
                const selectedDiscountCode = row.dataset.discountCode || 'none';
                const selectedDiscountRate = Number.parseFloat(row.dataset.discountRate || '0');
                activeFinancials = {
                    grossTotal: parseMoneyValue(row.dataset.grossTotal || '0'),
                    approvedTotal: parseMoneyValue(row.dataset.approvedTotal || '0'),
                };

                if (receiptButton) {
                    receiptButton.hidden = !isFullyPaid || !activeReceiptUrl;
                }

                if (discountTitle && discountCopy) {
                    if (Number.isFinite(selectedDiscountRate) && selectedDiscountRate > 0) {
                        discountTitle.textContent = `${row.dataset.discountLabel || 'Discount'} active`;
                        discountCopy.textContent = `${row.dataset.discountAmount || 'Php 0.00'} discount applied. Payable total is now ${row.dataset.payableTotal || '--'}.`;
                    } else {
                        discountTitle.textContent = 'No discount applied';
                        discountCopy.textContent = 'The selected booking currently uses the regular payable amount.';
                    }
                }

                if (discountForm) {
                    const discountInput = discountForm.querySelector('select[name="discount_code"]');

                    discountForm.action = row.dataset.discountApplyUrl || '';
                    discountForm.hidden = !canRecordPayment || isFullyPaid;

                    if (discountInput) {
                        discountInput.value = String(reopenBookingId || '') === String(row.dataset.id || '') && oldDiscountCode
                            ? String(oldDiscountCode)
                            : selectedDiscountCode;
                    }
                }

                if (recordForm) {
                    recordForm.action = row.dataset.recordPaymentUrl || '';
                    recordForm.hidden = isFullyPaid || !canRecordPayment;
                }

                if (bookingIdInput) {
                    bookingIdInput.value = row.dataset.id || '';
                }

                if (amountInput) {
                    amountInput.max = remainingBalance > 0 ? remainingBalance.toFixed(2) : '0';
                    amountInput.value = remainingBalance > 0 ? remainingBalance.toFixed(2) : '';
                    amountInput.disabled = isFullyPaid;
                    amountInput.setCustomValidity('');
                }

                if (recordForm) {
                    const methodInput = recordForm.querySelector('select[name="payment_method"]');
                    const recordDiscountInput = recordForm.querySelector('select[name="discount_code"]');

                    if (String(reopenBookingId || '') === String(row.dataset.id || '')) {
                        if (amountInput && oldAmount !== null && String(oldAmount).trim() !== '') {
                            amountInput.value = String(oldAmount);
                        }

                        if (methodInput && oldMethod !== null && String(oldMethod).trim() !== '') {
                            methodInput.value = String(oldMethod);
                        }

                        if (recordDiscountInput && oldDiscountCode !== null && String(oldDiscountCode).trim() !== '') {
                            recordDiscountInput.value = String(oldDiscountCode);
                        }
                    } else if (methodInput) {
                        methodInput.value = row.dataset.paymentMethod?.toLowerCase() === 'gcash'
                            ? 'gcash'
                            : row.dataset.paymentMethod?.toLowerCase() === 'bank transfer'
                                ? 'bank_transfer'
                                : 'cash';
                    }

                    if (recordDiscountInput && !(String(reopenBookingId || '') === String(row.dataset.id || '') && oldDiscountCode)) {
                        recordDiscountInput.value = selectedDiscountCode;
                    }
                }

                const activeDiscountCode = String(reopenBookingId || '') === String(row.dataset.id || '') && oldDiscountCode
                    ? String(oldDiscountCode)
                    : selectedDiscountCode;
                updateDiscountPreview(activeDiscountCode);

                if (recordNote) {
                    recordNote.hidden = !isFullyPaid && canRecordPayment;
                    if (!canRecordPayment && !isFullyPaid) {
                        recordNote.hidden = false;
                        recordNote.textContent = 'This role can view payment records but cannot record or verify payments.';
                    } else if (isFullyPaid) {
                        recordNote.textContent = 'Fully paid booking. No more payment submission is required for this booking.';
                    }
                }

                status.textContent = row.dataset.paymentStatus || 'Pending';
                status.className = `inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold ${badgeClass(row.dataset.paymentStatus)}`;
                bookingCount.textContent = `${row.dataset.bookingCount || '0'} booking slot(s)`;

                if (!payments.length) {
                    history.innerHTML = '<div class="admin-payments-modal__empty">No payment submission yet for this booking.</div>';
                } else {
                    history.innerHTML = payments.map((payment) => {
                        const proofButton = payment.proof_url
                            ? `<a href="${escapeHtml(payment.proof_url)}" target="_blank" class="admin-payments-modal__proof">View proof</a>`
                            : '';

                        const reviewButtons = payment.can_review
                            ? `
                                <form method="POST" action="${escapeHtml(payment.approve_url)}">
                                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                    <button type="submit" class="admin-payments-modal__button admin-payments-modal__button--primary">${escapeHtml(payment.verify_label || 'Verify Payment')}</button>
                                </form>
                                <form method="POST" action="${escapeHtml(payment.reject_url)}">
                                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                    <button type="submit" class="admin-payments-modal__button admin-payments-modal__button--danger">Reject</button>
                                </form>
                            `
                            : `<div class="admin-payments-modal__verified">${escapeHtml(payment.verified_by || 'Admin')}<br>${escapeHtml(payment.verified_at || '--')}</div>`;

                        return `
                            <article class="admin-payments-modal__payment-card">
                                <div class="admin-payments-modal__payment-top">
                                    <div class="admin-payments-modal__payment-meta">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <strong>Payment #${escapeHtml(payment.id)}</strong>
                                            <span class="inline-flex rounded-full px-2.5 py-0.75 text-[0.66rem] font-semibold ${badgeClass(payment.status)}">${escapeHtml(payment.status)}</span>
                                            <span class="inline-flex rounded-full bg-[#eef2ff] px-2.5 py-0.75 text-[0.66rem] font-semibold text-[#4e5ec3]">${escapeHtml(payment.payment_type)}</span>
                                        </div>
                                        <p>${escapeHtml(payment.room_name)} - ${escapeHtml(payment.date)} - ${escapeHtml(payment.time)}</p>
                                        <p>Method: ${escapeHtml(payment.payment_method)} - Amount: ${escapeHtml(payment.amount)}</p>
                                        <p>Submitted: ${escapeHtml(payment.submitted_at)}</p>
                                    </div>

                                    <div class="admin-payments-modal__payment-actions">
                                        ${proofButton}
                                        ${reviewButtons}
                                    </div>
                                </div>

                                <div class="admin-payments-modal__payment-notes">
                                    <div class="admin-payments-modal__payment-note">
                                        <strong>Customer note</strong>
                                        <span>${escapeHtml(payment.notes || '--')}</span>
                                    </div>
                                    <div class="admin-payments-modal__payment-note">
                                        <strong>Review note</strong>
                                        <span>${escapeHtml(payment.review_notes || '--')}</span>
                                    </div>
                                </div>
                            </article>
                        `;
                    }).join('');
                }

                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            document.querySelectorAll('[data-payment-booking-open]').forEach((row) => {
                if (viewedPaymentBookingIds.has(String(row.dataset.id || ''))) {
                    row.dataset.isNew = '0';
                    row.querySelector('[data-new-payment-booking-badge]')?.remove();
                }

                row.addEventListener('click', () => openModal(row));
            });

            modal.querySelectorAll('[data-payment-booking-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            receiptModal.querySelectorAll('[data-payment-receipt-close]').forEach((button) => {
                button.addEventListener('click', closeReceiptModal);
            });

            receiptButton?.addEventListener('click', openReceiptModal);

            discountForm?.querySelector('select[name="discount_code"]')?.addEventListener('change', (event) => {
                updateDiscountPreview(event.target.value);

                const recordDiscountInput = recordForm?.querySelector('select[name="discount_code"]');
                if (recordDiscountInput) {
                    recordDiscountInput.value = event.target.value;
                }
            });

            recordForm?.querySelector('select[name="discount_code"]')?.addEventListener('change', (event) => {
                updateDiscountPreview(event.target.value);

                const discountInput = discountForm?.querySelector('select[name="discount_code"]');
                if (discountInput) {
                    discountInput.value = event.target.value;
                }
            });

            receiptModal.querySelector('[data-payment-receipt-print]')?.addEventListener('click', () => {
                receiptFrame?.contentWindow?.print();
            });

            if (reopenBookingId !== null && String(reopenBookingId).trim() !== '') {
                const reopenRow = document.querySelector(`[data-payment-booking-open][data-id="${String(reopenBookingId)}"]`);

                if (reopenRow) {
                    openModal(reopenRow);
                }
            }

            if (amountInput) {
                amountInput.addEventListener('input', () => {
                    const max = Number.parseFloat(amountInput.max || '0');
                    const value = Number.parseFloat(amountInput.value || '0');

                    if (Number.isFinite(max) && max > 0 && Number.isFinite(value) && value > max) {
                        amountInput.setCustomValidity('Amount cannot be greater than the remaining balance.');
                    } else {
                        amountInput.setCustomValidity('');
                    }
                });
            }

            if (recordForm) {
                recordForm.addEventListener('submit', (event) => {
                    if (paymentSubmissionInProgress) {
                        event.preventDefault();

                        return;
                    }

                    const max = Number.parseFloat(amountInput?.max || '0');
                    const value = Number.parseFloat(amountInput?.value || '0');

                    if (amountInput && (!Number.isFinite(value) || value <= 0)) {
                        amountInput.setCustomValidity('Enter a valid payment amount.');
                        amountInput.reportValidity();
                        event.preventDefault();

                        return;
                    }

                    if (amountInput && Number.isFinite(max) && max > 0 && Number.isFinite(value) && value > max) {
                        amountInput.setCustomValidity('Amount cannot be greater than the remaining balance.');
                        amountInput.reportValidity();
                        event.preventDefault();

                        return;
                    }

                    amountInput?.setCustomValidity('');
                    paymentSubmissionInProgress = true;
                    recordForm.setAttribute('aria-busy', 'true');

                    if (recordSubmitButton) {
                        recordSubmitButton.disabled = true;
                        recordSubmitButton.classList.add('cursor-wait', 'opacity-70');
                    }

                    if (recordSubmitLabel) {
                        recordSubmitLabel.textContent = 'Processing…';
                    }
                });
            }

            window.addEventListener('pageshow', () => {
                paymentSubmissionInProgress = false;
                recordForm?.removeAttribute('aria-busy');

                if (recordSubmitButton) {
                    recordSubmitButton.disabled = false;
                    recordSubmitButton.classList.remove('cursor-wait', 'opacity-70');
                }

                if (recordSubmitLabel) {
                    recordSubmitLabel.textContent = 'Record Payment';
                }
            });

            const bookingRefreshId = @json(session('admin_trigger_bookings_refresh'));

            if (bookingRefreshId !== null && String(bookingRefreshId).trim() !== '') {
                const payload = JSON.stringify({
                    booking_id: String(bookingRefreshId),
                    refreshed_at: Date.now(),
                });

                try {
                    window.localStorage.setItem('hyve-admin-bookings-refresh', payload);
                } catch (error) {
                    // Ignore storage failures and keep the normal polling fallback.
                }
            }
        })();
    </script>
@endsection
