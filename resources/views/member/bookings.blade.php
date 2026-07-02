@extends('layouts.app')

@section('content')
    <div class="site-shell">
        @include('partials.home.navigation')

        <main class="member-portal member-bookings-page section-pad">
            <div class="section-wrap">
                @if (session('member_success'))
                    <div class="flash flash--success">{{ session('member_success') }}</div>
                @endif

                <section class="member-bookings-page__intro">
                    <p class="eyebrow">My account</p>
                    <h1>My Bookings</h1>
                    <p>Manage your upcoming and past reservations.</p>
                </section>

                <section class="member-bookings-page__tabs" data-bookings-tabs>
                    <div class="member-bookings-page__tabbar">
                        <button type="button" class="member-bookings-page__tab is-active" data-bookings-tab="upcoming">Upcoming</button>
                        <button type="button" class="member-bookings-page__tab" data-bookings-tab="past">Past</button>
                    </div>

                    <div class="member-bookings-page__panel is-active" data-bookings-panel="upcoming" id="booking-history">
                        <div class="member-bookings-list">
                            @forelse ($upcomingBookings as $booking)
                                <button
                                    type="button"
                                    class="member-booking-card member-booking-card--button"
                                    data-booking-open
                                    data-booking-room="{{ $booking['room_name'] }}"
                                    data-booking-space="{{ $booking['space_label'] }}"
                                    data-booking-date="{{ $booking['display_date'] }}"
                                    data-booking-time="{{ $booking['time_label'] }}"
                                    data-booking-duration="{{ $booking['duration_label'] }}"
                                    data-booking-payment="{{ $booking['payment_method'] }}"
                                    data-booking-status="{{ $booking['status_label'] }}"
                                    data-booking-status-class="{{ $booking['status_class'] }}"
                                    data-booking-status-meta="{{ $booking['status_meta'] }}"
                                    data-booking-payment-badge="{{ $booking['payment_badge_label'] }}"
                                    data-booking-amount="Php {{ number_format($booking['amount'], 2) }}"
                                    data-booking-balance="Php {{ number_format($booking['remaining_balance'], 2) }}"
                                    data-booking-downpayment="Php {{ number_format($booking['downpayment_amount'], 2) }}"
                                    data-booking-reference="{{ $booking['reference_no'] }}"
                                    data-booking-wifi-code="{{ $booking['wifi_voucher']['code'] ?? '' }}"
                                    data-booking-wifi-window="@if(!empty($booking['wifi_voucher'])) {{ $booking['wifi_voucher']['valid_from'] }} to {{ $booking['wifi_voucher']['valid_until'] }} @endif"
                                    data-booking-wifi-meta="@if(!empty($booking['wifi_voucher'])) {{ $booking['wifi_voucher']['status_label'] }} - {{ $booking['wifi_voucher']['sync_status'] }} @endif"
                                    data-booking-can-cancel="{{ $booking['can_cancel'] ? '1' : '0' }}"
                                    data-booking-cancel-url="{{ route('member.bookings.cancel', $booking['booking_header_id']) }}"
                                    data-booking-can-pay-balance="{{ $booking['can_pay_balance'] ? '1' : '0' }}"
                                    data-booking-balance-url="{{ route('member.bookings.balance-payment', ['bookingHeader' => $booking['booking_header_id'], 'detail' => $booking['booking_detail_id']]) }}"
                                    data-booking-can-reschedule="{{ $booking['can_reschedule'] ? '1' : '0' }}"
                                    data-booking-reschedule-url="{{ $booking['reschedule_url'] }}"
                                >
                                    <div class="member-booking-card__main">
                                        <p class="member-booking-card__date">{{ $booking['display_date'] }}</p>
                                        <h2>{{ $booking['time_label'] }}</h2>
                                        <p class="member-booking-card__meta">{{ $booking['duration_label'] }} - {{ $booking['room_name'] }} - {{ $booking['payment_method'] }}</p>
                                    </div>
                                    <div class="member-booking-card__side">
                                        <strong>Php {{ number_format($booking['amount'], 2) }}</strong>
                                        <span class="member-booking-card__badge {{ $booking['status_class'] }}">{{ $booking['status_label'] }}</span>
                                        <span class="member-booking-card__badge member-booking-card__badge--payment {{ $booking['payment_badge_class'] }}">{{ $booking['payment_badge_label'] }}</span>
                                        <small>{{ $booking['status_meta'] }}</small>
                                    </div>
                                </button>
                            @empty
                                <div class="member-history__empty">
                                    <strong>No upcoming bookings.</strong>
                                    <p>Your next confirmed room reservations will appear here.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="member-bookings-page__panel hidden" data-bookings-panel="past">
                        <div class="member-bookings-list">
                            @forelse ($pastBookings as $booking)
                                <button
                                    type="button"
                                    class="member-booking-card member-booking-card--button"
                                    data-booking-open
                                    data-booking-room="{{ $booking['room_name'] }}"
                                    data-booking-space="{{ $booking['space_label'] }}"
                                    data-booking-date="{{ $booking['display_date'] }}"
                                    data-booking-time="{{ $booking['time_label'] }}"
                                    data-booking-duration="{{ $booking['duration_label'] }}"
                                    data-booking-payment="{{ $booking['payment_method'] }}"
                                    data-booking-status="{{ $booking['status_label'] }}"
                                    data-booking-status-class="{{ $booking['status_class'] }}"
                                    data-booking-status-meta="{{ $booking['status_meta'] }}"
                                    data-booking-payment-badge="{{ $booking['payment_badge_label'] }}"
                                    data-booking-amount="Php {{ number_format($booking['amount'], 2) }}"
                                    data-booking-balance="Php {{ number_format($booking['remaining_balance'], 2) }}"
                                    data-booking-downpayment="Php {{ number_format($booking['downpayment_amount'], 2) }}"
                                    data-booking-reference="{{ $booking['reference_no'] }}"
                                    data-booking-wifi-code="{{ $booking['wifi_voucher']['code'] ?? '' }}"
                                    data-booking-wifi-window="@if(!empty($booking['wifi_voucher'])) {{ $booking['wifi_voucher']['valid_from'] }} to {{ $booking['wifi_voucher']['valid_until'] }} @endif"
                                    data-booking-wifi-meta="@if(!empty($booking['wifi_voucher'])) {{ $booking['wifi_voucher']['status_label'] }} - {{ $booking['wifi_voucher']['sync_status'] }} @endif"
                                    data-booking-can-cancel="{{ $booking['can_cancel'] ? '1' : '0' }}"
                                    data-booking-cancel-url="{{ route('member.bookings.cancel', $booking['booking_header_id']) }}"
                                    data-booking-can-pay-balance="{{ $booking['can_pay_balance'] ? '1' : '0' }}"
                                    data-booking-balance-url="{{ route('member.bookings.balance-payment', ['bookingHeader' => $booking['booking_header_id'], 'detail' => $booking['booking_detail_id']]) }}"
                                    data-booking-can-reschedule="{{ $booking['can_reschedule'] ? '1' : '0' }}"
                                    data-booking-reschedule-url="{{ $booking['reschedule_url'] }}"
                                >
                                    <div class="member-booking-card__main">
                                        <p class="member-booking-card__date">{{ $booking['display_date'] }}</p>
                                        <h2>{{ $booking['time_label'] }}</h2>
                                        <p class="member-booking-card__meta">{{ $booking['duration_label'] }} - {{ $booking['room_name'] }} - {{ $booking['payment_method'] }}</p>
                                    </div>
                                    <div class="member-booking-card__side">
                                        <strong>Php {{ number_format($booking['amount'], 2) }}</strong>
                                        <span class="member-booking-card__badge {{ $booking['status_class'] }}">{{ $booking['status_label'] }}</span>
                                        <span class="member-booking-card__badge member-booking-card__badge--payment {{ $booking['payment_badge_class'] }}">{{ $booking['payment_badge_label'] }}</span>
                                        <small>{{ $booking['status_meta'] }}</small>
                                    </div>
                                </button>
                            @empty
                                <div class="member-history__empty">
                                    <strong>No past bookings yet.</strong>
                                    <p>Finished reservations will show up here later.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <div class="member-booking-modal hidden" data-booking-modal>
                    <div class="member-booking-modal__backdrop" data-booking-close></div>
                    <div class="member-booking-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="member-booking-modal-title">
                        <button type="button" class="member-booking-modal__close" data-booking-close aria-label="Close booking details">
                            <span aria-hidden="true">&times;</span>
                        </button>

                        <p class="member-booking-modal__eyebrow">Booking details</p>
                        <h2 id="member-booking-modal-title" data-booking-modal-room>Room</h2>
                        <p class="member-booking-modal__space" data-booking-modal-space>Workspace</p>

                        <dl class="member-booking-modal__grid">
                            <div>
                                <dt>Date</dt>
                                <dd data-booking-modal-date></dd>
                            </div>
                            <div>
                                <dt>Time</dt>
                                <dd data-booking-modal-time></dd>
                            </div>
                            <div>
                                <dt>Duration</dt>
                                <dd data-booking-modal-duration></dd>
                            </div>
                            <div>
                                <dt>Payment</dt>
                                <dd data-booking-modal-payment></dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <span class="member-booking-card__badge" data-booking-modal-status></span>
                                </dd>
                            </div>
                            <div>
                                <dt>Amount</dt>
                                <dd data-booking-modal-amount></dd>
                            </div>
                            <div>
                                <dt>Remaining balance</dt>
                                <dd data-booking-modal-balance></dd>
                            </div>
                            <div>
                                <dt>Downpayment paid</dt>
                                <dd data-booking-modal-downpayment></dd>
                            </div>
                            <div class="member-booking-modal__full">
                                <dt>Reference</dt>
                                <dd data-booking-modal-reference></dd>
                            </div>
                            <div class="member-booking-modal__full hidden" data-booking-modal-wifi-wrap>
                                <dt>WiFi voucher</dt>
                                <dd>
                                    <strong data-booking-modal-wifi-code></strong><br>
                                    <span data-booking-modal-wifi-window></span><br>
                                    <small data-booking-modal-wifi-meta></small>
                                </dd>
                            </div>
                            <div class="member-booking-modal__full">
                                <dt>Notes</dt>
                                <dd data-booking-modal-meta></dd>
                            </div>
                        </dl>

                        <div class="member-booking-modal__actions hidden" data-booking-modal-actions>
                            <a href="#" class="button button--dark hidden" data-booking-reschedule-link>Reschedule</a>
                            <a href="#" class="button button--dark member-booking-modal__pay-link hidden" data-booking-balance-link>Pay remaining balance</a>
                            <p class="member-booking-modal__warning hidden" data-booking-cancel-warning>If you cancel this booking, any payment already made will not be refunded.</p>
                            <form method="POST" class="hidden" data-booking-cancel-form onsubmit="return confirm('Cancel this booking? This action is non-refundable.');">
                                @csrf
                                <button type="submit" class="button button--dark button--danger-soft">Cancel booking</button>
                            </form>
                        </div>
                    </div>
                </div>


                <section class="member-portal__hero member-portal__hero--compact">
                    <div class="member-portal__stats">
                        <article class="member-portal__stat">
                            <span>Total bookings</span>
                            <strong>{{ $memberStats['total_bookings'] }}</strong>
                            <small>All completed and pending reservations</small>
                        </article>
                        <article class="member-portal__stat">
                            <span>Total booked hours</span>
                            <strong>{{ rtrim(rtrim(number_format($memberStats['total_hours'], 2), '0'), '.') }}</strong>
                            <small>Across all your reservations</small>
                        </article>
                        <article class="member-portal__stat">
                            <span>Upcoming slots</span>
                            <strong>{{ $memberStats['upcoming_slots'] }}</strong>
                            <small>Future and today bookings still active</small>
                        </article>
                    </div>
                </section>

                <div class="member-bookings-page__action">
                    <a href="{{ route('bookings.index') }}" class="button button--dark">Book another</a>
                </div>
            </div>
        </main>
    </div>
@endsection
