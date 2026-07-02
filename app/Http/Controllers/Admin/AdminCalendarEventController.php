<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HyveCalendarEvent;
use App\Models\HyveRoom;
use App\Support\HyveCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminCalendarEventController extends Controller
{
    public function __construct(
        private readonly HyveCalendarService $calendarService,
    ) {
    }

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $selectedMonth = Carbon::createFromDate(
            (int) $request->query('year', $today->year),
            (int) $request->query('month', $today->month),
            1
        )->startOfMonth();

        $this->calendarService->ensureSystemHolidaysForYears([
            $today->year,
            $today->copy()->addYear()->year,
            $selectedMonth->year,
        ]);

        $upcomingItems = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->upcoming()
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $monthEvents = HyveCalendarEvent::query()
            ->with('rooms:id,room_name')
            ->active()
            ->whereDate('start_date', '<=', $selectedMonth->copy()->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $selectedMonth->toDateString())
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $editingEvent = null;
        $eventId = $request->query('event');

        if ($eventId) {
            $editingEvent = $upcomingItems->firstWhere('id', (int) $eventId)
                ?? HyveCalendarEvent::query()->with('rooms:id,room_name')->find($eventId);
        }

        return view('admin.calendar-events.index', [
            'meta' => [
                'title' => 'Calendar & Events | HYVE Admin',
                'description' => 'Track PH holidays, custom events, and blocked booking dates from one clean admin workspace.',
            ],
            'adminUser' => $request->user(),
            'upcomingItems' => $upcomingItems,
            'rooms' => HyveRoom::query()->active()->orderBy('room_name')->get(['id', 'room_name']),
            'editingEvent' => $editingEvent,
            'selectedMonth' => $selectedMonth,
            'previousMonthUrl' => route('admin.sections.calendar-events', [
                'month' => $selectedMonth->copy()->subMonth()->month,
                'year' => $selectedMonth->copy()->subMonth()->year,
            ]),
            'nextMonthUrl' => route('admin.sections.calendar-events', [
                'month' => $selectedMonth->copy()->addMonth()->month,
                'year' => $selectedMonth->copy()->addMonth()->year,
            ]),
            'calendarDays' => $this->buildCalendarDays($selectedMonth, $monthEvents),
            'openModal' => (string) $request->query('modal', $editingEvent ? 'edit' : ''),
            'modalType' => (string) $request->query('type', $editingEvent?->type ?? HyveCalendarEvent::TYPE_CUSTOM),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $event = HyveCalendarEvent::query()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'scope' => $validated['scope'],
            'source' => HyveCalendarEvent::SOURCE_ADMIN,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['all_day'] ? null : ($validated['start_time'] ?? null),
            'end_time' => $validated['all_day'] ? null : ($validated['end_time'] ?? null),
            'all_day' => $validated['all_day'],
            'affects_booking' => $validated['affects_booking'],
            'status' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        $event->rooms()->sync($validated['scope'] === HyveCalendarEvent::SCOPE_SELECTED_ROOMS ? $validated['room_ids'] : []);

        return redirect()
            ->route('admin.sections.calendar-events')
            ->with('admin_success', 'Calendar event added successfully.');
    }

    public function update(Request $request, HyveCalendarEvent $calendarEvent): RedirectResponse
    {
        $validated = $this->validatedPayload($request, $calendarEvent);

        $calendarEvent->update([
            'title' => $validated['title'],
            'type' => $calendarEvent->source === HyveCalendarEvent::SOURCE_SYSTEM ? HyveCalendarEvent::TYPE_HOLIDAY : $validated['type'],
            'scope' => $calendarEvent->source === HyveCalendarEvent::SOURCE_SYSTEM ? HyveCalendarEvent::SCOPE_ALL_ROOMS : $validated['scope'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['all_day'] ? null : ($validated['start_time'] ?? null),
            'end_time' => $validated['all_day'] ? null : ($validated['end_time'] ?? null),
            'all_day' => $validated['all_day'],
            'affects_booking' => $calendarEvent->source === HyveCalendarEvent::SOURCE_SYSTEM ? false : $validated['affects_booking'],
            'status' => (bool) $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($calendarEvent->source !== HyveCalendarEvent::SOURCE_SYSTEM) {
            $calendarEvent->rooms()->sync($validated['scope'] === HyveCalendarEvent::SCOPE_SELECTED_ROOMS ? $validated['room_ids'] : []);
        }

        return redirect()
            ->route('admin.sections.calendar-events')
            ->with('admin_success', 'Calendar event updated successfully.');
    }

    public function destroy(HyveCalendarEvent $calendarEvent): RedirectResponse
    {
        if ($calendarEvent->source === HyveCalendarEvent::SOURCE_SYSTEM) {
            return redirect()
                ->route('admin.sections.calendar-events')
                ->with('admin_error', 'System holidays cannot be deleted. Edit or deactivate them instead.');
        }

        $calendarEvent->rooms()->detach();
        $calendarEvent->delete();

        return redirect()
            ->route('admin.sections.calendar-events')
            ->with('admin_success', 'Calendar event deleted successfully.');
    }

    /**
     * @param HyveCalendarEvent|null $event
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?HyveCalendarEvent $event = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in([
                HyveCalendarEvent::TYPE_HOLIDAY,
                HyveCalendarEvent::TYPE_CUSTOM,
                HyveCalendarEvent::TYPE_BLOCKED,
            ])],
            'scope' => ['required', Rule::in([
                HyveCalendarEvent::SCOPE_ALL_ROOMS,
                HyveCalendarEvent::SCOPE_SELECTED_ROOMS,
            ])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'all_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'affects_booking' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('hyve_rooms', 'id')->where(fn ($query) => $query->where('status', 0))],
        ]);

        $validated['all_day'] = (bool) ($validated['all_day'] ?? false);
        $validated['affects_booking'] = (bool) ($validated['affects_booking'] ?? false);
        $validated['status'] = (bool) ($validated['status'] ?? true);
        $validated['room_ids'] = array_values(array_unique(array_map('intval', $validated['room_ids'] ?? [])));

        if ($event?->source === HyveCalendarEvent::SOURCE_SYSTEM) {
            $validated['type'] = HyveCalendarEvent::TYPE_HOLIDAY;
            $validated['scope'] = HyveCalendarEvent::SCOPE_ALL_ROOMS;
            $validated['all_day'] = true;
            $validated['affects_booking'] = false;
            $validated['room_ids'] = [];
        }

        if ($validated['type'] === HyveCalendarEvent::TYPE_HOLIDAY) {
            $validated['all_day'] = true;
            $validated['affects_booking'] = false;
            $validated['scope'] = HyveCalendarEvent::SCOPE_ALL_ROOMS;
            $validated['room_ids'] = [];
        }

        if (! $validated['all_day']) {
            if (empty($validated['start_time']) || empty($validated['end_time'])) {
                throw ValidationException::withMessages([
                    'start_time' => 'Provide a valid start and end time for timed events.',
                ]);
            }

            if ($validated['end_time'] <= $validated['start_time']) {
                throw ValidationException::withMessages([
                    'end_time' => 'End time must be later than start time.',
                ]);
            }
        }

        if ($validated['scope'] === HyveCalendarEvent::SCOPE_SELECTED_ROOMS && $validated['room_ids'] === []) {
            throw ValidationException::withMessages([
                'room_ids' => 'Select at least one room for room-specific events.',
            ]);
        }

        if ($validated['type'] === HyveCalendarEvent::TYPE_BLOCKED) {
            $validated['affects_booking'] = true;
        }

        return $validated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCalendarDays(Carbon $selectedMonth, Collection $monthEvents): array
    {
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $days = [];

        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $dayEvents = $monthEvents
                ->filter(fn (HyveCalendarEvent $event) => $cursor->between($event->start_date, $event->end_date));

            $priorityType = $dayEvents->contains(fn (HyveCalendarEvent $event) => $event->type === HyveCalendarEvent::TYPE_BLOCKED)
                ? HyveCalendarEvent::TYPE_BLOCKED
                : ($dayEvents->contains(fn (HyveCalendarEvent $event) => $event->type === HyveCalendarEvent::TYPE_HOLIDAY)
                    ? HyveCalendarEvent::TYPE_HOLIDAY
                    : ($dayEvents->isNotEmpty() ? HyveCalendarEvent::TYPE_CUSTOM : null));

            $days[] = [
                'date' => $cursor->copy(),
                'in_month' => $cursor->month === $selectedMonth->month,
                'is_today' => $cursor->isToday(),
                'events_count' => $dayEvents->count(),
                'priority_type' => $priorityType,
                'labels' => $dayEvents->take(2)->pluck('title')->all(),
            ];
        }

        return $days;
    }
}
