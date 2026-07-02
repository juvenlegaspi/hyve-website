@extends('layouts.admin')

@php
    use App\Models\HyveCalendarEvent;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $resolvedModalMode = old('_modal_mode', $openModal);
    $resolvedEventType = old('type', $modalType ?: HyveCalendarEvent::TYPE_CUSTOM);
    $resolvedEditingEvent = $editingEvent;

    if ($errors->any() && ! $resolvedEditingEvent && old('event_id')) {
        $resolvedEditingEvent = $upcomingItems->firstWhere('id', (int) old('event_id'));
    }

    $isModalVisible = $errors->any() || filled($resolvedModalMode);
    $isEditing = $resolvedModalMode === 'edit' && $resolvedEditingEvent instanceof HyveCalendarEvent;
    $activeEvent = $isEditing ? $resolvedEditingEvent : null;

    $fieldTitle = old('title', $activeEvent?->title);
    $fieldType = $activeEvent?->source === HyveCalendarEvent::SOURCE_SYSTEM
        ? HyveCalendarEvent::TYPE_HOLIDAY
        : $resolvedEventType;
    $fieldScope = old('scope', $activeEvent?->scope ?? HyveCalendarEvent::SCOPE_ALL_ROOMS);
    $fieldAllDay = old('all_day', $activeEvent?->all_day ?? true);
    $fieldAffectsBooking = old('affects_booking', $activeEvent?->affects_booking ?? ($fieldType === HyveCalendarEvent::TYPE_BLOCKED));
    $fieldStatus = old('status', $activeEvent?->status ?? true);
    $fieldStartDate = old('start_date', optional($activeEvent?->start_date)->format('Y-m-d'));
    $fieldEndDate = old('end_date', optional($activeEvent?->end_date)->format('Y-m-d'));
    $fieldStartTime = old('start_time', $activeEvent?->start_time);
    $fieldEndTime = old('end_time', $activeEvent?->end_time);
    $fieldNotes = old('notes', $activeEvent?->notes);
    $selectedRoomIds = collect(old('room_ids', $activeEvent?->rooms->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

@section('content')
    <style>
        .calendar-events-shell { display: grid; gap: 1.35rem; }
        .calendar-events-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .calendar-events-title { margin: 0; color: #132320; font-size: 1.45rem; font-weight: 700; letter-spacing: -0.04em; }
        .calendar-events-copy { margin: 0.32rem 0 0; color: #a09a8d; font-size: 0.82rem; line-height: 1.55; }
        .calendar-events-actions { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
        .calendar-events-button { display: inline-flex; align-items: center; justify-content: center; min-height: 2.8rem; padding: 0.78rem 1.2rem; border-radius: 0.95rem; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: transform 160ms ease, background 160ms ease, border-color 160ms ease, color 160ms ease; }
        .calendar-events-button:hover { transform: translateY(-1px); }
        .calendar-events-button--ghost { border: 1px solid #dfe5da; background: #fff; color: #627066; }
        .calendar-events-button--ghost:hover { background: #f8faf6; color: #264134; }
        .calendar-events-button--primary { border: 1px solid transparent; background: #44793b; color: #fff; }
        .calendar-events-button--primary:hover { background: #396733; color: #fff; }
        .calendar-events-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(19rem, 1fr); gap: 1rem; align-items: start; }
        .calendar-events-card { border: 1px solid #dde5d9; border-radius: 1.4rem; background: rgba(255, 255, 255, 0.92); box-shadow: 0 18px 46px rgba(22, 33, 28, 0.05); }
        .calendar-events-card--list { padding: 1.25rem; }
        .calendar-events-card--legend { padding: 1.25rem 1.35rem; }
        .calendar-events-side-stack { display: grid; gap: 1rem; }
        .calendar-events-section-title { margin: 0; color: #16261f; font-size: 0.98rem; font-weight: 700; letter-spacing: -0.03em; }
        .calendar-events-list {
            display: grid;
            gap: 0.8rem;
            margin-top: 1.05rem;
            max-height: 29rem;
            overflow-y: auto;
            padding-right: 0.35rem;
            scrollbar-width: thin;
            scrollbar-color: #b9c3b4 transparent;
        }
        .calendar-events-list::-webkit-scrollbar { width: 0.45rem; }
        .calendar-events-list::-webkit-scrollbar-track { background: transparent; }
        .calendar-events-list::-webkit-scrollbar-thumb { background: #c8d2c2; border-radius: 999px; }
        .calendar-events-list::-webkit-scrollbar-thumb:hover { background: #aeb9a8; }
        .calendar-events-item { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 0.9rem; padding: 0.88rem 0.98rem; border-radius: 0.95rem; border-left: 3px solid transparent; }
        .calendar-events-item--blocked { background: #fff2f2; border-left-color: #ff6b6b; }
        .calendar-events-item--holiday { background: #edf4ff; border-left-color: #4d86ff; }
        .calendar-events-item--custom { background: #fff6ea; border-left-color: #ff7a1a; }
        .calendar-events-item-title { margin: 0; color: #182721; font-size: 0.9rem; font-weight: 700; line-height: 1.35; }
        .calendar-events-item-copy { margin: 0.16rem 0 0; color: #6c746d; font-size: 0.79rem; line-height: 1.55; }
        .calendar-events-tag { display: inline-flex; align-items: center; justify-content: center; min-width: 4.7rem; padding: 0.38rem 0.7rem; border-radius: 999px; font-size: 0.73rem; font-weight: 700; }
        .calendar-events-tag--blocked { background: #ffe4e4; color: #be3f3f; }
        .calendar-events-tag--holiday { background: #dce9ff; color: #386be0; }
        .calendar-events-tag--custom { background: #ffe7cc; color: #db6a00; }
        .calendar-events-edit { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: 1px solid #dde5da; border-radius: 0.8rem; background: rgba(255, 255, 255, 0.88); color: #7f897f; text-decoration: none; transition: background 160ms ease, color 160ms ease, border-color 160ms ease; }
        .calendar-events-edit:hover { background: #fff; color: #244032; border-color: #cfd9ca; }
        .calendar-events-side-title { margin: 0; color: #16261f; font-size: 0.98rem; font-weight: 700; letter-spacing: -0.03em; }
        .calendar-events-month-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 1rem; }
        .calendar-events-month-label { color: #16261f; font-size: 1rem; font-weight: 700; letter-spacing: -0.03em; }
        .calendar-events-month-nav { display: inline-flex; align-items: center; justify-content: center; width: 2.2rem; height: 2.2rem; border: 1px solid #dde5da; border-radius: 999px; color: #536359; text-decoration: none; background: #fff; }
        .calendar-events-month-nav:hover { background: #f8faf6; color: #294136; }
        .calendar-events-month-weekdays,
        .calendar-events-month-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.45rem; }
        .calendar-events-month-weekday { text-align: center; color: #b1ab9f; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding-bottom: 0.2rem; }
        .calendar-events-month-day { min-height: 4.2rem; border: 1px solid #ecf1e9; border-radius: 0.95rem; background: #fff; padding: 0.48rem; display: grid; align-content: start; gap: 0.3rem; }
        .calendar-events-month-day.is-outside { background: #fafbf9; color: #c4cabc; }
        .calendar-events-month-day.is-today { border-color: #a8c69f; box-shadow: 0 0 0 3px rgba(126, 167, 118, 0.12); }
        .calendar-events-month-day.is-blocked { background: #fff3f3; border-color: #ffd6d6; }
        .calendar-events-month-day.is-holiday { background: #eef4ff; border-color: #d4e2ff; }
        .calendar-events-month-day.is-custom { background: #fff8ee; border-color: #ffe5c0; }
        .calendar-events-month-number { color: #24352c; font-size: 0.79rem; font-weight: 700; line-height: 1; }
        .calendar-events-month-dot-row { display: flex; flex-wrap: wrap; gap: 0.2rem; }
        .calendar-events-month-dot { width: 0.38rem; height: 0.38rem; border-radius: 999px; }
        .calendar-events-month-dot--blocked { background: #ff6b6b; }
        .calendar-events-month-dot--holiday { background: #4d86ff; }
        .calendar-events-month-dot--custom { background: #ff7a1a; }
        .calendar-events-month-note { color: #7f897f; font-size: 0.65rem; line-height: 1.35; }
        .calendar-events-legend-block { padding-top: 1.1rem; }
        .calendar-events-legend-block + .calendar-events-legend-block { margin-top: 1rem; border-top: 1px solid #edf1ea; }
        .calendar-events-label { display: block; margin-bottom: 0.8rem; color: #b2ac9f; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .calendar-events-legend-list { display: grid; gap: 0.68rem; }
        .calendar-events-legend-item { display: flex; align-items: center; gap: 0.78rem; color: #1e2d27; font-size: 0.81rem; line-height: 1.45; }
        .calendar-events-legend-swatch { width: 0.92rem; height: 0.92rem; border-radius: 0.28rem; flex-shrink: 0; }
        .calendar-events-legend-swatch--booked { background: #44793b; }
        .calendar-events-legend-swatch--available { background: #e1e5ea; }
        .calendar-events-legend-swatch--holiday { background: #4d86ff; }
        .calendar-events-legend-swatch--custom { background: #ff7a1a; }
        .calendar-events-legend-swatch--blocked { background: #374151; }
        .calendar-events-note { margin: 0; color: #1d2c26; font-size: 0.82rem; line-height: 1.6; }
        .calendar-events-empty { margin-top: 1rem; border: 1px dashed #d8dfd4; border-radius: 1rem; padding: 1.5rem; color: #7c877d; font-size: 0.82rem; text-align: center; }

        .calendar-events-modal-shell { position: fixed; inset: 0; z-index: 90; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 1.1rem; }
        .calendar-events-modal-backdrop { position: absolute; inset: 0; background: rgba(17, 22, 20, 0.26); backdrop-filter: blur(10px); }
        .calendar-events-modal { position: relative; width: min(100%, 46rem); max-height: calc(100vh - 2.2rem); overflow-y: auto; border: 1px solid rgba(222, 230, 218, 0.9); border-radius: 1.55rem; background: rgba(255, 255, 255, 0.97); box-shadow: 0 26px 70px rgba(18, 31, 25, 0.18); padding: 1.45rem; }
        .calendar-events-modal-close { position: absolute; top: 1.15rem; right: 1.15rem; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: 0; border-radius: 999px; background: #f3f6f1; color: #4d5b53; text-decoration: none; font-size: 1.2rem; line-height: 1; }
        .calendar-events-modal-title { margin: 0; color: #16261f; font-size: 1.15rem; font-weight: 700; letter-spacing: -0.03em; }
        .calendar-events-modal-copy { margin: 0.28rem 0 0; color: #8b938c; font-size: 0.81rem; line-height: 1.55; }
        .calendar-events-form { display: grid; gap: 1rem; margin-top: 1.35rem; }
        .calendar-events-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.9rem; }
        .calendar-events-form-field { display: grid; gap: 0.42rem; }
        .calendar-events-form-field--full { grid-column: 1 / -1; }
        .calendar-events-form-label { color: #b1ab9f; font-size: 0.73rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .calendar-events-form-input,
        .calendar-events-form-select,
        .calendar-events-form-textarea {
            width: 100%;
            min-height: 3rem;
            border: 1px solid #dde5da;
            border-radius: 0.95rem;
            background: #fbfcfa;
            color: #1d2b25;
            font-size: 0.86rem;
            padding: 0.82rem 0.92rem;
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }
        .calendar-events-form-textarea { min-height: 7rem; resize: vertical; }
        .calendar-events-form-input:focus,
        .calendar-events-form-select:focus,
        .calendar-events-form-textarea:focus { border-color: #a6c39f; box-shadow: 0 0 0 4px rgba(130, 171, 119, 0.12); background: #fff; }
        .calendar-events-choice-row { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .calendar-events-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.52rem;
            border: 1px solid #dce4d8;
            border-radius: 999px;
            background: #fff;
            padding: 0.72rem 0.95rem;
            color: #425247;
            font-size: 0.81rem;
            font-weight: 700;
        }
        .calendar-events-pill input { accent-color: #44793b; }
        .calendar-events-room-pick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.7rem; padding: 0.9rem; border: 1px solid #e5ece1; border-radius: 1rem; background: #fbfcfa; }
        .calendar-events-room-option { display: flex; align-items: center; gap: 0.58rem; color: #294236; font-size: 0.81rem; }
        .calendar-events-room-option input { accent-color: #44793b; }
        .calendar-events-inline-note { margin: 0; color: #8a918a; font-size: 0.78rem; line-height: 1.55; }
        .calendar-events-modal-actions { display: flex; align-items: center; justify-content: space-between; gap: 0.9rem; padding-top: 0.35rem; border-top: 1px solid #edf1ea; }
        .calendar-events-modal-actions-right { display: flex; align-items: center; gap: 0.7rem; margin-left: auto; }
        .calendar-events-cancel { display: inline-flex; align-items: center; justify-content: center; min-height: 3rem; padding: 0.8rem 1.1rem; border: 1px solid #dbe3d7; border-radius: 0.95rem; background: #fff; color: #556359; font-size: 0.81rem; font-weight: 700; text-decoration: none; }
        .calendar-events-delete { display: inline-flex; align-items: center; justify-content: center; min-height: 3rem; padding: 0.8rem 1.1rem; border: 1px solid #ffc7c7; border-radius: 0.95rem; background: #fff; color: #d73f3f; font-size: 0.81rem; font-weight: 700; }
        .calendar-events-submit { display: inline-flex; align-items: center; justify-content: center; min-height: 3rem; padding: 0.8rem 1.35rem; border: 0; border-radius: 0.95rem; background: #44793b; color: #fff; font-size: 0.82rem; font-weight: 800; }
        .calendar-events-submit:hover { background: #396733; }
        .calendar-events-helper-tag { display: inline-flex; align-items: center; gap: 0.42rem; padding: 0.38rem 0.68rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; }
        .calendar-events-helper-tag--system { background: #edf4ff; color: #386be0; }
        .calendar-events-hidden { display: none !important; }
        @media (max-width: 1120px) { .calendar-events-grid { grid-template-columns: 1fr; } }
        @media (max-width: 760px) {
            .calendar-events-modal-shell { padding: 0.7rem; }
            .calendar-events-modal { max-height: calc(100vh - 1.4rem); border-radius: 1.15rem; }
            .calendar-events-card--list, .calendar-events-card--legend, .calendar-events-modal { padding: 1rem; }
            .calendar-events-item { grid-template-columns: 1fr; align-items: start; }
            .calendar-events-actions, .calendar-events-form-grid, .calendar-events-room-pick, .calendar-events-modal-actions { width: 100%; grid-template-columns: 1fr; }
            .calendar-events-modal-actions-right { width: 100%; margin-left: 0; flex-wrap: wrap; }
            .calendar-events-button, .calendar-events-cancel, .calendar-events-submit { width: 100%; }
        }
    </style>

    <section class="calendar-events-shell">
        <header class="calendar-events-head">
            <div>
                <h1 class="calendar-events-title">Calendar &amp; Events</h1>
                <p class="calendar-events-copy">PH holidays, custom events, and blocked dates</p>
            </div>

            <div class="calendar-events-actions">
                <a href="{{ route('admin.sections.calendar-events', ['modal' => 'create', 'type' => HyveCalendarEvent::TYPE_BLOCKED]) }}"
                    class="calendar-events-button calendar-events-button--ghost">
                    + Block date
                </a>
                <a href="{{ route('admin.sections.calendar-events', ['modal' => 'create', 'type' => HyveCalendarEvent::TYPE_CUSTOM]) }}"
                    class="calendar-events-button calendar-events-button--primary">
                    + Add event
                </a>
            </div>
        </header>

        <div class="calendar-events-grid">
            <article class="calendar-events-card calendar-events-card--list">
                <h2 class="calendar-events-section-title">Upcoming events &amp; holidays</h2>

                @if ($upcomingItems->isEmpty())
                    <div class="calendar-events-empty">
                        Wala pay events o holidays karon. Pwede naka mo-add ug block dates ug custom events diri.
                    </div>
                @else
                    <div class="calendar-events-list">
                        @foreach ($upcomingItems as $item)
                            @php
                                $tag = match ($item->type) {
                                    HyveCalendarEvent::TYPE_BLOCKED => 'Blocked',
                                    HyveCalendarEvent::TYPE_HOLIDAY => 'Holiday',
                                    default => 'Custom',
                                };

                                $dateLabel = $item->start_date->format('F j, Y');

                                if (! $item->start_date->isSameDay($item->end_date)) {
                                    $dateLabel .= ' - ' . $item->end_date->format('F j, Y');
                                }

                                $timeLabel = $item->all_day
                                    ? null
                                    : Carbon::createFromFormat('H:i', (string) $item->start_time)->format('g:i A')
                                        . ' - ' .
                                        Carbon::createFromFormat('H:i', (string) $item->end_time)->format('g:i A');

                                $scopeLabel = $item->scope === HyveCalendarEvent::SCOPE_ALL_ROOMS
                                    ? 'All rooms'
                                    : $item->rooms->pluck('room_name')->implode(', ');

                                $detailSegments = array_filter([
                                    $dateLabel,
                                    $timeLabel,
                                    $item->isHoliday() ? 'PH Holiday' : $tag,
                                    $scopeLabel,
                                    $item->notes,
                                ]);
                            @endphp

                            <div class="calendar-events-item calendar-events-item--{{ $item->type }}">
                                <div>
                                    <h3 class="calendar-events-item-title">{{ $item->title }}</h3>
                                    <p class="calendar-events-item-copy">{{ implode(' · ', $detailSegments) }}</p>
                                </div>

                                <span class="calendar-events-tag calendar-events-tag--{{ $item->type }}">{{ $tag }}</span>

                                <a href="{{ route('admin.sections.calendar-events', ['modal' => 'edit', 'event' => $item->id]) }}"
                                    class="calendar-events-edit"
                                    aria-label="Edit event">
                                    <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="m11.9 2.8 1.3 1.3a1 1 0 0 1 0 1.4l-6.7 6.7-2.7.6.6-2.7 6.7-6.7a1 1 0 0 1 1.4 0Z"></path>
                                        <path d="M10.5 4.2 11.8 5.5"></path>
                                    </svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>

            <div class="calendar-events-side-stack">
                <aside class="calendar-events-card calendar-events-card--legend">
                    <div class="calendar-events-month-head">
                        <a href="{{ $previousMonthUrl }}" class="calendar-events-month-nav" aria-label="Previous month">&larr;</a>
                        <div class="calendar-events-month-label">{{ $selectedMonth->format('F Y') }}</div>
                        <a href="{{ $nextMonthUrl }}" class="calendar-events-month-nav" aria-label="Next month">&rarr;</a>
                    </div>

                    <div class="calendar-events-month-weekdays">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                            <div class="calendar-events-month-weekday">{{ $weekday }}</div>
                        @endforeach
                    </div>

                    <div class="calendar-events-month-grid">
                        @foreach ($calendarDays as $day)
                            @php
                                $dayClasses = 'calendar-events-month-day';

                                if (! $day['in_month']) {
                                    $dayClasses .= ' is-outside';
                                }

                                if ($day['is_today']) {
                                    $dayClasses .= ' is-today';
                                }

                                if ($day['priority_type'] === HyveCalendarEvent::TYPE_BLOCKED) {
                                    $dayClasses .= ' is-blocked';
                                } elseif ($day['priority_type'] === HyveCalendarEvent::TYPE_HOLIDAY) {
                                    $dayClasses .= ' is-holiday';
                                } elseif ($day['priority_type'] === HyveCalendarEvent::TYPE_CUSTOM) {
                                    $dayClasses .= ' is-custom';
                                }
                            @endphp

                            <div class="{{ $dayClasses }}">
                                <div class="calendar-events-month-number">{{ $day['date']->day }}</div>

                                @if ($day['events_count'] > 0)
                                    <div class="calendar-events-month-dot-row">
                                        <span class="calendar-events-month-dot calendar-events-month-dot--{{ $day['priority_type'] }}"></span>
                                    </div>
                                    <div class="calendar-events-month-note">
                                        {{ $day['events_count'] }} {{ Str::plural('event', $day['events_count']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </aside>

                <aside class="calendar-events-card calendar-events-card--legend">
                    <h2 class="calendar-events-side-title">Legend &amp; settings</h2>

                    <section class="calendar-events-legend-block">
                        <span class="calendar-events-label">Calendar color coding</span>
                        <div class="calendar-events-legend-list">
                            <div class="calendar-events-legend-item"><span class="calendar-events-legend-swatch calendar-events-legend-swatch--booked"></span>Booked</div>
                            <div class="calendar-events-legend-item"><span class="calendar-events-legend-swatch calendar-events-legend-swatch--available"></span>Available</div>
                            <div class="calendar-events-legend-item"><span class="calendar-events-legend-swatch calendar-events-legend-swatch--holiday"></span>PH Holiday (auto-populated)</div>
                            <div class="calendar-events-legend-item"><span class="calendar-events-legend-swatch calendar-events-legend-swatch--custom"></span>Custom event (admin-added)</div>
                            <div class="calendar-events-legend-item"><span class="calendar-events-legend-swatch calendar-events-legend-swatch--blocked"></span>Blocked / closed</div>
                        </div>
                    </section>

                    <section class="calendar-events-legend-block">
                        <span class="calendar-events-label">Booking behavior</span>
                        <p class="calendar-events-note">
                            Holidays now use peak pricing automatically. Blocked dates and timed room events with booking lock are
                            automatically reflected on the customer booking flow.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </section>

    @if ($isModalVisible)
        <div class="calendar-events-modal-shell" id="calendar-event-modal">
            <a href="{{ route('admin.sections.calendar-events') }}" class="calendar-events-modal-backdrop" aria-label="Close modal"></a>

            <div class="calendar-events-modal">
                <a href="{{ route('admin.sections.calendar-events') }}" class="calendar-events-modal-close" aria-label="Close modal">&times;</a>

                <div>
                    <h2 class="calendar-events-modal-title">
                        {{ $isEditing ? 'Edit event' : ($fieldType === HyveCalendarEvent::TYPE_BLOCKED ? 'Block date' : 'Add event') }}
                    </h2>
                    <p class="calendar-events-modal-copy">
                        {{ $isEditing ? 'Update the event details and booking behavior.' : 'Set a holiday, admin event, or blocked room schedule.' }}
                    </p>
                </div>

                @if ($activeEvent?->source === HyveCalendarEvent::SOURCE_SYSTEM)
                    <div style="margin-top: 0.9rem;">
                        <span class="calendar-events-helper-tag calendar-events-helper-tag--system">System holiday</span>
                    </div>
                @endif

                <form method="POST"
                    id="calendar-event-form"
                    action="{{ $isEditing ? route('admin.calendar-events.update', $activeEvent) : route('admin.calendar-events.store') }}"
                    class="calendar-events-form">
                    @csrf
                    @if ($isEditing)
                        @method('PATCH')
                    @endif

                    <input type="hidden" name="_modal_mode" value="{{ $isEditing ? 'edit' : 'create' }}">
                    <input type="hidden" name="event_id" value="{{ $activeEvent?->id }}">

                    <div class="calendar-events-form-grid">
                        <div class="calendar-events-form-field calendar-events-form-field--full">
                            <label class="calendar-events-form-label" for="title">Title</label>
                            <input id="title" type="text" name="title" value="{{ $fieldTitle }}" class="calendar-events-form-input" maxlength="160" required>
                        </div>

                        <div class="calendar-events-form-field">
                            <label class="calendar-events-form-label" for="type">Type</label>
                            @if ($activeEvent?->source === HyveCalendarEvent::SOURCE_SYSTEM)
                                <input type="hidden" name="type" value="{{ HyveCalendarEvent::TYPE_HOLIDAY }}">
                                <input type="text" value="Holiday" class="calendar-events-form-input" disabled>
                            @else
                                <select id="type" name="type" class="calendar-events-form-select" data-calendar-event-type>
                                    <option value="{{ HyveCalendarEvent::TYPE_CUSTOM }}" @selected($fieldType === HyveCalendarEvent::TYPE_CUSTOM)>Custom event</option>
                                    <option value="{{ HyveCalendarEvent::TYPE_BLOCKED }}" @selected($fieldType === HyveCalendarEvent::TYPE_BLOCKED)>Blocked / closed</option>
                                    <option value="{{ HyveCalendarEvent::TYPE_HOLIDAY }}" @selected($fieldType === HyveCalendarEvent::TYPE_HOLIDAY)>Holiday</option>
                                </select>
                            @endif
                        </div>

                        <div class="calendar-events-form-field">
                            <label class="calendar-events-form-label" for="scope">Scope</label>
                            @if ($activeEvent?->source === HyveCalendarEvent::SOURCE_SYSTEM)
                                <input type="hidden" name="scope" value="{{ HyveCalendarEvent::SCOPE_ALL_ROOMS }}">
                                <input type="text" value="All rooms" class="calendar-events-form-input" disabled>
                            @else
                                <select id="scope" name="scope" class="calendar-events-form-select" data-calendar-event-scope>
                                    <option value="{{ HyveCalendarEvent::SCOPE_ALL_ROOMS }}" @selected($fieldScope === HyveCalendarEvent::SCOPE_ALL_ROOMS)>All rooms</option>
                                    <option value="{{ HyveCalendarEvent::SCOPE_SELECTED_ROOMS }}" @selected($fieldScope === HyveCalendarEvent::SCOPE_SELECTED_ROOMS)>Selected rooms only</option>
                                </select>
                            @endif
                        </div>

                        <div class="calendar-events-form-field">
                            <label class="calendar-events-form-label" for="start_date">Start date</label>
                            <input id="start_date" type="date" name="start_date" value="{{ $fieldStartDate }}" class="calendar-events-form-input" required>
                        </div>

                        <div class="calendar-events-form-field">
                            <label class="calendar-events-form-label" for="end_date">End date</label>
                            <input id="end_date" type="date" name="end_date" value="{{ $fieldEndDate }}" class="calendar-events-form-input" required>
                        </div>

                        <div class="calendar-events-form-field calendar-events-form-field--full">
                            <label class="calendar-events-form-label">Event behavior</label>
                            <div class="calendar-events-choice-row">
                                <label class="calendar-events-pill">
                                    <input type="checkbox" name="all_day" value="1" @checked($fieldAllDay) data-calendar-event-all-day>
                                    All day
                                </label>

                                @if ($activeEvent?->source !== HyveCalendarEvent::SOURCE_SYSTEM)
                                    <label class="calendar-events-pill" data-calendar-affects-booking-pill>
                                        <input type="checkbox" name="affects_booking" value="1" @checked($fieldAffectsBooking) data-calendar-affects-booking>
                                        Block customer booking
                                    </label>
                                @endif

                                <label class="calendar-events-pill">
                                    <input type="checkbox" name="status" value="1" @checked($fieldStatus)>
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="calendar-events-form-field" data-calendar-time-field>
                            <label class="calendar-events-form-label" for="start_time">Start time</label>
                            <input id="start_time" type="time" name="start_time" value="{{ $fieldStartTime }}" class="calendar-events-form-input">
                        </div>

                        <div class="calendar-events-form-field" data-calendar-time-field>
                            <label class="calendar-events-form-label" for="end_time">End time</label>
                            <input id="end_time" type="time" name="end_time" value="{{ $fieldEndTime }}" class="calendar-events-form-input">
                        </div>

                        <div class="calendar-events-form-field calendar-events-form-field--full" data-calendar-room-select>
                            <label class="calendar-events-form-label">Affected rooms</label>
                            <div class="calendar-events-room-pick">
                                @foreach ($rooms as $room)
                                    <label class="calendar-events-room-option">
                                        <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" @checked(in_array($room->id, $selectedRoomIds, true))>
                                        {{ $room->room_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="calendar-events-form-field calendar-events-form-field--full">
                            <label class="calendar-events-form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="calendar-events-form-textarea" maxlength="255">{{ $fieldNotes }}</textarea>
                            <p class="calendar-events-inline-note">Optional admin note, holiday note, or maintenance details.</p>
                        </div>
                    </div>

                    <div class="calendar-events-modal-actions">
                        <a href="{{ route('admin.sections.calendar-events') }}" class="calendar-events-cancel">Cancel</a>

                        <div class="calendar-events-modal-actions-right">
                            <button type="submit" class="calendar-events-submit">
                                {{ $isEditing ? 'Save changes' : 'Add event' }}
                            </button>
                        </div>
                    </div>
                </form>

                @if ($isEditing && $activeEvent?->source !== HyveCalendarEvent::SOURCE_SYSTEM)
                    <form method="POST" action="{{ route('admin.calendar-events.destroy', $activeEvent) }}" style="display:none;" id="calendar-event-delete-form">
                        @csrf
                        @method('DELETE')
                    </form>

                    <script>
                        (() => {
                            const modalActions = document.querySelector('.calendar-events-modal-actions-right');

                            if (!modalActions || document.getElementById('calendar-event-delete-trigger')) {
                                return;
                            }

                            const deleteButton = document.createElement('button');
                            deleteButton.type = 'submit';
                            deleteButton.id = 'calendar-event-delete-trigger';
                            deleteButton.className = 'calendar-events-delete';
                            deleteButton.textContent = 'Delete';
                            deleteButton.setAttribute('form', 'calendar-event-delete-form');
                            deleteButton.setAttribute('onclick', "return confirm('Delete this calendar event?')");
                            modalActions.prepend(deleteButton);
                        })();
                    </script>
                @endif
            </div>
        </div>
    @endif

    <script>
        (() => {
            const typeField = document.querySelector('[data-calendar-event-type]');
            const scopeField = document.querySelector('[data-calendar-event-scope]');
            const allDayField = document.querySelector('[data-calendar-event-all-day]');
            const affectsBookingPill = document.querySelector('[data-calendar-affects-booking-pill]');
            const affectsBookingField = document.querySelector('[data-calendar-affects-booking]');
            const roomSelect = document.querySelector('[data-calendar-room-select]');
            const timeFields = document.querySelectorAll('[data-calendar-time-field]');

            const syncCalendarEventModal = () => {
                const type = typeField ? typeField.value : @json($fieldType);
                const scope = scopeField ? scopeField.value : @json($fieldScope);
                const allDay = allDayField ? allDayField.checked : @json((bool) $fieldAllDay);

                if (type === 'holiday') {
                    if (scopeField) { scopeField.value = 'all_rooms'; }
                    if (allDayField) { allDayField.checked = true; }
                    if (affectsBookingField) {
                        affectsBookingField.checked = false;
                    }
                    if (affectsBookingPill) {
                        affectsBookingPill.classList.add('calendar-events-hidden');
                    }
                    if (roomSelect) {
                        roomSelect.classList.add('calendar-events-hidden');
                    }
                } else {
                    if (affectsBookingPill) {
                        affectsBookingPill.classList.remove('calendar-events-hidden');
                    }

                    if (type === 'blocked' && affectsBookingField) {
                        affectsBookingField.checked = true;
                    }

                    if (roomSelect) {
                        roomSelect.classList.toggle('calendar-events-hidden', scope !== 'selected_rooms');
                    }
                }

                timeFields.forEach((field) => {
                    field.classList.toggle('calendar-events-hidden', allDayField ? allDayField.checked : allDay);
                });
            };

            if (typeField) {
                typeField.addEventListener('change', syncCalendarEventModal);
            }

            if (scopeField) {
                scopeField.addEventListener('change', syncCalendarEventModal);
            }

            if (allDayField) {
                allDayField.addEventListener('change', syncCalendarEventModal);
            }

            syncCalendarEventModal();
        })();
    </script>
@endsection
