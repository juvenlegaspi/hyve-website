@extends('layouts.app')

@section('content')
    @php
        $spaceImageMap = collect(config('hyve.spaces', []))->mapWithKeys(fn (array $space): array => [$space['title'] => asset($space['image'])]);
        $rateMap = collect(config('hyve.rates', []))->mapWithKeys(fn (array $rate): array => [$rate['title'] => $rate['day_use']['2 hrs min'] ?? $rate['day_use']['Daily'] ?? 'Ask HYVE']);
        $selectedSpaceSlug = request('space');
        $preselectedRoom = $hyveRooms->first(fn ($room) => \Illuminate\Support\Str::slug($room->mappedSpaceLabel()) === $selectedSpaceSlug);
        $initialRoomId = (string) old('hyve_room_id', $preselectedRoom?->id ?? $hyveRooms->first()?->id ?? '');
        $initialDate = old('booking_date', now()->toDateString());
        $oldScheduleItems = old('selected_schedule_items', '[]');
        $oldScheduleItemsJson = is_array($oldScheduleItems)
            ? json_encode($oldScheduleItems, JSON_UNESCAPED_SLASHES)
            : (string) $oldScheduleItems;
        $oldBookingMode = old('booking_mode', 'room');
        $hasScheduleSelection = filled($oldScheduleItemsJson) && $oldScheduleItemsJson !== '[]';
        $showCheckout = $errors->any() && ((old('start_time') && old('end_time')) || $hasScheduleSelection);
        $scheduleSummary = $oldBookingMode === 'schedule' ? ($oldScheduleSummary ?? null) : null;
        $scheduleSummarySlotCount = (int) ($scheduleSummary['slot_count'] ?? 0);
        $scheduleSummaryDateCount = (int) ($scheduleSummary['date_count'] ?? 0);
        $scheduleSummaryFirstDate = $scheduleSummary['first_date'] ?? null;
        $scheduleSummaryTotal = (float) ($scheduleSummary['total_amount'] ?? 0);
        $scheduleSummaryMinimum = (float) ($scheduleSummary['minimum_downpayment_amount'] ?? 0);
        $scheduleSummaryBalance = (float) ($scheduleSummary['remaining_balance'] ?? 0);
        $scheduleSummaryItems = $scheduleSummary['items'] ?? [];
        $scheduleCheckoutRoom = $scheduleSummarySlotCount > 0
            ? $scheduleSummarySlotCount.' selected slot'.($scheduleSummarySlotCount === 1 ? '' : 's')
            : 'Choose a room';
        $scheduleCheckoutDate = $scheduleSummaryDateCount === 1 && $scheduleSummaryFirstDate
            ? \Illuminate\Support\Carbon::parse($scheduleSummaryFirstDate)->format('F j, Y')
            : ($scheduleSummaryDateCount > 1 ? $scheduleSummaryDateCount.' booking dates' : \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y'));
        $scheduleCheckoutDuration = $scheduleSummarySlotCount > 0
            ? $scheduleSummarySlotCount.' hour'.($scheduleSummarySlotCount === 1 ? '' : 's').' total'
            : '--';
        $guestFullName = trim((string) old('full_name', ''));
        $guestNameParts = preg_split('/\s+/', $guestFullName, 2) ?: ['', ''];
        $guestFirstName = $guestNameParts[0] ?? '';
        $guestLastName = $guestNameParts[1] ?? '';
    @endphp

    <div class="booking-page booking-page--calendar" data-booking-page>
        <header class="booking-topbar">
            <div class="section-wrap booking-topbar__inner">
                <a href="{{ route('home') }}" class="brand-mark">
                    <span>
                        <strong>HYVE Workspace</strong>
                        <small>Booking Desk</small>
                    </span>
                </a>

                <div class="booking-topbar__actions">
                    <a href="{{ route('home') }}" class="button button--ghost">Back Home</a>
                    @guest
                        <a href="{{ route('login') }}" class="nav-link nav-link--muted">Log In</a>
                    @endguest
                    <a href="{{ route('bookings.index') }}" class="button button--dark">Book Now</a>
                </div>
            </div>
        </header>

        <main class="section-wrap booking-calendar-shell">
            @if (session('booking_success'))
                <div class="flash flash--success">{{ session('booking_success') }}</div>
            @endif

            @if ($errors->any())
                <div class="flash flash--error">
                    Please review the booking details below. Some fields still need attention.
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('bookings.store') }}"
                enctype="multipart/form-data"
                class="booking-calendar-form"
                data-booking-form
                data-availability-url="{{ $bookingConfig['availability_url'] }}"
                data-unavailable-dates-url="{{ $bookingConfig['unavailable_dates_url'] }}"
                data-quote-url="{{ $bookingConfig['quote_url'] }}"
                data-layout-url="{{ $bookingConfig['layout_url'] }}"
                data-minimum-duration="{{ $bookingConfig['minimum_duration_minutes'] }}"
                data-unavailable-dates-horizon="30"
                data-show-checkout="{{ $showCheckout ? 'true' : 'false' }}"
            >
                @csrf

                <select name="hyve_room_id" data-room-select class="hidden">
                    <option value="">Select a room</option>
                    @foreach ($hyveRooms as $room)
                        <option value="{{ $room->id }}" @selected($initialRoomId === (string) $room->id)>{{ $room->room_name }} - {{ $room->mappedSpaceLabel() }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="booking_date" value="{{ $initialDate }}" data-booking-date>
                <input type="hidden" name="booking_mode" value="{{ $oldBookingMode }}" data-booking-mode-input>
                <input type="hidden" name="selected_schedule_items" value="{{ $oldScheduleItemsJson }}" data-schedule-items-input>
                <select name="start_time" data-start-time-select class="hidden">
                    <option value="{{ old('start_time') }}">{{ old('start_time') }}</option>
                </select>
                <select name="end_time" data-end-time-select class="hidden">
                    <option value="{{ old('end_time') }}">{{ old('end_time') }}</option>
                </select>

                <div data-booking-picker @class(['hidden' => $showCheckout])>
                    <section class="booking-calendar-intro reveal">
                        <p class="eyebrow">Book a Space</p>
                        <h1 class="section-title">Book a room</h1>
                        <p class="section-copy">Pick your date and time. Rates adjust automatically for peak hours, weekends, and holidays.</p>

                        <div class="booking-calendar-tabs">
                            <button type="button" class="booking-calendar-tab booking-calendar-tab--active" data-booking-mode-trigger data-booking-mode-value="room">Book by room</button>
                            <button type="button" class="booking-calendar-tab" data-booking-mode-trigger data-booking-mode-value="schedule">Full schedule - all rooms</button>
                        </div>
                    </section>

                    <div data-booking-mode-panel="room">
                    <section class="booking-room-strip reveal">
                        <div class="booking-room-strip__viewport">
                            <button type="button" class="booking-room-strip__nav booking-room-strip__nav--prev" data-room-scroll-prev aria-label="Scroll rooms left">&#8249;</button>
                            <div class="booking-room-strip__rail" data-room-cards>
                                @foreach ($hyveRooms as $room)
                                    <button
                                        type="button"
                                        class="booking-room-card @if ($initialRoomId === (string) $room->id) is-active @endif"
                                        data-room-card
                                        data-room-id="{{ $room->id }}"
                                        data-room-name="{{ $room->room_name }}"
                                        data-room-space="{{ $room->mappedSpaceLabel() }}"
                                        data-room-description="{{ $room->description }}"
                                        data-room-rate="{{ $rateMap[$room->mappedSpaceLabel()] ?? 'Ask HYVE' }}"
                                    >
                                        <img src="{{ $spaceImageMap[$room->mappedSpaceLabel()] ?? asset('images/office.png') }}" alt="{{ $room->room_name }}">
                                        <span class="booking-room-card__body">
                                            <strong>{{ $room->room_name }}</strong>
                                            <small>{{ $room->mappedSpaceLabel() }}</small>
                                        </span>
                                        <span class="booking-room-card__arrow">&#8250;</span>
                                    </button>
                                @endforeach
                            </div>
                            <button type="button" class="booking-room-strip__nav booking-room-strip__nav--next" data-room-scroll-next aria-label="Scroll rooms right">&#8250;</button>
                        </div>
                    </section>

                    <section class="booking-calendar-grid reveal">
                        <article class="booking-calendar-panel">
                            <div class="booking-calendar-panel__head">
                                <h2 class="booking-calendar-title" data-calendar-title>Calendar</h2>
                                <div class="booking-calendar-nav">
                                    <button type="button" data-calendar-prev aria-label="Previous month">&#8249;</button>
                                    <button type="button" data-calendar-next aria-label="Next month">&#8250;</button>
                                </div>
                            </div>

                            <div class="booking-calendar-weekdays">
                                <span>Su</span>
                                <span>Mo</span>
                                <span>Tu</span>
                                <span>We</span>
                                <span>Th</span>
                                <span>Fr</span>
                                <span>Sa</span>
                            </div>

                            <div class="booking-calendar-days" data-calendar-days></div>

                            <div class="booking-calendar-legend">
                                <span><i class="is-today"></i>Today</span>
                                <span><i class="is-selected"></i>Selected</span>
                                <span><i class="is-booked"></i>Fully booked</span>
                            </div>
                        </article>

                        <article class="booking-slot-panel">
                            <div class="booking-slot-panel__head">
                                <h2 data-slot-date-title>{{ \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y') }}</h2>
                                <p><span data-selected-room-name>{{ $preselectedRoom?->room_name ?? $hyveRooms->first()?->room_name ?? 'Choose a room' }}</span> - <span data-selected-room-space>{{ $preselectedRoom?->mappedSpaceLabel() ?? $hyveRooms->first()?->mappedSpaceLabel() ?? '' }}</span> - <span data-selected-room-rate>{{ $rateMap[$preselectedRoom?->mappedSpaceLabel() ?? $hyveRooms->first()?->mappedSpaceLabel() ?? ''] ?? 'Ask HYVE' }}</span></p>
                                <p data-selected-room-meta>Choose the exact room first, then pick an available date and start time.</p>
                            </div>

                            <div class="booking-slot-panel__legend">
                                <span><i class="is-offpeak"></i>Available</span>
                                <span><i class="is-peak"></i>Peak rate</span>
                                <span><i class="is-booked"></i>Booked</span>
                            </div>

                            <div class="booking-slot-section" data-start-step>
                                <p class="mini-title">Step 1 - Pick a start time</p>
                                <p class="booking-slot-copy" data-availability-message-body>Select a room and date first. Available times will appear here.</p>
                                <div class="booking-slot-list" data-start-slots></div>
                            </div>

                            <div class="booking-slot-start-summary hidden" data-start-summary>
                                <div>
                                    <span class="booking-slot-start-summary__label">Start Time</span>
                                    <strong data-start-summary-time>--:--</strong>
                                </div>
                                <button type="button" class="booking-slot-start-summary__change" data-start-summary-change>Change</button>
                            </div>

                            <div class="booking-slot-section">
                                <p class="mini-title">Step 2 - Pick an end time</p>
                                <p class="booking-slot-copy" data-duration-display>Choose a start time first to continue.</p>
                                <div class="booking-slot-list" data-end-slots></div>
                            </div>

                            <div class="booking-inline-summary hidden" data-inline-summary>
                                <div class="booking-inline-summary__row">
                                    <span>Date</span>
                                    <strong data-summary-date>{{ \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y') }}</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>Start</span>
                                    <strong data-summary-start>--:--</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>End</span>
                                    <strong data-summary-end>--:--</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>Duration</span>
                                    <strong data-summary-duration>--</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>Rate breakdown</span>
                                    <strong data-summary-rate>--</strong>
                                </div>
                                <div class="booking-inline-summary__row booking-inline-summary__row--total">
                                    <span>Total</span>
                                    <strong data-summary-total>Php 0.00</strong>
                                </div>
                            </div>

                            <button type="button" class="booking-slot-continue" data-slot-continue disabled>Continue to checkout -&gt;</button>
                        </article>
                    </section>
                    </div>

                    <section class="booking-schedule reveal hidden" data-booking-mode-panel="schedule">
                        <div class="booking-schedule__head">
                            <div class="booking-schedule__date-nav">
                                <button type="button" class="booking-schedule__date-button" data-schedule-prev aria-label="Previous day">&#8249;</button>
                                <strong data-schedule-date-title>{{ \Illuminate\Support\Carbon::parse($initialDate)->format('D, F j') }}</strong>
                                <button type="button" class="booking-schedule__date-button" data-schedule-next aria-label="Next day">&#8250;</button>
                            </div>

                            <div class="booking-schedule__legend">
                                <span><i class="is-selected"></i>In your cart</span>
                                <span><i class="is-available"></i>Available</span>
                                <span><i class="is-unavailable"></i>Unavailable</span>
                            </div>
                        </div>

                        <div class="booking-schedule__intro">
                            <div>
                                <h2>Full schedule - all rooms</h2>
                                <p>Pick from current and upcoming one-hour slots only. Past hours and booked slots cannot be selected.</p>
                            </div>
                        </div>

                        <div class="booking-schedule__top-scroll hidden" data-schedule-top-scroll aria-hidden="true">
                            <div class="booking-schedule__top-scroll-inner" data-schedule-top-scroll-inner></div>
                        </div>

                        <div class="booking-schedule__table-wrap" data-schedule-table-wrap>
                            <table class="booking-schedule__table">
                                <thead data-schedule-head></thead>
                                <tbody data-schedule-body></tbody>
                            </table>
                        </div>

                        <div class="booking-schedule__selection">
                            <div class="booking-schedule__selection-copy">
                                <p class="mini-title">Selected bookings</p>
                                <div data-schedule-selection-empty>
                                    <strong>Tap any available hour to add it to your cart.</strong>
                                    <p>You can mix different rooms and time slots for the same booking date.</p>
                                </div>
                                <div class="hidden" data-schedule-selection-filled>
                                    <strong data-schedule-selection-room>0 slots selected</strong>
                                    <p data-schedule-selection-meta>Pick another available hour to add more bookings.</p>
                                </div>
                            </div>

                            <div class="booking-schedule__selection-total">
                                <span>Total</span>
                                <strong data-schedule-selection-total>Php 0.00</strong>
                            </div>
                        </div>

                        <div class="booking-schedule__cart hidden" data-schedule-cart-panel>
                            <div class="booking-schedule__cart-head">
                                <p class="mini-title">Your bookings</p>
                                <strong data-schedule-cart-count>0 items</strong>
                            </div>
                            <div class="booking-schedule__cart-list" data-schedule-cart-list></div>
                            @error('selected_schedule_items') <small class="field-error">{{ $message }}</small> @enderror
                        </div>

                        <button type="button" class="booking-slot-continue" data-schedule-continue disabled>Continue to checkout -&gt;</button>
                    </section>
                </div>

                <section class="booking-checkout reveal @if (! $showCheckout) hidden @endif" data-booking-checkout>
                    <button type="button" class="booking-checkout__back" data-checkout-back>&larr; Back to booking</button>
                    <h1 class="booking-checkout__title">Complete your booking</h1>

                    <div class="booking-checkout__grid">
                        <div class="booking-checkout__main">
                            <div class="booking-checkout__panel">
                                <h2>Your details</h2>
                                <p>Enter your details to confirm the booking.</p>

                                <div class="booking-details-main">
                                    @guest
                                        <div class="field-grid field-grid--guest">
                                            <label>
                                                <span>First name</span>
                                                <input type="text" value="{{ $guestFirstName }}" placeholder="Maria" data-guest-first-name>
                                            </label>
                                            <label>
                                                <span>Last name</span>
                                                <input type="text" value="{{ $guestLastName }}" placeholder="Santos" data-guest-last-name>
                                            </label>
                                            <input type="hidden" name="full_name" value="{{ old('full_name') }}" data-guest-full-name>
                                            @error('full_name') <small class="field-error field-error--full">{{ $message }}</small> @enderror
                                            <label class="field-grid__wide">
                                                <span>Email address</span>
                                                <input type="email" name="email" value="{{ old('email') }}" placeholder="maria@email.com">
                                                @error('email') <small class="field-error">{{ $message }}</small> @enderror
                                            </label>
                                            <label class="field-grid__wide">
                                                <span>Phone number</span>
                                                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+63 912 345 6789">
                                                @error('phone') <small class="field-error">{{ $message }}</small> @enderror
                                            </label>
                                        </div>
                                    @endguest

                                    <div class="booking-checkout__supporting">
                                    <div class="field-grid field-grid--booking-meta">
                                        <label>
                                            <span>Guests</span>
                                            <input type="number" name="guests" min="1" max="20" value="{{ old('guests', 1) }}">
                                            @error('guests') <small class="field-error">{{ $message }}</small> @enderror
                                        </label>
                                        <label>
                                            <span>Downpayment</span>
                                            <input type="number" name="downpayment_amount" min="0.01" step="0.01" value="{{ old('downpayment_amount') }}" data-downpayment-input>
                                            @error('downpayment_amount') <small class="field-error">{{ $message }}</small> @enderror
                                        </label>
                                        <div class="booking-checkout__method-block">
                                            <span>Payment method</span>
                                            <select name="payment_method" data-payment-method class="hidden">
                                                <option value="">Select a payment method</option>
                                                <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                            </select>
                                            <div class="booking-checkout__methods" data-payment-method-cards>
                                                <button type="button" class="booking-checkout__method-card" data-payment-choice="gcash">
                                                    <span class="booking-checkout__method-icon">$</span>
                                                    <strong>GCash</strong>
                                                </button>
                                                <button type="button" class="booking-checkout__method-card" data-payment-choice="bank_transfer">
                                                    <span class="booking-checkout__method-icon">B</span>
                                                    <strong>Bank Transfer</strong>
                                                </button>
                                            </div>
                                            @error('payment_method') <small class="field-error">{{ $message }}</small> @enderror
                                        </div>
                                    </div>

                                    <div class="field-grid">
                                        <label class="field-grid__wide">
                                            <span>Payment Proof</span>
                                            <input type="file" name="payment_proof" accept="image/*">
                                            @error('payment_proof') <small class="field-error">{{ $message }}</small> @enderror
                                        </label>
                                    </div>

                                    <label>
                                        <span>Notes</span>
                                        <textarea name="notes" rows="4" placeholder="Tell HYVE anything important about your setup or purpose.">{{ old('notes') }}</textarea>
                                        @error('notes') <small class="field-error">{{ $message }}</small> @enderror
                                    </label>
                                    </div>
                                </div>

                                <button type="submit" class="booking-checkout__submit" data-checkout-submit>Confirm &amp; Pay Php 0.00</button>
                            </div>
                        </div>

                        <aside class="booking-checkout__side">
                            <div class="booking-checkout__panel booking-checkout__panel--summary">
                                <h2>Booking summary</h2>

                                <p class="booking-checkout__meta @if ($oldBookingMode !== 'schedule' || $scheduleSummarySlotCount < 1) hidden @endif" data-checkout-schedule-count>
                                    {{ $scheduleSummarySlotCount > 0 ? $scheduleSummarySlotCount.' booking'.($scheduleSummarySlotCount === 1 ? '' : 's') : '0 bookings' }}
                                </p>

                                <div class="@if ($oldBookingMode === 'schedule' && $scheduleSummarySlotCount > 0) hidden @endif" data-checkout-standard-summary>

                                <div class="booking-checkout__summary-row">
                                    <span>Room</span>
                                    <strong data-checkout-room>{{ $oldBookingMode === 'schedule' ? $scheduleCheckoutRoom : (($preselectedRoom?->room_name ?? $hyveRooms->first()?->room_name ?? 'Choose a room').' · '.($preselectedRoom?->mappedSpaceLabel() ?? $hyveRooms->first()?->mappedSpaceLabel() ?? '')) }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Date</span>
                                    <strong data-checkout-date>{{ $oldBookingMode === 'schedule' ? $scheduleCheckoutDate : \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y') }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Start</span>
                                    <strong data-checkout-start>{{ $oldBookingMode === 'schedule' ? ($scheduleSummarySlotCount > 0 ? 'Multiple' : '--:--') : old('start_time', '--:--') }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>End</span>
                                    <strong data-checkout-end>{{ $oldBookingMode === 'schedule' ? ($scheduleSummarySlotCount > 0 ? 'Multiple' : '--:--') : old('end_time', '--:--') }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Duration</span>
                                    <strong data-checkout-duration>{{ $oldBookingMode === 'schedule' ? $scheduleCheckoutDuration : '--' }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Minimum downpayment</span>
                                    <strong data-quote-minimum-downpayment>{{ $oldBookingMode === 'schedule' ? 'Php '.number_format($scheduleSummaryMinimum, 2) : 'Php 0.00' }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Remaining balance</span>
                                    <strong data-quote-balance>{{ $oldBookingMode === 'schedule' ? 'Php '.number_format($scheduleSummaryBalance, 2) : 'Php 0.00' }}</strong>
                                </div>
                                </div>

                                <div class="booking-checkout__schedule-list @if ($oldBookingMode !== 'schedule' || $scheduleSummarySlotCount < 1) hidden @endif" data-checkout-schedule-list>
                                    @foreach ($scheduleSummaryItems as $item)
                                        <article class="booking-checkout__schedule-item">
                                            <strong>{{ $item['room_name'] }} - {{ $item['room_space'] }}</strong>
                                            <span>{{ \Illuminate\Support\Carbon::parse($item['booking_date'])->format('F j, Y') }} - {{ $item['label'] }} - 1 hour</span>
                                            <em>Php {{ number_format((float) $item['total_amount'], 2) }}</em>
                                        </article>
                                    @endforeach
                                </div>

                                <div class="booking-checkout__summary-total">
                                    <span>Total</span>
                                    <strong data-quote-total>{{ $oldBookingMode === 'schedule' ? 'Php '.number_format($scheduleSummaryTotal, 2) : 'Php 0.00' }}</strong>
                                </div>

                                <div class="booking-checkout__note">
                                    <div data-payment-gcash class="hidden">
                                        <p>{{ $paymentSetting?->gcash_account_name ?? 'HYVE Workspace' }}</p>
                                        <p>{{ $paymentSetting?->gcash_number ?? '0917 123 4567' }}</p>
                                    </div>
                                    <div data-payment-bank class="hidden">
                                        <p>{{ $paymentSetting?->bank_name ?? 'Sample Bank' }}</p>
                                        <p>{{ $paymentSetting?->bank_account_name ?? 'HYVE Workspace' }}</p>
                                        <p>{{ $paymentSetting?->bank_account_number ?? '012345678901' }}</p>
                                    </div>
                                    <p data-payment-instructions>{{ $paymentSetting?->instructions ?? 'Send the required downpayment first, then upload your proof for checking.' }}</p>
                                </div>

                                <p class="booking-checkout__meta" data-quote-meta>
                                    @if ($oldBookingMode === 'schedule' && $scheduleSummarySlotCount > 0)
                                        Full schedule cart | {{ $scheduleSummarySlotCount }} hour slot(s) | {{ $scheduleSummary['room_count'] ?? 0 }} room(s).
                                    @else
                                        Choose a room, date, start time, and end time first to load your live rate summary.
                                    @endif
                                </p>
                            </div>
                        </aside>
                    </div>
                </section>
            </form>
        </main>
    </div>
@endsection
