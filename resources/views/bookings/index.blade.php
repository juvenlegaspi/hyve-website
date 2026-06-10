@extends('layouts.app')

@section('content')
    @php
        $oldRoom = $hyveRooms->firstWhere('id', (int) old('hyve_room_id'));
        $featuredRooms = $hyveRooms->filter(fn ($room) => in_array($room->room_name, ['Conference Room', 'Room 7'], true))->values();
        $privateRooms = $hyveRooms->filter(fn ($room) => in_array($room->room_name, ['Room 6', 'Room 5', 'Room 4', 'Room 3', 'Room 2', 'Room 1'], true))->values();
        $tableGroups = $hyveRooms
            ->filter(fn ($room) => str_starts_with($room->room_name, 'Table '))
            ->groupBy(fn ($room) => explode('-', $room->room_name)[0]);
    @endphp

    <div class="relative overflow-x-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[24rem] bg-[radial-gradient(circle_at_top,_rgba(196,156,91,0.22),_transparent_55%)]"></div>

        <header class="relative px-6 pt-6 md:px-10">
            <div class="mx-auto flex max-w-7xl items-center justify-between rounded-full border border-white/60 bg-white/80 px-6 py-4 shadow-lg shadow-black/5 backdrop-blur">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/logohyve.jpg') }}" alt="HYVE logo" class="h-11 w-11 rounded-full border border-white/60 bg-white/90 object-contain p-1">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-[#163129]">HYVE</p>
                        <p class="text-[11px] uppercase tracking-[0.22em] text-[#74675a]">Workspaces and Meetings</p>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="hidden rounded-full border border-[#163129]/12 px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-[#163129] transition hover:bg-white md:inline-flex">
                        Back to Home
                    </a>
                    @auth
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-full bg-[#163129] px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-[#10241f]">
                                Log Out
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full bg-[#163129] px-5 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-[#10241f]">
                            Member Log In
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="relative px-6 pb-20 pt-10 md:px-10">
            <div class="mx-auto max-w-7xl space-y-8">
                <section class="rounded-[2rem] border border-white/60 bg-white/85 p-8 shadow-[0_30px_100px_rgba(18,24,21,0.08)] backdrop-blur">
                    <div>
                        @auth
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#8c692c]">Member Booking Page</p>
                            <h1 class="mt-4 font-display text-4xl tracking-[-0.04em] text-[#18130f]">
                                Welcome, {{ $user->first_name }}.
                            </h1>
                            <p class="mt-4 max-w-2xl text-base leading-7 text-[#5f5449]">
                                Your monthly-member details are already linked to your request. Choose the space, date, and schedule that best fits the way you plan to work or meet.
                            </p>

                            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                                <div class="rounded-[1.5rem] bg-[#f7f2eb] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8c692c]">Username</p>
                                    <p class="mt-2 text-base text-[#163129]">{{ $user->username }}</p>
                                </div>
                                <div class="rounded-[1.5rem] bg-[#f7f2eb] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8c692c]">Email</p>
                                    <p class="mt-2 break-all text-base text-[#163129]">{{ $user->email }}</p>
                                </div>
                                <div class="rounded-[1.5rem] bg-[#f7f2eb] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8c692c]">Phone</p>
                                    <p class="mt-2 text-base text-[#163129]">{{ $user->phone }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#8c692c]">Direct Booking</p>
                            <h1 class="mt-4 font-display text-4xl tracking-[-0.04em] text-[#18130f]">
                                Reserve a HYVE space without creating an account.
                            </h1>
                            <p class="mt-4 max-w-3xl text-base leading-7 text-[#5f5449]">
                                For day use, meetings, and one-time reservations, you can book directly by sharing your contact details below. Login and registration are only for monthly members who want an account-based experience with easier monitoring and repeat bookings.
                            </p>

                            <div class="mt-6 flex flex-col gap-4 sm:flex-row">
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full bg-[#163129] px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-[#10241f]">
                                    Become a Monthly Member
                                </a>
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-[#163129]/12 px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-[#163129] transition hover:bg-white">
                                    Member Log In
                                </a>
                            </div>
                        @endauth
                    </div>
                </section>

                <section class="space-y-8">
                    @if (session('booking_success'))
                        <div class="rounded-[1.75rem] border border-emerald-400/30 bg-emerald-500/10 p-5 text-sm leading-7 text-emerald-700">
                            {{ session('booking_success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-[1.75rem] border border-red-400/30 bg-red-500/10 p-5 text-sm leading-7 text-red-700">
                            <p class="font-semibold uppercase tracking-[0.18em] text-red-800">Your last booking submission needs a quick fix.</p>
                            <p class="mt-2">Open the booking popup again and review the highlighted details.</p>
                        </div>
                    @endif

                    <div id="live-room-layout" class="rounded-[2rem] border border-[#163129]/10 bg-white p-8 shadow-xl shadow-black/5" data-room-layout data-layout-url="{{ $bookingConfig['layout_url'] }}">
                            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#8c692c]">Live Room Layout</p>
                                    <h2 class="mt-4 font-display text-3xl tracking-[-0.04em] text-[#18130f]">Check exact room availability.</h2>
                                    <p class="mt-3 max-w-2xl leading-7 text-[#5f5449]">
                                        Pick a date, review the legend, then click any room or table to open a schedule popup for that day.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <label class="block">
                                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Filter Date</span>
                                        <input type="date" value="{{ old('booking_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" data-layout-date class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b] md:min-w-[16rem]">
                                    </label>

                                    <div class="flex items-end">
                                        <button type="button" class="inline-flex items-center justify-center rounded-full bg-[#163129] px-6 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-white transition hover:-translate-y-0.5 hover:bg-[#10241f]" data-booking-open>
                                            Book Now
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                    Available
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-amber-400/25 bg-amber-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                    Booked
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-red-400/25 bg-red-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-red-700">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                    Occupied
                                </span>
                            </div>

                            <div class="mt-8 space-y-5">
                                <div class="space-y-3">
                                    <div class="grid gap-3 md:grid-cols-[1.08fr_0.92fr]">
                                        @foreach ($featuredRooms as $room)
                                            <button type="button" data-layout-room data-room-id="{{ $room->id }}" class="{{ $room->room_name === 'Conference Room' ? 'min-h-[9rem]' : 'min-h-[8rem]' }} rounded-[1.5rem] border border-[#163129]/12 bg-[#f8f3ec] p-4 text-left transition hover:-translate-y-0.5 hover:border-[#c49c5b]">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">{{ $room->mappedSpaceLabel() }}</p>
                                                <p class="mt-2 text-[1.85rem] font-semibold leading-[1.05] text-[#163129]">{{ $room->room_name }}</p>
                                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-[#5f5449]">{{ $room->description }}</p>
                                                <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#163129]/70" data-layout-room-state>View availability</p>
                                            </button>
                                        @endforeach
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach ($privateRooms as $room)
                                            <button type="button" data-layout-room data-room-id="{{ $room->id }}" class="rounded-[1.15rem] border border-[#163129]/12 bg-[#f8f3ec] px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-[#c49c5b]">
                                                <div class="flex items-center justify-between gap-4">
                                                    <div>
                                                        <p class="text-base font-semibold text-[#163129]">{{ $room->room_name }}</p>
                                                        <p class="mt-1 text-xs leading-5 text-[#5f5449]">{{ $room->mappedSpaceLabel() }}</p>
                                                    </div>
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#163129]/70" data-layout-room-state>View</p>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="rounded-[1.5rem] border border-[#163129]/10 bg-[#f8f3ec] p-4">
                                    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Shared Table Zone</p>
                                            <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[#163129]">Browse Every Shared Table At A Glance</h3>
                                        </div>
                                        <p class="max-w-xl text-sm leading-6 text-[#5f5449]">
                                            Each table card stays compact but readable so guests can check available seats without labels spilling outside the layout.
                                        </p>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                    @foreach ($tableGroups as $tableName => $tableRooms)
                                        <div class="min-w-0 rounded-[1.35rem] border border-[#163129]/10 bg-[#fcfaf7] p-4">
                                            <div class="mb-3 flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Shared Table</p>
                                                    <h3 class="text-[1.85rem] font-semibold leading-none tracking-[-0.03em] text-[#163129]">{{ $tableName }}</h3>
                                                </div>
                                                <span class="shrink-0 text-[11px] uppercase tracking-[0.18em] text-[#5f5449]">{{ $tableRooms->count() }} seats</span>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2.5 {{ $tableRooms->count() > 4 ? 'xl:grid-cols-3' : '' }}">
                                                @foreach ($tableRooms->sortBy('room_name')->values() as $room)
                                                    <button type="button" data-layout-room data-room-id="{{ $room->id }}" class="min-w-0 rounded-[1rem] border border-[#163129]/12 bg-white px-3 py-3 text-center transition hover:-translate-y-0.5 hover:border-[#c49c5b]">
                                                        <p class="text-base font-semibold leading-none text-[#163129]">{{ Str::after($room->room_name, $tableName.'-') }}</p>
                                                        <p class="mt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#163129]/70" data-layout-room-state>Open</p>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 rounded-[1.5rem] border border-[#163129]/10 bg-[#f8f3ec] p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8c692c]">Layout Viewing</p>
                                <h3 class="mt-2 text-xl font-semibold text-[#163129]">Click any room to open its schedule popup.</h3>
                                <p class="mt-2 max-w-2xl text-sm leading-7 text-[#5f5449]">
                                    The live layout is for viewing availability only. Your actual booking choice stays inside the booking popup so the page remains clean and focused.
                                </p>
                            </div>
                    </div>
                </section>
            </div>
        </main>

        <div class="fixed inset-0 z-50 hidden items-center justify-center bg-[#18130f]/65 px-4 py-6 backdrop-blur-md" data-booking-modal aria-hidden="true" data-open-on-load="{{ $errors->any() ? 'true' : 'false' }}">
            <div class="flex h-[calc(100vh-3rem)] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-white text-[#18130f] shadow-[0_35px_120px_rgba(12,18,16,0.32)]">
                <div class="shrink-0 border-b border-[#163129]/8 bg-[linear-gradient(135deg,rgba(196,156,91,0.14),rgba(255,255,255,0.94))] px-6 py-6 md:px-8">
                    <div class="flex items-start justify-between gap-4">
                        <div class="max-w-3xl">
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#8c692c]">Make a Booking</p>
                            <h2 class="mt-4 font-display text-3xl tracking-[-0.04em] text-[#18130f]">Submit your workspace request.</h2>
                            <p class="mt-3 text-sm leading-7 text-[#5f5449]">
                                Finalize your room, time range, payment method, and proof of payment here. The layout stays outside so the booking flow feels cleaner and easier to follow.
                            </p>
                        </div>

                        <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#163129]/10 bg-white/80 text-2xl leading-none text-[#163129] transition hover:bg-white" data-booking-close aria-label="Close booking form">
                            x
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6 md:px-8">
                    @if ($errors->any())
                        <div class="rounded-[1.75rem] border border-red-400/30 bg-red-500/10 p-5 text-sm leading-7 text-red-700">
                            <p class="font-semibold uppercase tracking-[0.18em] text-red-800">Please check your booking details.</p>
                            <ul class="mt-3 space-y-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('bookings.store') }}" method="POST" enctype="multipart/form-data" class="mt-{{ $errors->any() ? '6' : '0' }} grid gap-4 sm:grid-cols-2" data-booking-form data-availability-url="{{ $bookingConfig['availability_url'] }}" data-unavailable-dates-url="{{ $bookingConfig['unavailable_dates_url'] }}" data-quote-url="{{ $bookingConfig['quote_url'] }}" data-slot-interval="{{ $bookingConfig['slot_interval_minutes'] }}" data-minimum-duration="{{ $bookingConfig['minimum_duration_minutes'] }}" data-unavailable-dates-horizon="{{ $bookingConfig['unavailable_dates_horizon'] }}">
                            @csrf
                            @guest
                                <label class="block sm:col-span-2">
                                    <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Full Name</span>
                                    <input type="text" name="full_name" value="{{ old('full_name') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Juan Dela Cruz">
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Email Address</span>
                                    <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="you@example.com">
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Phone Number</span>
                                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="+63 9xx xxx xxxx">
                                </label>
                            @endguest

                            <label class="block sm:col-span-2">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Room Or Seat</span>
                                <select name="hyve_room_id" data-room-select class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]">
                                    <option value="">Select a room or table seat</option>
                                    @foreach ($hyveRooms as $room)
                                        <option value="{{ $room->id }}" @selected((string) old('hyve_room_id') === (string) $room->id)>
                                            {{ $room->room_name }} - {{ $room->mappedSpaceLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs leading-6 text-[#74675a]" data-selected-room-meta>
                                    {{ $oldRoom ? $oldRoom->description.' | '.$oldRoom->mappedSpaceLabel() : 'Choose the exact room, conference room, or shared table seat that you want to book.' }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Booking Date</span>
                                <input type="date" name="booking_date" value="{{ old('booking_date') }}" min="{{ now()->toDateString() }}" data-booking-date class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]">
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Guests</span>
                                <input type="number" name="guests" value="{{ old('guests', 1) }}" min="1" max="20" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]">
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Start Time</span>
                                <select name="start_time" data-start-time-select data-old-value="{{ old('start_time') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" disabled>
                                    <option value="">{{ old('start_time') ? 'Reload available start times' : 'Select a room and date first' }}</option>
                                </select>
                                <p class="mt-2 text-xs leading-6 text-[#74675a]">
                                    Pick the exact time you want your booking to begin.
                                </p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">End Time</span>
                                <select name="end_time" data-end-time-select data-old-value="{{ old('end_time') }}" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" disabled>
                                    <option value="">{{ old('end_time') ? 'Reload available end times' : 'Select a start time first' }}</option>
                                </select>
                                <p class="mt-2 text-xs leading-6 text-[#74675a]">
                                    End time options only show the ranges that are still open for your chosen start.
                                </p>
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Booking Duration</span>
                                <input type="text" value="{{ old('start_time') && old('end_time') ? old('start_time').' to '.old('end_time') : 'Choose a start and end time to see the total duration.' }}" data-duration-display class="w-full rounded-2xl border border-[#163129]/12 bg-[#f7f2eb] px-4 py-3 text-[#5f5449] outline-none" readonly>
                            </label>

                            <div class="sm:col-span-2 rounded-[1.25rem] border border-[#163129]/8 bg-white px-4 py-4 text-sm text-[#5f5449]">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Unavailable Dates</p>
                                <p class="mt-2 leading-7" data-unavailable-dates-message>
                                    Select a room first to load fully booked dates for the next 14 days.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2" data-unavailable-dates-list></div>
                            </div>

                            <div class="sm:col-span-2 rounded-[1.25rem] border border-[#163129]/8 bg-[#f7f2eb] px-4 py-4 text-sm leading-7 text-[#5f5449]" data-availability-message>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Booking Availability</p>
                                <div class="mt-2 flex items-start gap-3">
                                    <span class="mt-1 hidden h-4 w-4 animate-spin rounded-full border-2 border-[#c49c5b]/35 border-t-[#c49c5b]" data-availability-spinner aria-hidden="true"></span>
                                    <p data-availability-message-body>
                                        Select a room and date first. Available start and end times will appear here.
                                    </p>
                                </div>
                            </div>

                            <div class="sm:col-span-2 overflow-hidden rounded-[1.75rem] border border-[#163129]/10 bg-white shadow-sm">
                                <div class="border-b border-[#163129]/8 bg-[linear-gradient(135deg,rgba(196,156,91,0.12),rgba(255,255,255,0.85))] px-5 py-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8c692c]">Payment Summary</p>
                                    <h3 class="mt-2 text-2xl font-semibold tracking-[-0.03em] text-[#163129]">Clear, friendly breakdown before you submit.</h3>
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#5f5449]" data-quote-meta>
                                        Choose a room, date, start time, and end time first to load your live rate summary.
                                    </p>
                                </div>

                                <div class="grid gap-0 xl:grid-cols-[1.02fr_0.98fr]">
                                    <div class="border-b border-[#163129]/8 bg-[#f8f3ec] p-5 xl:border-b-0 xl:border-r">
                                        <div class="grid gap-3 lg:grid-cols-3 xl:grid-cols-1">
                                            <div class="rounded-[1.2rem] border border-white/70 bg-white px-5 py-5 shadow-sm">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Total Amount</p>
                                                <p class="mt-3 text-3xl font-semibold tracking-[-0.03em] text-[#163129]" data-quote-total>Php 0.00</p>
                                                <p class="mt-2 text-sm leading-6 text-[#74675a]">Live total based on your selected room, date, and final time range.</p>
                                            </div>
                                            <div class="rounded-[1.2rem] border border-white/70 bg-white px-5 py-5 shadow-sm">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Minimum Downpayment</p>
                                                <p class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#163129]" data-quote-minimum-downpayment>Php 500.00</p>
                                                <p class="mt-2 text-sm leading-6 text-[#74675a]">Up to Php 1,000 total: 50% minimum. Above Php 1,000 total: minimum Php 500.</p>
                                            </div>
                                            <div class="rounded-[1.2rem] border border-white/70 bg-white px-5 py-5 shadow-sm">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Remaining Balance</p>
                                                <p class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#163129]" data-quote-balance>Php 0.00</p>
                                                <p class="mt-2 text-sm leading-6 text-[#74675a]">Automatically updates as soon as you enter your preferred downpayment.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-5 p-5">
                                        <div class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                                            <label class="block">
                                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Your Downpayment</span>
                                                <input type="number" name="downpayment_amount" min="0.01" step="0.01" value="{{ old('downpayment_amount') }}" data-downpayment-input class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Enter the required minimum downpayment">
                                                <p class="mt-2 text-xs leading-6 text-[#74675a]">
                                                    Minimum payment depends on your total: <span class="font-semibold text-[#163129]">50% if Php 1,000 and below</span>, or <span class="font-semibold text-[#163129]">Php 500</span> once the total goes above Php 1,000.
                                                </p>
                                            </label>

                                            <div class="rounded-[1.35rem] border border-[#163129]/8 bg-[#f8f3ec] p-4 text-sm text-[#5f5449]">
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Booking Payment Flow</p>
                                                <ol class="mt-3 space-y-2 leading-6">
                                                    <li><span class="font-semibold text-[#163129]">1.</span> Finalize room, date, and time.</li>
                                                    <li><span class="font-semibold text-[#163129]">2.</span> Enter your chosen downpayment amount.</li>
                                                    <li><span class="font-semibold text-[#163129]">3.</span> Send payment, then upload proof for checking.</li>
                                                </ol>
                                            </div>
                                        </div>

                                        <div class="grid gap-4 xl:grid-cols-[0.92fr_1.08fr]">
                                            <label class="block">
                                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Payment Method</span>
                                                <select name="payment_method" data-payment-method class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]">
                                                    <option value="">Select where you will send the downpayment</option>
                                                    <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                                                    <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                                </select>
                                                <p class="mt-2 text-xs leading-6 text-[#74675a]">Choose the channel first so the correct account details appear below.</p>
                                            </label>

                                            <label class="block rounded-[1.35rem] border border-[#163129]/8 bg-white p-4">
                                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Payment Proof</span>
                                                <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-2xl border border-[#163129]/12 bg-[#f8f3ec] px-4 py-3 text-sm text-[#18130f] outline-none transition file:mr-3 file:rounded-full file:border-0 file:bg-[#163129] file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-[0.18em] file:text-white hover:file:bg-[#10241f] focus:border-[#c49c5b]">
                                                <p class="mt-3 text-xs leading-6 text-[#74675a]">
                                                    Upload a clear screenshot or photo after sending your selected downpayment.
                                                </p>
                                            </label>
                                        </div>

                                        <div class="rounded-[1.35rem] border border-[#163129]/8 bg-[#f8f3ec] p-4 text-sm text-[#5f5449]" data-payment-destination>
                                            <div class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr] xl:items-start">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Payment Destination</p>
                                                    <div class="mt-3 hidden space-y-2" data-payment-gcash>
                                                        <p><span class="font-semibold text-[#163129]">Account Name:</span> {{ $paymentSetting?->gcash_account_name ?? 'HYVE Workspace' }}</p>
                                                        <p><span class="font-semibold text-[#163129]">GCash Number:</span> {{ $paymentSetting?->gcash_number ?? '0917 123 4567' }}</p>
                                                    </div>
                                                    <div class="mt-3 hidden space-y-2" data-payment-bank>
                                                        <p><span class="font-semibold text-[#163129]">Bank:</span> {{ $paymentSetting?->bank_name ?? 'BDO Sample Account' }}</p>
                                                        <p><span class="font-semibold text-[#163129]">Account Name:</span> {{ $paymentSetting?->bank_account_name ?? 'HYVE Workspace Inc.' }}</p>
                                                        <p><span class="font-semibold text-[#163129]">Account Number:</span> {{ $paymentSetting?->bank_account_number ?? '012345678901' }}</p>
                                                    </div>
                                                    <p class="mt-3 leading-6" data-payment-instructions>
                                                        {{ $paymentSetting?->instructions ?? 'Send the required downpayment first, then upload a clear screenshot of the payment confirmation.' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-[1.2rem] border border-white/70 bg-white px-4 py-4 shadow-sm">
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#8c692c]">Submission Reminder</p>
                                                    <p class="mt-3 leading-6 text-[#5f5449]">
                                                        The booking request stays pending until the payment proof is reviewed. Make sure the screenshot clearly shows the amount, receiver, and reference details.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <label class="block sm:col-span-2">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-[#163129]">Notes</span>
                                <textarea name="notes" rows="4" class="w-full rounded-2xl border border-[#163129]/12 bg-white px-4 py-3 text-[#18130f] outline-none transition focus:border-[#c49c5b]" placeholder="Tell HYVE about your meeting goals, setup needs, or preferred room arrangement.">{{ old('notes') }}</textarea>
                            </label>

                            <div class="sm:col-span-2">
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#163129] px-7 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:-translate-y-0.5 hover:bg-[#10241f]">
                                    {{ $user ? 'Submit Member Booking Request' : 'Submit Booking Request' }}
                                </button>
                            </div>
                        </form>
                </div>
            </div>
        </div>

        <div class="fixed inset-0 z-50 hidden items-center justify-center bg-[#18130f]/65 px-4 py-6 backdrop-blur-md" data-room-modal aria-hidden="true">
            <div class="flex h-[calc(100vh-3rem)] w-full max-w-4xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-[#163129] text-white shadow-[0_35px_120px_rgba(12,18,16,0.42)]">
                    <div class="relative shrink-0 overflow-hidden border-b border-white/8 bg-[radial-gradient(circle_at_top_left,_rgba(196,156,91,0.25),_transparent_45%),linear-gradient(135deg,_rgba(255,255,255,0.04),_rgba(255,255,255,0))] px-6 py-6 md:px-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="max-w-2xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#c49c5b]">Room Schedule</p>
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <h3 class="text-3xl font-semibold tracking-[-0.03em]" data-room-detail-name>No room selected yet</h3>
                                    <span class="rounded-full border border-white/12 bg-white/8 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-white/78" data-room-detail-type>
                                        Layout View
                                    </span>
                                </div>
                                <p class="mt-3 max-w-xl text-sm leading-7 text-white/72" data-room-detail-meta>
                                    Click any room or table on the layout to inspect the available and occupied time slots for the filtered date.
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/82" data-room-detail-status>
                                    Waiting for selection
                                </div>
                                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/12 bg-white/8 text-2xl leading-none text-white transition hover:bg-white/14" data-room-modal-close aria-label="Close room schedule">
                                    x
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-3">
                            <div class="rounded-[1.4rem] border border-white/10 bg-white/7 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Next Available</p>
                                <p class="mt-3 text-lg font-semibold text-white" data-room-detail-next-slot>
                                    No slot loaded yet
                                </p>
                                <p class="mt-1 text-sm text-white/62">
                                    Earliest available schedule for the selected date.
                                </p>
                            </div>
                            <div class="rounded-[1.4rem] border border-white/10 bg-white/7 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Available Slots</p>
                                <p class="mt-3 text-lg font-semibold text-white" data-room-detail-available-count>
                                    0 open
                                </p>
                                <p class="mt-1 text-sm text-white/62">
                                    Remaining schedules still open for booking.
                                </p>
                            </div>
                            <div class="rounded-[1.4rem] border border-white/10 bg-white/7 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Booked Slots</p>
                                <p class="mt-3 text-lg font-semibold text-white" data-room-detail-booked-count>
                                    0 reserved
                                </p>
                                <p class="mt-1 text-sm text-white/62">
                                    Schedules already booked or occupied.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                        <div class="grid gap-4 bg-[#163129] px-6 py-6 md:grid-cols-2 md:px-8">
                            <div class="rounded-[1.5rem] border border-white/10 bg-white/7 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Available Slots</p>
                                <div class="mt-4 flex flex-wrap gap-2" data-room-detail-available>
                                    <span class="rounded-full border border-white/12 px-3 py-2 text-xs uppercase tracking-[0.16em] text-white/72">No room selected</span>
                                </div>
                            </div>
                            <div class="rounded-[1.5rem] border border-white/10 bg-white/7 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Unavailable Slots</p>
                                <div class="mt-4 flex flex-wrap gap-2" data-room-detail-booked>
                                    <span class="rounded-full border border-white/12 px-3 py-2 text-xs uppercase tracking-[0.16em] text-white/72">No room selected</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-white/8 bg-[#122720] px-6 py-6 md:px-8">
                            <div class="rounded-[1.5rem] border border-white/10 bg-white/7 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c49c5b]">Booking Details</p>
                                <div class="mt-4 space-y-3" data-room-detail-timeline>
                                    <div class="rounded-[1rem] border border-white/10 px-4 py-3 text-sm text-white/72">
                                        Click a room to view its schedule details.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>
@endsection
