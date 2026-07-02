@extends('layouts.admin')

@section('content')
    @php
        use App\Models\HyveScheduleOverride;
        use Illuminate\Support\Carbon;

        $selectedMode = $selectedOverride?->mode ?? HyveScheduleOverride::MODE_DEFAULT;
        $selectedReason = old('reason', $selectedOverride?->reason ?? '');
        $selectedOpeningTime = old('opening_time', $selectedOverride?->opening_time ?? $defaultOpeningTime);
        $selectedClosingTime = old('closing_time', $selectedOverride?->closing_time ?? $defaultClosingTime);
        $selectedRoomId = old('hyve_room_id', $selectedRoom->id);

        $monthStart = $monthSeed->copy()->startOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $monthSeed->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $previousMonth = $monthSeed->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthSeed->copy()->addMonth()->format('Y-m');

        $openingOptions = collect(range(0, 47))
            ->map(fn (int $slot) => sprintf('%02d:%02d', intdiv($slot, 2), ($slot % 2) * 30));

        $closingOptions = collect(range(1, 48))
            ->map(function (int $slot): string {
                if ($slot === 48) {
                    return '24:00';
                }

                return sprintf('%02d:%02d', intdiv($slot, 2), ($slot % 2) * 30);
            });
    @endphp

    <style>
        .room-schedule-shell { display: grid; gap: 1.4rem; }
        .room-schedule-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .room-schedule-title { margin: 0; color: #132320; font-size: 1.45rem; font-weight: 700; letter-spacing: -0.04em; }
        .room-schedule-copy { margin: 0.35rem 0 0; color: #8e877b; font-size: 0.8rem; line-height: 1.6; }
        .room-schedule-reset { display: inline-flex; align-items: center; justify-content: center; min-height: 2.8rem; padding: 0.78rem 1.2rem; border: 1px solid #dde4d8; border-radius: 0.95rem; background: #fff; color: #536158; font-size: 0.8rem; font-weight: 600; transition: background 160ms ease, border-color 160ms ease, color 160ms ease; }
        .room-schedule-reset:hover { background: #f8faf6; color: #264134; }
        .room-schedule-grid { display: grid; grid-template-columns: minmax(18rem, 27rem) minmax(0, 1fr); gap: 1rem; }
        .room-schedule-card { border: 1px solid #dde5d9; border-radius: 1.35rem; background: rgba(255, 255, 255, 0.92); box-shadow: 0 18px 46px rgba(22, 33, 28, 0.06); }
        .room-schedule-card--calendar { padding: 1.25rem 1.2rem 1.05rem; }
        .room-schedule-card--details { padding: 1.25rem 1.35rem 1.3rem; }
        .room-schedule-calendar-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.95rem; }
        .room-schedule-calendar-title { margin: 0; color: #182822; font-size: 0.95rem; font-weight: 700; letter-spacing: -0.03em; }
        .room-schedule-calendar-nav { display: flex; gap: 0.45rem; }
        .room-schedule-calendar-nav a { display: inline-flex; align-items: center; justify-content: center; width: 2.15rem; height: 2.15rem; border: 1px solid #dde4d9; border-radius: 999px; background: #fff; color: #5b665f; font-size: 1rem; line-height: 1; transition: background 160ms ease, color 160ms ease, border-color 160ms ease; }
        .room-schedule-calendar-nav a:hover { background: #f7faf4; color: #20372d; border-color: #c9d7c4; }
        .room-schedule-weekdays, .room-schedule-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .room-schedule-weekdays { margin-bottom: 0.45rem; }
        .room-schedule-weekdays span { color: #b5aea2; font-size: 0.72rem; font-weight: 700; text-align: center; }
        .room-schedule-day { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.8rem; height: 2.8rem; margin: 0.18rem auto; border: 1px solid transparent; border-radius: 999px; background: transparent; color: #485650; font-size: 0.9rem; font-weight: 500; transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease, opacity 160ms ease; }
        .room-schedule-day:hover { transform: translateY(-1px); }
        .room-schedule-day.is-default { background: #eef6df; color: #314634; }
        .room-schedule-day.is-custom { background: #ecf3ff; color: #42608a; }
        .room-schedule-day.is-closed { background: #fff1f1; color: #bf5959; }
        .room-schedule-day.is-selected { border-color: #7aad54; box-shadow: 0 0 0 3px rgba(122, 173, 84, 0.14); font-weight: 700; }
        .room-schedule-day.is-outside { opacity: 0.24; }
        .room-schedule-legend { display: grid; gap: 0.55rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #edf1ea; }
        .room-schedule-legend span { display: inline-flex; align-items: center; gap: 0.6rem; color: #617067; font-size: 0.8rem; }
        .room-schedule-legend i { width: 0.82rem; height: 0.82rem; border-radius: 0.28rem; border: 1px solid transparent; }
        .room-schedule-legend .is-default { background: #eef6df; border-color: #9fc16c; }
        .room-schedule-legend .is-custom { background: #ecf3ff; border-color: #93b7ef; }
        .room-schedule-legend .is-closed { background: #fff1f1; border-color: #ee9b9b; }
        .room-schedule-details-title { margin: 0; color: #16261f; font-size: 1rem; font-weight: 700; letter-spacing: -0.03em; }
        .room-schedule-details-subtitle { margin: 0.2rem 0 1.15rem; color: #a29d91; font-size: 0.77rem; line-height: 1.55; }
        .room-schedule-label { display: block; margin-bottom: 0.55rem; color: #b2ac9f; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .room-schedule-status-row { display: flex; flex-wrap: wrap; gap: 0.65rem; margin-bottom: 1.1rem; }
        .room-schedule-status-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 2.45rem; padding: 0.7rem 1.15rem; border: 1px solid #dde4da; border-radius: 0.9rem; background: #fff; color: #54635b; font-size: 0.81rem; font-weight: 600; transition: background 160ms ease, border-color 160ms ease, color 160ms ease; }
        .room-schedule-status-pill.is-active[data-mode="default"] { border-color: #9cc06e; background: #eef6df; color: #335037; }
        .room-schedule-status-pill.is-active[data-mode="custom"] { border-color: #96b8ee; background: #ecf3ff; color: #43638f; }
        .room-schedule-status-pill.is-active[data-mode="closed"] { border-color: #ed9a9a; background: #fff1f1; color: #be5252; }
        .room-schedule-radio { position: absolute; opacity: 0; pointer-events: none; }
        .room-schedule-field { display: grid; gap: 0.42rem; margin-bottom: 0.95rem; }
        .room-schedule-input, .room-schedule-select { width: 100%; min-height: 2.7rem; padding: 0.78rem 0.92rem; border: 1px solid #dde4da; border-radius: 0.9rem; background: #fbfbf8; color: #203026; font-size: 0.82rem; outline: none; transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease; }
        .room-schedule-input:focus, .room-schedule-select:focus { border-color: rgba(68, 121, 59, 0.42); background: #fff; box-shadow: 0 0 0 3px rgba(68, 121, 59, 0.08); }
        .room-schedule-hours-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; }
        .room-schedule-hours-card { margin-bottom: 1rem; padding: 0.95rem; border: 1px solid #edf1ea; border-radius: 1rem; background: #fbfcf8; }
        .room-schedule-hours-card.hidden { display: none; }
        .room-schedule-note { color: #8d978d; font-size: 0.76rem; line-height: 1.6; }
        .room-schedule-room-picker { max-width: 20rem; }
        .room-schedule-actions { display: flex; flex-wrap: wrap; gap: 0.7rem; margin-top: 1.15rem; }
        .room-schedule-button { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem; padding: 0.8rem 1.25rem; border-radius: 0.95rem; font-size: 0.81rem; font-weight: 700; transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease; }
        .room-schedule-button:hover { transform: translateY(-1px); }
        .room-schedule-button--primary { border: 1px solid transparent; background: #44793b; color: #fff; }
        .room-schedule-button--primary:hover { background: #396733; }
        .room-schedule-button--ghost { border: 1px solid #dde4d8; background: #fff; color: #5b695f; }
        .room-schedule-button--ghost:hover { background: #f8faf6; }
        @media (max-width: 1100px) { .room-schedule-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) {
            .room-schedule-card--calendar, .room-schedule-card--details { padding: 1rem; }
            .room-schedule-day { width: 2.45rem; height: 2.45rem; font-size: 0.82rem; }
            .room-schedule-hours-grid { grid-template-columns: 1fr; }
            .room-schedule-status-row { display: grid; grid-template-columns: 1fr; }
        }
    </style>

    <section class="room-schedule-shell">
        <header class="room-schedule-head">
            <div>
                <h1 class="room-schedule-title">Room Schedule</h1>
                <p class="room-schedule-copy">{{ $roomCount }} rooms - pick a room first, then click any date to view or override its hours.</p>
            </div>

            <form method="POST" action="{{ route('admin.room-schedule.reset-all') }}">
                @csrf
                <input type="hidden" name="hyve_room_id" value="{{ $selectedRoom->id }}">
                <input type="hidden" name="month" value="{{ $monthSeed->format('Y-m') }}">
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                <button type="submit" class="room-schedule-reset">Reset selected room</button>
            </form>
        </header>

        <div class="room-schedule-grid">
            <article class="room-schedule-card room-schedule-card--calendar">
                <form method="GET" action="{{ route('admin.sections.room-schedule') }}" class="room-schedule-room-picker">
                    <input type="hidden" name="month" value="{{ $monthSeed->format('Y-m') }}">
                    <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                    <label class="room-schedule-field">
                        <span class="room-schedule-label">Room</span>
                        <select name="room" class="room-schedule-select" onchange="this.form.submit()">
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected((int) $selectedRoom->id === (int) $room->id)>{{ $room->room_name }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>

                <div class="room-schedule-calendar-head">
                    <h2 class="room-schedule-calendar-title">{{ $monthSeed->format('F Y') }}</h2>
                    <div class="room-schedule-calendar-nav">
                        <a href="{{ route('admin.sections.room-schedule', ['room' => $selectedRoom->id, 'month' => $previousMonth, 'date' => $selectedDate->copy()->subMonthNoOverflow()->toDateString()]) }}" aria-label="Previous month">&#8249;</a>
                        <a href="{{ route('admin.sections.room-schedule', ['room' => $selectedRoom->id, 'month' => $nextMonth, 'date' => $selectedDate->copy()->addMonthNoOverflow()->toDateString()]) }}" aria-label="Next month">&#8250;</a>
                    </div>
                </div>

                <div class="room-schedule-weekdays">
                    <span>Su</span>
                    <span>Mo</span>
                    <span>Tu</span>
                    <span>We</span>
                    <span>Th</span>
                    <span>Fr</span>
                    <span>Sa</span>
                </div>

                <div class="room-schedule-days">
                    @for ($cursor = $calendarStart->copy(); $cursor->lte($calendarEnd); $cursor->addDay())
                        @php
                            $dateKey = $cursor->toDateString();
                            $override = $calendarOverrides->get($dateKey);
                            $mode = $override?->mode ?? HyveScheduleOverride::MODE_DEFAULT;
                            $isOutside = ! $cursor->isSameMonth($monthSeed);
                        @endphp
                        <a
                            href="{{ route('admin.sections.room-schedule', ['room' => $selectedRoom->id, 'month' => $monthSeed->format('Y-m'), 'date' => $dateKey]) }}"
                            class="room-schedule-day is-{{ $mode }} @if ($selectedDate->isSameDay($cursor)) is-selected @endif @if ($isOutside) is-outside @endif"
                        >
                            {{ $cursor->day }}
                        </a>
                    @endfor
                </div>

                <div class="room-schedule-legend">
                    <span><i class="is-default"></i>Default hours</span>
                    <span><i class="is-custom"></i>Custom hours</span>
                    <span><i class="is-closed"></i>Closed</span>
                </div>
            </article>

            <article class="room-schedule-card room-schedule-card--details">
                <h2 class="room-schedule-details-title">{{ $selectedDate->format('l, F j, Y') }}</h2>
                <p class="room-schedule-details-subtitle">Selected room: {{ $selectedRoom->room_name }} - custom override for this date only</p>

                <form method="POST" action="{{ route('admin.room-schedule.store') }}" data-room-schedule-form>
                    @csrf
                    <input type="hidden" name="hyve_room_id" value="{{ $selectedRoomId }}">
                    <input type="hidden" name="booking_date" value="{{ $selectedDate->toDateString() }}">
                    <input type="hidden" name="month" value="{{ $monthSeed->format('Y-m') }}">

                    <div class="room-schedule-field">
                        <span class="room-schedule-label">Day Status</span>
                        <div class="room-schedule-status-row">
                            @foreach ([HyveScheduleOverride::MODE_DEFAULT => 'Default hours', HyveScheduleOverride::MODE_CUSTOM => 'Custom hours', HyveScheduleOverride::MODE_CLOSED => 'Closed'] as $mode => $label)
                                <label class="room-schedule-status-pill @if ($selectedMode === $mode) is-active @endif" data-status-pill data-mode="{{ $mode }}">
                                    <input class="room-schedule-radio" type="radio" name="mode" value="{{ $mode }}" @checked($selectedMode === $mode)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="room-schedule-hours-card @if ($selectedMode !== HyveScheduleOverride::MODE_CUSTOM) hidden @endif" data-custom-hours-card>
                        <div class="room-schedule-hours-grid">
                            <label class="room-schedule-field">
                                <span class="room-schedule-label">Opening time</span>
                                <select name="opening_time" class="room-schedule-select">
                                    @foreach ($openingOptions as $option)
                                        <option value="{{ $option }}" @selected($selectedOpeningTime === $option)>{{ Carbon::createFromFormat('H:i', $option)->format('g:i A') }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="room-schedule-field">
                                <span class="room-schedule-label">Closing time</span>
                                <select name="closing_time" class="room-schedule-select">
                                    @foreach ($closingOptions as $option)
                                        <option value="{{ $option }}" @selected($selectedClosingTime === $option)>
                                            {{ $option === '24:00' ? '12:00 AM next day' : Carbon::createFromFormat('H:i', $option)->format('g:i A') }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <p class="room-schedule-note">Custom hours apply only to the selected date. Booking will follow this exact open and close window.</p>
                    </div>

                    <label class="room-schedule-field">
                        <span class="room-schedule-label">Reason (optional)</span>
                        <input type="text" name="reason" class="room-schedule-input" value="{{ $selectedReason }}" placeholder="Add a short reason for this schedule change">
                    </label>

                    <div class="room-schedule-actions">
                        <button type="submit" class="room-schedule-button room-schedule-button--primary">Save changes</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.room-schedule.reset-day') }}" class="room-schedule-actions">
                    @csrf
                    <input type="hidden" name="hyve_room_id" value="{{ $selectedRoom->id }}">
                    <input type="hidden" name="booking_date" value="{{ $selectedDate->toDateString() }}">
                    <input type="hidden" name="month" value="{{ $monthSeed->format('Y-m') }}">
                    <button type="submit" class="room-schedule-button room-schedule-button--ghost">Reset to default</button>
                </form>
            </article>
        </div>
    </section>

    <script>
        (() => {
            const form = document.querySelector('[data-room-schedule-form]');

            if (!form) {
                return;
            }

            const pills = Array.from(form.querySelectorAll('[data-status-pill]'));
            const customHoursCard = form.querySelector('[data-custom-hours-card]');

            const syncState = () => {
                const checked = form.querySelector('input[name="mode"]:checked');
                const mode = checked ? checked.value : 'default';

                pills.forEach((pill) => {
                    pill.classList.toggle('is-active', pill.dataset.mode === mode);
                });

                customHoursCard?.classList.toggle('hidden', mode !== 'custom');
            };

            pills.forEach((pill) => {
                pill.addEventListener('click', () => {
                    const input = pill.querySelector('input[name="mode"]');

                    if (input) {
                        input.checked = true;
                    }

                    syncState();
                });
            });

            syncState();
        })();
    </script>
@endsection
