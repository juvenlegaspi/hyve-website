@extends('layouts.app')

@section('content')
    @php
        $adminMode = (bool) ($adminMode ?? false);
        $spaceImageMap = collect(config('hyve.spaces', []))->mapWithKeys(fn (array $space): array => [$space['title'] => asset($space['image'])]);
        $spaceGalleryMap = collect(config('hyve.spaces', []))->mapWithKeys(fn (array $space): array => [
            $space['title'] => collect($space['gallery'] ?? [$space['image']])->map(fn (string $image): string => asset($image))->values()->all(),
        ]);
        $rateMap = collect(config('hyve.rates', []))->mapWithKeys(fn (array $rate): array => [$rate['title'] => $rate['day_use']['2 hrs min'] ?? $rate['day_use']['Daily'] ?? 'Ask HYVE']);
        $displayRooms = $hyveRooms;
        $sharedTableRepresentativeId = $sharedTableRepresentative?->id;
        $queryBookingMode = request()->query('mode', 'room');
        $queryBookingDate = request()->query('date', now()->toDateString());
        $queryBookingEndDate = request()->query('end_date', $queryBookingDate);
        $queryStartTime = request()->query('start_time', '');
        $queryEndTime = request()->query('end_time', '');
        $initialRoomId = (string) old('hyve_room_id', $preselectedRoom?->id ?? $displayRooms->first()?->id ?? '');
        $initialDate = old('booking_date', $queryBookingDate);
        $oldScheduleItems = old('selected_schedule_items', '[]');
        $oldScheduleItemsJson = is_array($oldScheduleItems)
            ? json_encode($oldScheduleItems, JSON_UNESCAPED_SLASHES)
            : (string) $oldScheduleItems;
        $oldBookingMode = old('booking_mode', in_array($queryBookingMode, ['room', 'monthly'], true) ? $queryBookingMode : 'room');
        $oldMonthlyPlan = (string) old('monthly_plan', '');
        $initialEndDate = old('booking_end_date', $oldBookingMode === 'monthly' ? $queryBookingEndDate : $initialDate);
        $hasScheduleSelection = filled($oldScheduleItemsJson) && $oldScheduleItemsJson !== '[]';
        $hasMonthlySelection = $oldBookingMode === 'monthly' && $oldMonthlyPlan !== '' && filled($initialDate) && filled($initialEndDate);
        $showCheckout = $errors->any() && ((old('start_time') && old('end_time')) || $hasScheduleSelection || $hasMonthlySelection);
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
        $initialDisplayRoom = $preselectedRoom ?? $displayRooms->first();
        $initialDisplayName = ($initialDisplayRoom?->isSharedTable() ?? false)
            ? 'Common Area'
            : ($initialDisplayRoom?->room_name ?? 'Choose a room');
        $initialDisplaySpace = $initialDisplayRoom?->mappedSpaceLabel() ?? '';
        $initialDisplayRate = $rateMap[$initialDisplaySpace] ?? 'Ask HYVE';
        $standardCheckoutRoom = $initialDisplayName.($initialDisplaySpace ? ' - '.$initialDisplaySpace : '');
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
                        <small>{{ $adminMode ? 'Walk-In Desk' : 'Booking Desk' }}</small>
                    </span>
                </a>

                <div class="booking-topbar__actions">
                    @if ($adminMode)
                        <a href="{{ route('admin.bookings.index') }}" class="button button--ghost">Back to bookings</a>
                        <a href="{{ route('admin.dashboard') }}" class="button button--dark">Admin dashboard</a>
                    @else
                        <a href="{{ route('home') }}" class="button button--ghost">Back Home</a>
                        @auth
                            <a href="{{ route('member.index') }}" class="nav-link @if (request()->routeIs('member.*')) is-active @endif">My bookings</a>
                        @endauth
                        @guest
                            <a href="{{ route('login', ['return_to' => url()->full()]) }}" class="nav-link nav-link--muted">Log In</a>
                        @endguest
                        <a href="{{ route('bookings.index') }}" class="button button--dark">Book Now</a>
                        @auth
                            @include('partials.home.member-menu')
                        @endauth
                    @endif
                </div>
            </div>
        </header>

        <main class="section-wrap booking-calendar-shell">
            @if (session('booking_success'))
                <div class="flash flash--success">{{ session('booking_success') }}</div>
            @endif

            @if ($adminMode && session('admin_success'))
                <div class="flash flash--success">{{ session('admin_success') }}</div>
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
                action="{{ $adminMode ? route('admin.bookings.store') : route('bookings.store') }}"
                enctype="multipart/form-data"
                class="booking-calendar-form"
                data-booking-form
                data-availability-url="{{ $bookingConfig['availability_url'] }}"
                data-unavailable-dates-url="{{ $bookingConfig['unavailable_dates_url'] }}"
                data-quote-url="{{ $bookingConfig['quote_url'] }}"
                data-layout-url="{{ $bookingConfig['layout_url'] }}"
                data-minimum-duration="{{ $bookingConfig['minimum_duration_minutes'] }}"
                data-unavailable-dates-horizon="{{ $bookingConfig['unavailable_dates_horizon'] }}"
                data-show-checkout="{{ $showCheckout ? 'true' : 'false' }}"
                data-admin-mode="{{ $adminMode ? 'true' : 'false' }}"
            >
                @csrf
                <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}" data-booking-submission-token>

                <select name="hyve_room_id" data-room-select class="hidden">
                    <option value="">Select a room</option>
                    @foreach ($displayRooms as $room)
                        @php($displayName = $room->isSharedTable() ? 'Common Area' : $room->room_name)
                        <option value="{{ $room->id }}" data-common-area="{{ $room->isSharedTable() ? 'true' : 'false' }}" @selected($initialRoomId === (string) $room->id)>{{ $displayName }} - {{ $room->mappedSpaceLabel() }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="booking_date" value="{{ $initialDate }}" data-booking-date>
                <input type="hidden" name="booking_end_date" value="{{ $initialEndDate }}" data-booking-end-date>
                <input type="hidden" name="booking_mode" value="{{ $oldBookingMode }}" data-booking-mode-input>
                <input type="hidden" name="monthly_plan" value="{{ $oldMonthlyPlan }}" data-monthly-plan-input>
                <input type="hidden" name="long_stay_use_type" value="{{ old('long_stay_use_type', '') }}" data-long-stay-use-type-input>
                <input type="hidden" name="selected_schedule_items" value="{{ $oldScheduleItemsJson }}" data-schedule-items-input>
                <select name="start_time" data-start-time-select class="hidden">
                    <option value="{{ old('start_time', $queryStartTime) }}">{{ old('start_time', $queryStartTime) }}</option>
                </select>
                <select name="end_time" data-end-time-select class="hidden">
                    <option value="{{ old('end_time', $queryEndTime) }}">{{ old('end_time', $queryEndTime) }}</option>
                </select>

                <div data-booking-picker @class(['hidden' => $showCheckout])>
                    <section class="booking-calendar-intro reveal">
                        <p class="eyebrow">Book a Space</p>
                        <h1 class="section-title">{{ $adminMode ? 'Create a walk-in booking' : 'Book a room' }}</h1>
                        <p class="section-copy">
                            {{ $adminMode ? 'Use the same booking flow for walk-in customers, then submit the booking from the admin desk.' : 'Pick your date and time. Rates adjust automatically for peak hours, weekends, and holidays.' }}
                        </p>

                        <div class="booking-calendar-tabs">
                            <button type="button" class="booking-calendar-tab booking-calendar-tab--active" data-booking-mode-trigger data-booking-mode-value="room">Book by room</button>
                            <button type="button" class="booking-calendar-tab" data-booking-mode-trigger data-booking-mode-value="schedule">Full schedule - all rooms</button>
                            <button type="button" class="booking-calendar-tab" data-booking-mode-trigger data-booking-mode-value="monthly">Daily / Weekly / Monthly</button>
                        </div>
                    </section>

                    <section class="booking-room-strip reveal" data-shared-room-strip>
                        <div class="booking-room-strip__viewport">
                            <button type="button" class="booking-room-strip__nav booking-room-strip__nav--prev" data-room-scroll-prev aria-label="Scroll rooms left">&#8249;</button>
                            <div class="booking-room-strip__rail" data-room-cards>
                                @foreach ($displayRooms as $room)
                                    @php($displayName = $room->isSharedTable() ? 'Common Area' : $room->room_name)
                                    @php($monthlyOptions = $monthlyPlansByRoom[$room->id] ?? [])
                                    <div
                                        class="booking-room-card @if ($initialRoomId === (string) $room->id) is-active @endif"
                                        role="button"
                                        tabindex="0"
                                        data-room-card
                                        data-room-id="{{ $room->id }}"
                                        data-room-name="{{ $displayName }}"
                                        data-room-space="{{ $room->mappedSpaceLabel() }}"
                                        data-room-description="{{ $room->isSharedTable() ? 'A shared seating area. Your exact table will be assigned automatically based on availability.' : $room->description }}"
                                        data-room-rate="{{ $rateMap[$room->mappedSpaceLabel()] ?? 'Ask HYVE' }}"
                                        data-room-gallery='@json($spaceGalleryMap[$room->mappedSpaceLabel()] ?? [asset('images/optimized/office.webp')])'
                                        data-room-monthly-options='@json($monthlyOptions)'
                                        data-common-area="{{ $room->isSharedTable() ? 'true' : 'false' }}"
                                    >
                                        <img
                                            src="{{ $spaceImageMap[$room->mappedSpaceLabel()] ?? asset('images/optimized/office.webp') }}"
                                            alt="{{ $displayName }}"
                                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                            decoding="async"
                                            @if ($loop->first) fetchpriority="high" @else fetchpriority="low" @endif
                                        >
                                        <span class="booking-room-card__body">
                                            <strong>{{ $displayName }}</strong>
                                            <small>{{ $room->mappedSpaceLabel() }}</small>
                                            <button type="button" class="booking-room-card__preview-link" data-room-preview-open>View photos</button>
                                        </span>
                                        <span class="booking-room-card__arrow">&#8250;</span>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="booking-room-strip__nav booking-room-strip__nav--next" data-room-scroll-next aria-label="Scroll rooms right">&#8250;</button>
                        </div>
                    </section>

                    <div data-booking-mode-panel="room">
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
                                <p><span data-selected-room-name>{{ $initialDisplayName }}</span> - <span data-selected-room-space>{{ $initialDisplaySpace }}</span> - <span data-selected-room-rate>{{ $initialDisplayRate }}</span></p>
                                <p data-selected-room-meta>{{ $initialDisplayRoom?->isSharedTable() ? 'Choose your date and time first. Your exact table will be assigned automatically based on availability.' : 'Choose the exact room first, then pick an available date and start time.' }}</p>
                            </div>

                            <div class="booking-slot-panel__legend">
                                <span><i class="is-offpeak"></i>Available</span>
                                <span><i class="is-peak"></i>Peak rate</span>
                                <span><i class="is-booked"></i>Booked</span>
                            </div>

                            <div class="booking-slot-section" data-start-step>
                                <p class="mini-title">Step 1 - Pick a start time</p>
                                <p class="booking-slot-copy" data-availability-message-body>Select a room and date first. Available start times will appear here. Minimum booking is 2 hours.</p>
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
                                <p class="booking-slot-copy" data-duration-display>Choose a start time first to continue. Minimum booking is 2 hours.</p>
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
                                    <div data-summary-rate>--</div>
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
                                <p>Pick from current and upcoming available 2-hour slots. You can mix rooms and time slots, but a minimum of 2 hours total is required before checkout.</p>
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
                                <p class="mini-title">Booking status</p>
                                <div data-schedule-selection-empty>
                                    <strong>Tap any available hour to add it to your cart.</strong>
                                    <p>You can mix different rooms and 2-hour time slots for the same booking date. A minimum of 2 total hours is required before checkout.</p>
                                </div>
                                <div class="hidden" data-schedule-selection-filled>
                                    <strong data-schedule-selection-room>0 slots selected</strong>
                                    <p data-schedule-selection-meta>Pick another available hour to add more bookings.</p>
                                </div>
                            </div>

                            <div class="booking-schedule__selection-total">
                                <span>Running total</span>
                                <strong data-schedule-selection-total>Php 0.00</strong>
                            </div>
                        </div>

                        <div class="booking-schedule__cart" data-schedule-cart-panel>
                            <div class="booking-schedule__cart-head">
                                <p class="mini-title" data-schedule-cart-heading>Your bookings (0)</p>
                                <strong data-schedule-cart-count>0 items</strong>
                            </div>
                            <div class="booking-schedule__cart-empty" data-schedule-cart-empty>
                                Tap any open slot above to add it here. Selected slots will show a green check in the schedule.
                            </div>
                            <div class="booking-schedule__cart-list" data-schedule-cart-list></div>
                            <div class="booking-schedule__cart-footer">
                                <button type="button" class="button button--ghost hidden" data-schedule-clear>Clear all selections</button>
                                <div class="booking-schedule__cart-total">
                                    <span>Combined total</span>
                                    <strong data-schedule-cart-total>Php 0.00</strong>
                                </div>
                                <button type="button" class="booking-slot-continue" data-schedule-continue disabled>Continue to checkout -&gt;</button>
                            </div>
                            @error('selected_schedule_items') <small class="field-error">{{ $message }}</small> @enderror
                        </div>
                    </section>

                    <section class="booking-calendar-grid reveal hidden" data-booking-mode-panel="monthly">
                        <article class="booking-calendar-panel">
                            <div class="booking-calendar-panel__head">
                                <h2 class="booking-calendar-title">Long stay dates</h2>
                                <button type="button" class="button button--ghost booking-calendar-panel__action" data-monthly-blocked-open disabled>View blocked dates</button>
                            </div>

                            <div class="field-grid">
                                <label>
                                    <span>Start date</span>
                                    <input type="date" value="{{ $initialDate }}" min="{{ now()->toDateString() }}" data-monthly-start-date>
                                </label>
                                <label>
                                    <span>End date</span>
                                    <input type="date" value="{{ $initialEndDate }}" min="{{ $initialDate }}" data-monthly-end-date>
                                </label>
                            </div>

                            <div class="booking-slot-section hidden" data-long-stay-use-wrap>
                                <p class="mini-title">Step 2 - Choose Day Use or Night Use</p>
                                <p class="booking-slot-copy">Tell HYVE if this stay should run during the daytime or nighttime window so the correct rate can be computed.</p>
                                <div class="booking-slot-list booking-slot-list--compact">
                                    <button type="button" class="booking-slot-pill" data-long-stay-use-choice="day">
                                        <strong>Day Use</strong>
                                        <span>8:00 AM - 8:00 PM daily window</span>
                                    </button>
                                    <button type="button" class="booking-slot-pill" data-long-stay-use-choice="night">
                                        <strong>Night Use</strong>
                                        <span>8:00 PM - 8:00 AM daily window</span>
                                    </button>
                                </div>
                                <p class="booking-slot-copy">You cannot continue until you choose one stay window.</p>
                            </div>

                            <div class="booking-calendar-panel__head booking-calendar-panel__head--compact">
                                <h3 class="booking-calendar-title booking-calendar-title--small" data-monthly-calendar-title>{{ \Illuminate\Support\Carbon::parse($initialDate)->format('F Y') }}</h3>
                                <div class="booking-calendar-nav">
                                    <button type="button" data-monthly-calendar-prev aria-label="Previous month">&#8249;</button>
                                    <button type="button" data-monthly-calendar-next aria-label="Next month">&#8250;</button>
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

                            <div class="booking-calendar-days" data-monthly-calendar-days></div>

                            <div class="booking-calendar-legend">
                                <span><i class="is-selected"></i>Selected stay</span>
                                <span><i class="is-today"></i>Today</span>
                                <span><i class="is-booked"></i>Booked</span>
                            </div>

                            <p class="booking-slot-copy" data-monthly-blocked-note>Select a room first so HYVE can check which stay dates are already booked.</p>
                        </article>

                        <article class="booking-slot-panel">
                            <div class="booking-slot-panel__head">
                                <h2>Choose your stay period</h2>
                                <p><span data-monthly-room-name>{{ $initialDisplayName }}</span> - <span data-monthly-room-space>{{ $initialDisplaySpace }}</span> - <span data-monthly-room-rate>{{ $initialDisplayRate }}</span></p>
                                <p data-monthly-plan-description>Select a room first, then choose your start date and end date. HYVE will automatically compute the full long-stay breakdown for you.</p>
                            </div>

                            <div class="booking-inline-summary hidden" data-monthly-inline-summary>
                                <div class="booking-inline-summary__row">
                                    <span>Start date</span>
                                    <strong data-monthly-summary-date>{{ \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y') }}</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>End date</span>
                                    <strong data-monthly-summary-end-date>{{ \Illuminate\Support\Carbon::parse($initialEndDate)->format('F j, Y') }}</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>Plan</span>
                                    <strong data-monthly-summary-plan>{{ $oldMonthlyPlan ?: '--' }}</strong>
                                </div>
                                <div class="booking-inline-summary__row hidden" data-monthly-summary-use-type-row>
                                    <span>Stay window</span>
                                    <strong data-monthly-summary-use-type>--</strong>
                                </div>
                                <div class="booking-inline-summary__row">
                                    <span>Coverage</span>
                                    <strong data-monthly-summary-units>--</strong>
                                </div>
                                <div class="booking-inline-summary__row booking-inline-summary__row--total">
                                    <span>Total</span>
                                    <strong data-monthly-summary-total>Php 0.00</strong>
                                </div>
                            </div>

                            @error('monthly_plan') <small class="field-error">{{ $message }}</small> @enderror
                            @error('booking_date') <small class="field-error">{{ $message }}</small> @enderror
                            @error('booking_end_date') <small class="field-error">{{ $message }}</small> @enderror
                            @error('long_stay_use_type') <small class="field-error">{{ $message }}</small> @enderror

                            <button type="button" class="booking-slot-continue" data-monthly-continue disabled>Continue to checkout -&gt;</button>
                        </article>
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
                                    @if ($adminMode || auth()->guest())
                                        @if ($adminMode)
                                            <div class="mb-4 rounded-[1rem] border border-[#dce8d4] bg-[#f7faf2] p-4">
                                                <div class="mb-3">
                                                    <strong class="block text-[0.82rem] text-[#294531]">Returning customer</strong>
                                                    <span class="mt-1 block text-[0.72rem] text-[#758076]">Search a previous online or walk-in customer, then select the correct phone or email to fill the details automatically.</span>
                                                </div>
                                                <label class="relative block">
                                                    <span class="mb-1.5 block text-[0.72rem] font-semibold text-[#526358]">Search name, phone, or email</span>
                                                    <input
                                                        type="search"
                                                        data-returning-customer-search
                                                        class="w-full rounded-[0.8rem] border border-[#d9e4d2] bg-white px-3 py-2.5 text-[0.82rem] text-[#24372f]"
                                                        placeholder="Start typing a customer..."
                                                        autocomplete="off"
                                                    >
                                                    <div
                                                        data-returning-customer-results
                                                        class="absolute left-0 right-0 top-full z-30 mt-1 hidden max-h-64 overflow-y-auto rounded-[0.8rem] border border-[#d9e4d2] bg-white p-1.5 shadow-[0_18px_45px_rgba(35,58,42,0.16)]"
                                                    >
                                                        @foreach ($returningCustomers as $customer)
                                                            <button
                                                                type="button"
                                                                data-returning-customer-option
                                                                data-name="{{ $customer['name'] }}"
                                                                data-email="{{ $customer['email'] }}"
                                                                data-phone="{{ $customer['phone'] }}"
                                                                class="block w-full rounded-[0.65rem] px-3 py-2.5 text-left transition hover:bg-[#f0f6eb]"
                                                            >
                                                                <strong class="block text-[0.78rem] text-[#24372f]">{{ $customer['name'] }}</strong>
                                                                <span class="mt-0.5 block text-[0.68rem] text-[#7f857c]">{{ $customer['phone'] ?: $customer['email'] }} · {{ $customer['booking_count'] }} previous booking{{ $customer['booking_count'] !== 1 ? 's' : '' }}</span>
                                                            </button>
                                                        @endforeach
                                                        <p data-returning-customer-empty class="hidden px-3 py-3 text-[0.72rem] text-[#7f857c]">No previous customer found. Enter the new customer details below.</p>
                                                    </div>
                                                </label>
                                                <div data-returning-customer-selected class="mt-3 hidden rounded-[0.7rem] bg-[#eaf5e3] px-3 py-2 text-[0.72rem] font-semibold text-[#39623a]"></div>
                                            </div>
                                        @endif
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
                                                <input type="email" name="email" value="{{ old('email') }}" placeholder="maria@email.com" data-guest-email>
                                                @error('email') <small class="field-error">{{ $message }}</small> @enderror
                                            </label>
                                            <label class="field-grid__wide">
                                                <span>Phone number</span>
                                                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+63 912 345 6789" data-guest-phone>
                                                @error('phone') <small class="field-error">{{ $message }}</small> @enderror
                                            </label>
                                        </div>
                                    @endif

                                    <div class="booking-checkout__supporting">
                                    <div class="field-grid field-grid--booking-meta">
                                        <label>
                                            <span>Guests</span>
                                            <input type="number" name="guests" min="1" max="20" value="{{ old('guests', 1) }}">
                                            @error('guests') <small class="field-error">{{ $message }}</small> @enderror
                                        </label>
                                        <label>
                                            <span>{{ $adminMode ? 'Initial payment' : 'Downpayment' }}</span>
                                            <input type="number" name="downpayment_amount" min="{{ $adminMode ? '0' : '0.01' }}" step="0.01" value="{{ old('downpayment_amount') }}" data-downpayment-input>
                                            @error('downpayment_amount') <small class="field-error">{{ $message }}</small> @enderror
                                        </label>
                                        <div class="booking-checkout__method-block">
                                            <span>Payment method</span>
                                            <select name="payment_method" data-payment-method class="hidden">
                                                <option value="">Select a payment method</option>
                                                <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                                <option value="pay_later" @selected(old('payment_method') === 'pay_later')>{{ $adminMode ? 'Pay upon checkout' : 'Pay at HYVE' }}</option>
                                                @if ($adminMode)
                                                    <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                                @endif
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
                                                @if ($adminMode)
                                                    <button type="button" class="booking-checkout__method-card" data-payment-choice="cash">
                                                        <span class="booking-checkout__method-icon">C</span>
                                                        <strong>Cash</strong>
                                                    </button>
                                                @endif
                                                <button type="button" class="booking-checkout__method-card hidden" data-payment-choice="pay_later">
                                                    <span class="booking-checkout__method-icon">P</span>
                                                    <strong>{{ $adminMode ? 'Pay upon checkout' : 'Pay at HYVE' }}</strong>
                                                </button>
                                            </div>
                                            @error('payment_method') <small class="field-error">{{ $message }}</small> @enderror
                                        </div>
                                    </div>

                                    <div class="field-grid" data-payment-proof-wrap>
                                        <label class="field-grid__wide">
                                            <span data-payment-proof-label>{{ $adminMode ? 'Payment proof / receipt photo' : 'Payment Proof' }}</span>
                                            <input type="file" name="payment_proof" accept="image/*">
                                            @error('payment_proof') <small class="field-error">{{ $message }}</small> @enderror
                                            @if ($adminMode)
                                                <small class="field-error" style="color:#7f857c;" data-payment-proof-hint>Not required for cash or deferred payment. GCash and bank payments still require proof.</small>
                                            @endif
                                        </label>
                                    </div>

                                    <label>
                                        <span>{{ $adminMode ? 'Notes / receipt reference' : 'Notes' }}</span>
                                        <textarea name="notes" rows="4" placeholder="{{ $adminMode ? 'For cash, note the OR number or amount received. For pay upon checkout, add any front-desk remarks.' : 'Tell HYVE anything important about your setup or purpose.' }}">{{ old('notes') }}</textarea>
                                        @error('notes') <small class="field-error">{{ $message }}</small> @enderror
                                    </label>
                                    @unless ($adminMode)
                                        <div class="agreement-consent">
                                            <label>
                                                <input type="checkbox" name="rules_agreement" value="1" data-agreement-checkbox @checked(old('rules_agreement'))>
                                                <span>
                                                    I have read and agree to the
                                                    <button type="button" class="agreement-link-button" data-agreement-open="booking-payment-agreement-modal">Rules &amp; Agreement</button>
                                                    of HYVE before submitting this payment.
                                                </span>
                                            </label>
                                            @error('rules_agreement') <small class="field-error">{{ $message }}</small> @enderror
                                        </div>
                                    @endunless
                                    </div>
                                </div>

                                <button type="submit" class="booking-checkout__submit" data-checkout-submit @unless($adminMode) data-agreement-submit disabled @endunless>Confirm &amp; Pay Php 0.00</button>
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
                                    <strong data-checkout-room>{{ $oldBookingMode === 'schedule' ? $scheduleCheckoutRoom : $standardCheckoutRoom }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Date</span>
                                    <strong data-checkout-date>{{ $oldBookingMode === 'schedule' ? $scheduleCheckoutDate : \Illuminate\Support\Carbon::parse($initialDate)->format('F j, Y') }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row @if ($oldBookingMode !== 'monthly') hidden @endif" data-checkout-end-date-row>
                                    <span>End date</span>
                                    <strong data-checkout-end-date>{{ \Illuminate\Support\Carbon::parse($initialEndDate)->format('F j, Y') }}</strong>
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
                                <div class="booking-checkout__summary-row @if ($oldBookingMode !== 'monthly') hidden @endif" data-checkout-monthly-plan-row>
                                    <span>Monthly plan</span>
                                    <strong data-checkout-monthly-plan>{{ $oldMonthlyPlan ?: '--' }}</strong>
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
                                        @if ($paymentSetting?->gcash_qr_path)
                                            <div style="margin-top:0.75rem;">
                                                <img src="{{ route('payment-qr.show', 'gcash') }}" alt="GCash QR code" style="width:min(100%, 220px); border-radius:1rem; border:1px solid #dfe7d8; background:#fff; padding:0.7rem;">
                                            </div>
                                        @endif
                                    </div>
                                    <div data-payment-bank class="hidden">
                                        <p>{{ $paymentSetting?->bank_name ?? 'Sample Bank' }}</p>
                                        <p>{{ $paymentSetting?->bank_account_name ?? 'HYVE Workspace' }}</p>
                                        <p>{{ $paymentSetting?->bank_account_number ?? '012345678901' }}</p>
                                        @if ($paymentSetting?->bank_qr_path)
                                            <div style="margin-top:0.75rem;">
                                                <img src="{{ route('payment-qr.show', 'bank') }}" alt="Bank transfer QR code" style="width:min(100%, 220px); border-radius:1rem; border:1px solid #dfe7d8; background:#fff; padding:0.7rem;">
                                            </div>
                                        @endif
                                    </div>
                                    <div data-payment-cash class="hidden">
                                        <p>Cash payment at front desk</p>
                                        <p>Record the receipt / OR number in notes.</p>
                                    </div>
                                    <div data-payment-pay-later class="hidden">
                                        <p>{{ $adminMode ? 'Pay upon checkout' : 'Pay at HYVE' }}</p>
                                        <p>No initial payment is required. The full booking amount will remain due at HYVE.</p>
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
            @unless ($adminMode)
                @include('partials.payment-agreement-modal', ['modalId' => 'booking-payment-agreement-modal'])
            @endunless

            <div class="member-booking-modal hidden" data-monthly-blocked-modal>
                <div class="member-booking-modal__backdrop" data-monthly-blocked-close></div>
                <div class="member-booking-modal__dialog booking-availability-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="monthly-blocked-dates-title">
                    <button type="button" class="member-booking-modal__close" data-monthly-blocked-close aria-label="Close blocked dates">&times;</button>
                    <p class="member-booking-modal__eyebrow">Room availability</p>
                    <h2 id="monthly-blocked-dates-title" data-monthly-blocked-title>Select a room first</h2>
                    <p class="member-booking-modal__space" data-monthly-blocked-subtitle>Blocked dates for long-stay booking will appear here.</p>

                    <div class="booking-availability-modal__summary">
                        <span>Blocked dates</span>
                        <strong data-monthly-blocked-count>0 dates</strong>
                    </div>

                    <div class="booking-availability-modal__body" data-monthly-blocked-body>
                        <div class="booking-availability-modal__calendar">
                            <div class="booking-availability-modal__calendar-head">
                                <strong data-monthly-blocked-calendar-title>Calendar</strong>
                                <div class="booking-calendar-nav booking-availability-modal__calendar-nav">
                                    <button type="button" data-monthly-blocked-prev aria-label="Previous month">&#8249;</button>
                                    <button type="button" data-monthly-blocked-next aria-label="Next month">&#8250;</button>
                                </div>
                            </div>

                            <div class="booking-calendar-weekdays booking-availability-modal__weekdays">
                                <span>Su</span>
                                <span>Mo</span>
                                <span>Tu</span>
                                <span>We</span>
                                <span>Th</span>
                                <span>Fr</span>
                                <span>Sa</span>
                            </div>

                            <div class="booking-calendar-days booking-availability-modal__days" data-monthly-blocked-calendar-days></div>

                            <div class="booking-calendar-legend booking-availability-modal__legend">
                                <span><i class="is-selected"></i>Selected range</span>
                                <span><i class="is-booked"></i>Blocked</span>
                            </div>
                        </div>

                        <p class="booking-availability-modal__empty" data-monthly-blocked-empty>Select a room first so HYVE can load its blocked dates.</p>
                        <div class="booking-availability-modal__dates hidden" data-monthly-blocked-list></div>
                    </div>
                </div>
            </div>

            <div class="member-booking-modal hidden" data-room-preview-modal>
                <div class="member-booking-modal__backdrop" data-room-preview-close></div>
                <div class="member-booking-modal__dialog booking-room-preview-modal" role="dialog" aria-modal="true" aria-labelledby="room-preview-title">
                    <button type="button" class="member-booking-modal__close" data-room-preview-close aria-label="Close room photos">&times;</button>
                    <p class="member-booking-modal__eyebrow">Room preview</p>
                    <h2 id="room-preview-title" data-room-preview-title>{{ $initialDisplayName }}</h2>
                    <p class="member-booking-modal__space" data-room-preview-space>{{ $initialDisplaySpace }}</p>
                    <p class="booking-room-preview-modal__copy" data-room-preview-description>
                        {{ $initialDisplayRoom?->isSharedTable() ? 'A shared seating area. Your exact table will be assigned automatically based on availability.' : ($initialDisplayRoom?->description ?? 'See the selected room before continuing your booking.') }}
                    </p>

                    <div class="booking-room-preview-modal__hero">
                        <img
                            src="{{ ($spaceGalleryMap[$initialDisplaySpace][0] ?? asset('images/optimized/office.webp')) }}"
                            alt="{{ $initialDisplayName }}"
                            data-room-preview-image
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="booking-room-preview-modal__thumbs" data-room-preview-thumbs></div>
                </div>
            </div>
        </main>
    </div>
@endsection
