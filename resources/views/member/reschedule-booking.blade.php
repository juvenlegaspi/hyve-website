@extends('layouts.app')

@section('content')
    @php
        $member = auth()->user();
        $initials = strtoupper(substr((string) $member->first_name, 0, 1).substr((string) $member->last_name, 0, 1));
        $selectedRoomId = (string) old('hyve_room_id', $selectedRoom?->id ?? '');
        $currentStartDate = old('booking_date', optional($bookingDetail->booking_date)->toDateString() ?? now()->toDateString());
        $currentEndDate = old('booking_end_date', optional($bookingDetail->booking_end_date)->toDateString() ?? $currentStartDate);
        $currentStartTime = old('start_time', substr((string) $bookingDetail->start_time, 0, 5));
        $currentEndTime = old('end_time', substr((string) $bookingDetail->end_time, 0, 5));
        $currentDateLabel = $bookingDetail->booking_date?->format('F j, Y') ?? '--';
        $currentEndDateLabel = ($bookingDetail->booking_end_date ?: $bookingDetail->booking_date)?->format('F j, Y') ?? '--';
        $currentTimeLabel = \Illuminate\Support\Carbon::createFromFormat(strlen((string) $bookingDetail->start_time) === 5 ? 'H:i' : 'H:i:s', (string) $bookingDetail->start_time)->format('g:i A')
            .' - '.
            \Illuminate\Support\Carbon::createFromFormat(strlen((string) $bookingDetail->end_time) === 5 ? 'H:i' : 'H:i:s', (string) $bookingDetail->end_time)->format('g:i A');
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
                        Please review your reschedule details.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="member-bookings-page__intro">
                    <p class="eyebrow">My account</p>
                    <h1>Reschedule Booking</h1>
                    <p>Update your existing room, date, or time. Your current payment stays attached to this same booking.</p>
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
                                <h2>Move this booking</h2>
                                <p>Pick the new schedule below. HYVE will keep the same booking reference and payment history.</p>

                                <form action="{{ route('member.bookings.reschedule.update', $bookingDetail) }}" method="POST" class="member-form">
                                    @csrf
                                    @method('PATCH')

                                    <div class="booking-details-main">
                                        <div class="booking-checkout__supporting">
                                            <label>
                                                <span>Room</span>
                                                <select name="hyve_room_id">
                                                    @foreach ($displayRooms as $room)
                                                        <option value="{{ $room->id }}" @selected($selectedRoomId === (string) $room->id)>
                                                            {{ $room->isSharedTable() ? 'Common Area' : $room->room_name }} - {{ $room->mappedSpaceLabel() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('hyve_room_id') <small class="field-error">{{ $message }}</small> @enderror
                                            </label>

                                            @if ($isLongStay)
                                                <div class="field-grid">
                                                    <label>
                                                        <span>Start date</span>
                                                        <input type="date" name="booking_date" min="{{ now()->toDateString() }}" value="{{ $currentStartDate }}">
                                                        @error('booking_date') <small class="field-error">{{ $message }}</small> @enderror
                                                    </label>
                                                    <label>
                                                        <span>End date</span>
                                                        <input type="date" name="booking_end_date" min="{{ $currentStartDate }}" value="{{ $currentEndDate }}">
                                                        @error('booking_end_date') <small class="field-error">{{ $message }}</small> @enderror
                                                    </label>
                                                </div>
                                            @else
                                                <div class="field-grid">
                                                    <label class="field-grid__wide">
                                                        <span>Booking date</span>
                                                        <input type="date" name="booking_date" min="{{ now()->toDateString() }}" value="{{ $currentStartDate }}">
                                                        @error('booking_date') <small class="field-error">{{ $message }}</small> @enderror
                                                    </label>
                                                    <label>
                                                        <span>Start time</span>
                                                        <input type="time" name="start_time" step="1800" value="{{ $currentStartTime }}">
                                                        @error('start_time') <small class="field-error">{{ $message }}</small> @enderror
                                                    </label>
                                                    <label>
                                                        <span>End time</span>
                                                        <input type="time" name="end_time" step="1800" value="{{ $currentEndTime }}">
                                                        @error('end_time') <small class="field-error">{{ $message }}</small> @enderror
                                                    </label>
                                                </div>
                                            @endif

                                            <div class="booking-balance-banner">
                                                <span>Payment reminder</span>
                                                <strong>No new downpayment needed</strong>
                                            </div>

                                            <p style="margin:0; color:#6f786d;">
                                                Existing paid amount: <strong>Php {{ number_format((float) $bookingHeader->downpayment_amount, 2) }}</strong><br>
                                                If the updated schedule changes the price, HYVE will only recompute the total and remaining balance under this same booking.
                                            </p>
                                        </div>
                                    </div>

                                    <button type="submit" class="booking-checkout__submit">Save reschedule</button>
                                </form>
                            </div>
                        </div>

                        <aside class="booking-checkout__side">
                            <div class="booking-checkout__panel booking-checkout__panel--summary">
                                <h2>Current booking</h2>

                                <div class="booking-checkout__summary-row">
                                    <span>Reference</span>
                                    <strong>{{ $bookingHeader->reference_no }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Current room</span>
                                    <strong>{{ $bookingDetail->hyveRoom?->isSharedTable() ? 'Common Area' : ($bookingDetail->hyveRoom?->room_name ?? 'Space booking') }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Current date</span>
                                    <strong>{{ $isLongStay ? $currentDateLabel.' - '.$currentEndDateLabel : $currentDateLabel }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Current schedule</span>
                                    <strong>{{ $isLongStay ? ucfirst((string) $bookingDetail->charge_period).' stay' : $currentTimeLabel }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Current line total</span>
                                    <strong>Php {{ number_format((float) $bookingDetail->subtotal, 2) }}</strong>
                                </div>
                                <div class="booking-checkout__summary-row">
                                    <span>Paid so far</span>
                                    <strong>Php {{ number_format((float) $bookingHeader->downpayment_amount, 2) }}</strong>
                                </div>
                                <div class="booking-checkout__summary-total">
                                    <span>Current remaining balance</span>
                                    <strong>Php {{ number_format((float) $bookingHeader->balance_amount, 2) }}</strong>
                                </div>

                                @if ($selectedQuote)
                                    <div class="booking-checkout__note" style="margin-top:1rem;">
                                        <strong>Estimated updated total</strong>
                                        <p style="margin-top:0.5rem;">Php {{ number_format((float) ($selectedQuote['total_amount'] ?? 0), 2) }}</p>
                                        <small>
                                            {{ $selectedQuote['rate_name'] ?? 'Updated rate' }}
                                            @if (!empty($selectedQuote['monthly_plan_label']))
                                                <br>{{ $selectedQuote['monthly_plan_label'] }}
                                            @endif
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </main>
    </div>
@endsection
