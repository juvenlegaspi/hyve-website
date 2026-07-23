@extends('layouts.app')

@section('content')
    @php
        $member = auth()->user();
        $initials = strtoupper(substr((string) $member->first_name, 0, 1).substr((string) $member->last_name, 0, 1));
    @endphp

    <div class="site-shell">
        @include('partials.home.navigation')

        <main class="member-portal section-pad">
            <div class="section-wrap">
                @if (session('member_success'))
                    <div class="flash flash--success">{{ session('member_success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="flash flash--error">
                        Please review your balance payment details.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="member-bookings-page__intro">
                    <p class="eyebrow">My account</p>
                    <h1>Pay Remaining Balance</h1>
                    <p>Complete the unpaid balance for this booking and upload your latest payment proof.</p>
                </section>

                <section class="member-account-shell">
                    <div class="member-account-strip">
                        <div class="member-account-strip__identity">
                            <div class="member-account-strip__avatar">{{ $initials }}</div>
                            <div>
                                <strong>{{ $member->name }}</strong>
                                <span>{{ $member->email }}</span>
                            </div>
                        </div>

                        <div class="member-account-strip__links">
                            <a href="{{ route('member.index') }}" class="member-account-strip__link is-active">My bookings</a>
                            <a href="{{ route('member.profile.edit') }}" class="member-account-strip__link">Edit profile</a>
                            <a href="{{ route('member.password.edit') }}" class="member-account-strip__link">Change password</a>
                        </div>
                    </div>
                </section>

                <section class="booking-checkout reveal">
                    <button type="button" class="booking-checkout__back" onclick="window.location.href='{{ route('member.index') }}'">&larr; Back to bookings</button>

                    <div class="booking-checkout__grid">
                        <div class="booking-checkout__main">
                            <div class="booking-checkout__panel">
                                <h2>Pay booking balance</h2>
                                <p>Enter the amount you paid, then upload your proof so admin can verify it.</p>

                                <form action="{{ route('member.bookings.balance-payment.store', $selectedHeader) }}" method="POST" enctype="multipart/form-data" class="member-form">
                                    @csrf
                                    <input type="hidden" name="detail_id" value="{{ $selectedBalanceItem['detail_id'] ?? '' }}">

                                    <div class="booking-details-main">
                                        <div class="booking-checkout__supporting">
                                            <div class="booking-balance-banner">
                                                <span>Current remaining balance</span>
                                                <strong>Php {{ number_format((float) $selectedHeaderSummary['balance_amount'], 2) }}</strong>
                                            </div>

                                            <label>
                                                <span>Payment amount</span>
                                                <input
                                                    type="number"
                                                    name="payment_amount"
                                                    min="0.01"
                                                    max="{{ number_format((float) $selectedHeaderSummary['balance_amount'], 2, '.', '') }}"
                                                    step="0.01"
                                                    value="{{ old('payment_amount', number_format((float) $selectedPaymentAmount, 2, '.', '')) }}"
                                                >
                                                @error('payment_amount') <small class="field-error">{{ $message }}</small> @enderror
                                                <small>Enter any amount up to the remaining balance.</small>
                                            </label>

                                            <div class="booking-checkout__method-block">
                                                <span>Payment scope</span>
                                                <div class="booking-checkout__methods booking-checkout__methods--compact">
                                                    <label class="booking-checkout__method-card @if(old('payment_scope', 'single') === 'single') is-active @endif">
                                                        <input type="radio" name="payment_scope" value="single" class="hidden" @checked(old('payment_scope', 'single') === 'single')>
                                                        <strong>Pay selected booking only</strong>
                                                    </label>
                                                    <label class="booking-checkout__method-card @if(old('payment_scope') === 'all') is-active @endif">
                                                        <input type="radio" name="payment_scope" value="all" class="hidden" @checked(old('payment_scope') === 'all')>
                                                        <strong>Pay all remaining</strong>
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="booking-checkout__method-block">
                                                <span>Payment method</span>
                                                <div class="booking-checkout__methods booking-checkout__methods--compact">
                                                    <label class="booking-checkout__method-card @if(old('payment_method', $selectedHeader->payment_method) === 'gcash') is-active @endif">
                                                        <input type="radio" name="payment_method" value="gcash" class="hidden" @checked(old('payment_method', $selectedHeader->payment_method) === 'gcash')>
                                                        <span class="booking-checkout__method-icon">$</span>
                                                        <strong>GCash</strong>
                                                    </label>
                                                    <label class="booking-checkout__method-card @if(old('payment_method', $selectedHeader->payment_method) === 'bank_transfer') is-active @endif">
                                                        <input type="radio" name="payment_method" value="bank_transfer" class="hidden" @checked(old('payment_method', $selectedHeader->payment_method) === 'bank_transfer')>
                                                        <span class="booking-checkout__method-icon">B</span>
                                                        <strong>Bank Transfer</strong>
                                                    </label>
                                                </div>
                                            </div>

                                            <label class="field-grid__wide">
                                                <span>Payment proof</span>
                                                <input type="file" name="payment_proof" accept="image/*">
                                            </label>

                                            <label>
                                                <span>Notes</span>
                                                <textarea name="notes" rows="4" placeholder="Add any note about this balance payment.">{{ old('notes') }}</textarea>
                                            </label>

                                            <div class="agreement-consent">
                                                <label>
                                                    <input type="checkbox" name="rules_agreement" value="1" data-agreement-checkbox @checked(old('rules_agreement'))>
                                                    <span>
                                                        I have read and agree to the
                                                        <button type="button" class="agreement-link-button" data-agreement-open="member-payment-agreement-modal">Rules &amp; Agreement</button>
                                                        of HYVE before submitting this payment.
                                                    </span>
                                                </label>
                                                @error('rules_agreement') <small class="field-error">{{ $message }}</small> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="booking-checkout__submit" data-agreement-submit disabled>Submit balance payment</button>
                                </form>
                            </div>
                        </div>

                        <aside class="booking-checkout__side">
                            <div class="booking-checkout__panel booking-checkout__panel--summary">
                                <h2>Booking summary</h2>

                                @foreach ($selectedHeaderSummary['items'] as $item)
                                    <article class="booking-checkout__schedule-item @if (($selectedBalanceItem['detail_id'] ?? null) === $item['detail_id']) booking-checkout__schedule-item--selected @endif">
                                        <strong>{{ $item['room_name'] }} - {{ $item['space_label'] }}</strong>
                                        <span>{{ $item['date_label'] }} - {{ $item['time_label'] }}</span>
                                        <em>Php {{ number_format((float) $item['amount'], 2) }}</em>
                                    </article>
                                @endforeach

                                <div class="booking-checkout__summary-row">
                                    <span>Reference</span>
                                    <strong>{{ $selectedHeaderSummary['reference_no'] }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Paid so far</span>
                                    <strong>Php {{ number_format((float) $selectedHeaderSummary['downpayment_amount'], 2) }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Remaining balance</span>
                                    <strong>Php {{ number_format((float) $selectedHeaderSummary['balance_amount'], 2) }}</strong>
                                </div>
                                <div class="booking-checkout__summary-total">
                                    <span>Total booking amount</span>
                                    <strong>Php {{ number_format((float) $selectedHeaderSummary['total_amount'], 2) }}</strong>
                                </div>

                                @if ($paymentSetting)
                                    <div class="booking-checkout__note" style="margin-top:1rem;">
                                        @php
                                            $selectedMethod = old('payment_method', $selectedHeader->payment_method);
                                        @endphp

                                        @if ($selectedMethod === 'bank_transfer')
                                            <div>
                                                <p>{{ $paymentSetting->bank_name ?? 'Sample Bank' }}</p>
                                                <p>{{ $paymentSetting->bank_account_name ?? 'HYVE Workspace' }}</p>
                                                <p>{{ $paymentSetting->bank_account_number ?? '012345678901' }}</p>
                                                @if ($paymentSetting->bank_qr_path)
                                                    <div style="margin-top:0.75rem;">
                                                        <img src="{{ route('payment-qr.show', 'bank') }}" alt="Bank transfer QR code" style="width:min(100%, 220px); border-radius:1rem; border:1px solid #dfe7d8; background:#fff; padding:0.7rem;">
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div>
                                                <p>{{ $paymentSetting->gcash_account_name ?? 'HYVE Workspace' }}</p>
                                                <p>{{ $paymentSetting->gcash_number ?? '0917 123 4567' }}</p>
                                                @if ($paymentSetting->gcash_qr_path)
                                                    <div style="margin-top:0.75rem;">
                                                        <img src="{{ route('payment-qr.show', 'gcash') }}" alt="GCash QR code" style="width:min(100%, 220px); border-radius:1rem; border:1px solid #dfe7d8; background:#fff; padding:0.7rem;">
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </main>
    </div>

    @include('partials.payment-agreement-modal', ['modalId' => 'member-payment-agreement-modal'])
@endsection
