@extends('layouts.app')

@section('content')
    @php
        $member = auth()->user();
        $nextBooking = $memberInsights['next_booking'];
    @endphp
    <div class="site-shell">
        @include('partials.member.navigation')

        <main
            class="member-portal member-bookings-page section-pad"
            data-member-booking-live-sync
            data-state-url="{{ route('member.bookings.state') }}"
            data-state-version="{{ $memberBookingStateVersion }}"
        >
            <div class="section-wrap">
                <div class="flash flash--success hidden" data-member-booking-sync-notice>Booking information updated. Refreshing&hellip;</div>
                @if (session('member_success'))
                    <div class="flash flash--success">{{ session('member_success') }}</div>
                @endif

                @if (request()->routeIs('member.dashboard'))
                <section class="member-dashboard-hero">
                    <div class="member-dashboard-hero__main">
                        <div>
                            <p class="eyebrow">Member portal</p>
                            <h1>Welcome back, {{ $member->first_name }}</h1>
                            <p>Everything about your HYVE visits, payments, and reservations in one place.</p>
                        </div>
                        <div class="member-dashboard-hero__actions">
                            <a href="{{ route('bookings.index') }}" class="button button--dark">Book a space</a>
                            <a href="{{ route('member.profile.edit') }}" class="member-dashboard-hero__profile">Manage profile</a>
                        </div>
                        <span class="member-dashboard-live"><i></i> Live booking updates enabled</span>
                    </div>

                    <aside class="member-dashboard-next">
                        <span>Next visit</span>
                        @if ($nextBooking)
                            <strong>{{ $nextBooking['room_name'] }}</strong>
                            <p>{{ $nextBooking['display_date'] }}</p>
                            <small>{{ $nextBooking['time_label'] }} &middot; {{ $nextBooking['status_label'] }}</small>
                            <a href="{{ route('member.index') }}">View booking &rarr;</a>
                        @else
                            <strong>No visit scheduled</strong>
                            <p>Your next confirmed reservation will appear here.</p>
                            <a href="{{ route('bookings.index') }}">Create a booking &rarr;</a>
                        @endif
                    </aside>
                </section>

                <section class="member-dashboard-metrics" aria-label="Booking overview">
                    <article>
                        <span>Upcoming</span>
                        <strong>{{ $memberStats['upcoming_slots'] }}</strong>
                        <small>Active booking slots</small>
                    </article>
                    <article>
                        <span>Confirmed</span>
                        <strong>{{ $memberInsights['confirmed_upcoming_count'] }}</strong>
                        <small>Ready for your visit</small>
                    </article>
                    <article>
                        <span>Outstanding</span>
                        <strong>Php {{ number_format($memberInsights['outstanding_balance'], 2) }}</strong>
                        <small>Across active bookings</small>
                    </article>
                    <article>
                        <span>Total bookings</span>
                        <strong>{{ $memberStats['total_bookings'] }}</strong>
                        <small>{{ rtrim(rtrim(number_format($memberStats['total_hours'], 2), '0'), '.') }} booked hours</small>
                    </article>
                </section>

                <section
                    class="member-live-rooms"
                    data-member-live-rooms
                    data-feed-url="{{ route('member.live-rooms') }}"
                >
                    <div class="member-live-rooms__head">
                        <div>
                            <span>Live availability</span>
                            <h2>Find a room available right now</h2>
                            <p>Room availability updates automatically. Final availability is rechecked when you continue your booking.</p>
                        </div>
                        <div class="member-live-rooms__summary">
                            <strong data-live-room-available-count>{{ $memberLiveRooms['available_count'] }}</strong>
                            <span>of <b data-live-room-total-count>{{ $memberLiveRooms['total_count'] }}</b> spaces available</span>
                            <small>Updated <time data-live-room-updated>{{ $memberLiveRooms['generated_at'] }}</time></small>
                        </div>
                    </div>

                    <div class="member-live-rooms__legend" aria-label="Room availability legend">
                        <span><i class="is-available"></i>Available now</span>
                        <span><i class="is-upcoming"></i>Reserved later</span>
                        <span><i class="is-occupied"></i>Occupied</span>
                        <span><i class="is-unavailable"></i>Unavailable</span>
                    </div>

                    <div class="member-live-rooms__grid" data-live-room-grid>
                        @foreach ($memberLiveRooms['rooms'] as $room)
                            <article class="member-live-room is-{{ $room['status'] }}" data-live-room-card data-room-id="{{ $room['id'] }}">
                                <div class="member-live-room__top">
                                    <div>
                                        <span data-live-room-space>{{ $room['space_label'] }}</span>
                                        <h3 data-live-room-name>{{ $room['room_name'] }}</h3>
                                    </div>
                                    <strong data-live-room-status>{{ $room['status_label'] }}</strong>
                                </div>
                                <p data-live-room-note>{{ $room['status_note'] }}</p>
                                <small data-live-room-detail>{{ $room['availability_detail'] }}</small>
                                <a href="{{ $room['book_url'] }}" data-live-room-book>Check schedule &amp; book <span>&rarr;</span></a>
                            </article>
                        @endforeach
                    </div>

                    <div class="member-live-rooms__foot">
                        <span><i></i> Live updates every 30 seconds</span>
                        <a href="{{ route('bookings.index') }}">View full room schedule &rarr;</a>
                    </div>
                </section>

                <section class="member-action-center">
                    <div class="member-action-center__heading">
                        <div>
                            <span>Action center</span>
                            <h2>What needs your attention</h2>
                        </div>
                        <small>Updated automatically from HYVE</small>
                    </div>

                    <div class="member-action-center__items">
                        @if ($memberInsights['payment_action_count'] > 0)
                            <article class="is-attention">
                                <div class="member-action-center__icon">!</div>
                                <div>
                                    <strong>{{ $memberInsights['payment_action_count'] }} booking {{ $memberInsights['payment_action_count'] === 1 ? 'balance' : 'balances' }} due</strong>
                                    <p>Complete the remaining payment and upload your proof for verification.</p>
                                </div>
                                <a href="{{ $memberInsights['first_payment_action']['balance_payment_url'] }}">Review payment</a>
                            </article>
                        @endif

                        @if ($memberInsights['pending_approval_count'] > 0)
                            <article>
                                <div class="member-action-center__icon">{{ $memberInsights['pending_approval_count'] }}</div>
                                <div>
                                    <strong>Awaiting HYVE approval</strong>
                                    <p>{{ $memberInsights['pending_approval_count'] }} {{ $memberInsights['pending_approval_count'] === 1 ? 'booking is' : 'bookings are' }} under review. The status will update here automatically.</p>
                                </div>
                                <span class="member-action-center__status">In review</span>
                            </article>
                        @endif

                        @if ($memberInsights['payment_review_count'] > 0)
                            <article>
                                <div class="member-action-center__icon">{{ $memberInsights['payment_review_count'] }}</div>
                                <div>
                                    <strong>Payment verification in progress</strong>
                                    <p>{{ $memberInsights['payment_review_count'] }} {{ $memberInsights['payment_review_count'] === 1 ? 'payment is' : 'payments are' }} waiting for HYVE review. Another payment, cancellation, or reschedule is temporarily disabled for the affected booking.</p>
                                </div>
                                <span class="member-action-center__status">Verifying</span>
                            </article>
                        @endif

                        @if ($memberInsights['payment_action_count'] === 0 && $memberInsights['pending_approval_count'] === 0 && $memberInsights['payment_review_count'] === 0)
                            <article class="is-clear">
                                <div class="member-action-center__icon">&#10003;</div>
                                <div>
                                    <strong>You are all caught up</strong>
                                    <p>No pending booking or payment action needs your attention.</p>
                                </div>
                                <span class="member-action-center__status">All clear</span>
                            </article>
                        @endif
                    </div>
                </section>

                <section
                    class="member-announcements"
                    id="hyve-announcements"
                    data-member-announcements-panel
                    data-feed-url="{{ route('member.announcements.feed') }}"
                    data-read-all-url="{{ route('member.announcements.read-all') }}"
                    data-csrf-token="{{ csrf_token() }}"
                >
                    <div class="member-announcements__head">
                        <div>
                            <span>HYVE notifications</span>
                            <h2>Booking approvals &amp; team updates</h2>
                            <p>Your booking approvals, important workspace notices, and member updates are posted here.</p>
                        </div>
                        @if ($memberNotificationUnreadCount > 0)
                            <button type="button" data-member-announcements-read-all>Mark all as read</button>
                        @endif
                    </div>

                    <div class="member-announcements__list" data-member-announcements-list>
                        @foreach ($memberBookingNotifications as $notification)
                            <article class="member-announcement-item is-important @if(!$notification['is_read']) is-unread @endif" data-booking-notification-id="{{ $notification['id'] }}">
                                <div class="member-announcement-item__marker"></div>
                                <div>
                                    <div class="member-announcement-item__meta">
                                        <span>Booking update</span>
                                        <time>{{ $notification['published_at'] }}</time>
                                    </div>
                                    <h3>{{ $notification['title'] }}</h3>
                                    <p>{{ $notification['body'] }}</p>
                                </div>
                                @if (!$notification['is_read'])
                                    <button type="button" data-announcement-read-url="{{ $notification['read_url'] }}">Mark read</button>
                                @else
                                    <span class="member-announcement-item__read">Read</span>
                                @endif
                            </article>
                        @endforeach

                        @forelse ($memberAnnouncements as $announcement)
                            <article class="member-announcement-item is-{{ $announcement['priority'] }} @if(!$announcement['is_read']) is-unread @endif" data-announcement-id="{{ $announcement['id'] }}">
                                <div class="member-announcement-item__marker"></div>
                                <div>
                                    <div class="member-announcement-item__meta">
                                        <span>{{ $announcement['priority_label'] }}</span>
                                        <time>{{ $announcement['published_at'] }}</time>
                                    </div>
                                    <h3>{{ $announcement['title'] }}</h3>
                                    <p>{{ $announcement['body'] }}</p>
                                </div>
                                @if (!$announcement['is_read'])
                                    <button type="button" data-announcement-read-url="{{ $announcement['read_url'] }}">Mark read</button>
                                @else
                                    <span class="member-announcement-item__read">Read</span>
                                @endif
                            </article>
                        @empty
                            @if ($memberBookingNotifications->isEmpty())
                                <div class="member-updates-empty">
                                    <strong>No notifications right now.</strong>
                                    <p>Booking approvals and official HYVE announcements will appear here.</p>
                                </div>
                            @endif
                        @endforelse
                    </div>
                </section>

                <section class="member-updates-grid">
                    <article class="member-updates-card">
                        <div class="member-updates-card__head">
                            <div>
                                <span>HYVE calendar</span>
                                <h2>Upcoming events &amp; notices</h2>
                            </div>
                            <a href="{{ route('bookings.index') }}">View availability &rarr;</a>
                        </div>

                        <div class="member-events-list">
                            @forelse ($memberEvents as $event)
                                <div class="member-event-item">
                                    <div class="member-event-date">
                                        <span>{{ $event['month'] }}</span>
                                        <strong>{{ $event['day'] }}</strong>
                                    </div>
                                    <div class="member-event-copy">
                                        <span>{{ $event['type_label'] }}</span>
                                        <strong>{{ $event['title'] }}</strong>
                                        <p>{{ $event['date_label'] }} &middot; {{ $event['time_label'] }}</p>
                                        <small>{{ $event['scope_label'] }}</small>
                                    </div>
                                    @if ($event['affects_booking'])
                                        <em>Booking affected</em>
                                    @else
                                        <em class="is-open">Open notice</em>
                                    @endif
                                </div>
                            @empty
                                <div class="member-updates-empty">
                                    <strong>No upcoming public events yet.</strong>
                                    <p>New HYVE events and calendar notices will appear here automatically.</p>
                                </div>
                            @endforelse
                        </div>
                    </article>

                    <article class="member-updates-card">
                        <div class="member-updates-card__head">
                            <div>
                                <span>Member savings</span>
                                <h2>Discount &amp; promo guide</h2>
                            </div>
                        </div>

                        <div class="member-promos-list">
                            @foreach ($memberPromotions as $promotion)
                                <div class="member-promo-item">
                                    <span>{{ $promotion['badge'] }}</span>
                                    <div>
                                        <strong>{{ $promotion['title'] }}</strong>
                                        <p>{{ $promotion['note'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="member-promos-footnote">Discounts are subject to booking eligibility and HYVE front-desk verification. Applicable IDs or references may be required.</p>
                    </article>
                </section>
                @endif

                @if (request()->routeIs('member.index'))
                <section class="member-bookings-page__intro">
                    <p class="eyebrow">Member portal</p>
                    <h1>My Bookings</h1>
                    <p>A focused list of your upcoming and previous reservations. Select a booking to view its full details and available actions.</p>
                </section>
                <section class="member-bookings-page__tabs" data-bookings-tabs id="member-upcoming-bookings">
                    <div class="member-bookings-page__section-head">
                        <div>
                            <span>Your reservations</span>
                            <h2>Booking activity</h2>
                        </div>
                        <p>Click a booking to view complete details and available actions.</p>
                    </div>
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
                @endif

            </div>
        </main>
    </div>
@endsection
