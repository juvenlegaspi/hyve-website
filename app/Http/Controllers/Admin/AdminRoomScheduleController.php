<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminRoomScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $rooms = HyveRoom::query()->active()->orderBy('room_name')->get();
        abort_if($rooms->isEmpty(), 404, 'No active rooms found.');
        $monthSeed = $this->resolvedMonthSeed($request, $today);
        $selectedDate = $this->resolvedSelectedDate($request, $monthSeed, $today);
        $selectedRoom = $this->resolvedSelectedRoom($request, $rooms);
        $monthStart = $monthSeed->copy()->startOfMonth();
        $monthEnd = $monthSeed->copy()->endOfMonth();
        $overrides = HyveScheduleOverride::query()
            ->where(function ($query) use ($selectedRoom) {
                $query->where('hyve_room_id', $selectedRoom->getKey())
                    ->orWhereNull('hyve_room_id');
            })
            ->whereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->orderBy('booking_date')
            ->get()
            ->groupBy(fn (HyveScheduleOverride $override) => $override->booking_date->toDateString())
            ->map(fn ($group) => $group->first());

        $selectedOverride = HyveScheduleOverride::query()
            ->where(function ($query) use ($selectedRoom) {
                $query->where('hyve_room_id', $selectedRoom->getKey())
                    ->orWhereNull('hyve_room_id');
            })
            ->whereDate('booking_date', $selectedDate->toDateString())
            ->orderByRaw('case when hyve_room_id is null then 1 else 0 end')
            ->first();

        return view('admin.rooms.schedule', [
            'meta' => [
                'title' => 'Room Schedule | HYVE Admin',
                'description' => 'Manage room schedule status, daily overrides, and custom operating hours.',
            ],
            'adminUser' => $request->user(),
            'roomCount' => HyveRoom::query()->count(),
            'rooms' => $rooms,
            'selectedRoom' => $selectedRoom,
            'todayDate' => $today->toDateString(),
            'defaultOpeningTime' => (string) config('hyve.booking.opening_time', '00:00'),
            'defaultClosingTime' => (string) config('hyve.booking.closing_time', '24:00'),
            'monthSeed' => $monthSeed,
            'selectedDate' => $selectedDate,
            'calendarOverrides' => $overrides,
            'selectedOverride' => $selectedOverride,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateOverridePayload($request);
        $mode = (string) $validated['mode'];
        $redirectParams = $this->redirectParams($validated['booking_date'], $validated['month'] ?? null, (int) $validated['hyve_room_id']);

        if ($mode === HyveScheduleOverride::MODE_DEFAULT) {
            HyveScheduleOverride::query()
                ->where('hyve_room_id', $validated['hyve_room_id'])
                ->whereDate('booking_date', $validated['booking_date'])
                ->delete();

            return redirect()
                ->route('admin.sections.room-schedule', $redirectParams)
                ->with('admin_success', 'Schedule reset to default hours.');
        }

        HyveScheduleOverride::query()->updateOrCreate(
            [
                'hyve_room_id' => $validated['hyve_room_id'],
                'booking_date' => $validated['booking_date'],
            ],
            [
                'mode' => $mode,
                'opening_time' => $mode === HyveScheduleOverride::MODE_CUSTOM ? $validated['opening_time'] : null,
                'closing_time' => $mode === HyveScheduleOverride::MODE_CUSTOM ? $validated['closing_time'] : null,
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return redirect()
            ->route('admin.sections.room-schedule', $redirectParams)
            ->with('admin_success', 'Room schedule updated successfully.');
    }

    public function resetDay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'booking_date' => ['required', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        HyveScheduleOverride::query()
            ->where('hyve_room_id', $validated['hyve_room_id'])
            ->whereDate('booking_date', $validated['booking_date'])
            ->delete();

        return redirect()
            ->route('admin.sections.room-schedule', $this->redirectParams($validated['booking_date'], $validated['month'] ?? null, (int) $validated['hyve_room_id']))
            ->with('admin_success', 'Selected day was reset to default hours.');
    }

    public function resetAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date'],
        ]);

        HyveScheduleOverride::query()
            ->where('hyve_room_id', $validated['hyve_room_id'])
            ->delete();

        return redirect()
            ->route('admin.sections.room-schedule', array_filter([
                'room' => $validated['hyve_room_id'],
                'month' => $validated['month'] ?? null,
                'date' => $validated['date'] ?? null,
            ]))
            ->with('admin_success', 'Selected room schedule overrides were reset to default hours.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOverridePayload(Request $request): array
    {
        $validated = $request->validate([
            'hyve_room_id' => ['required', 'integer', 'exists:hyve_rooms,id'],
            'booking_date' => ['required', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'mode' => ['required', 'in:default,custom,closed'],
            'opening_time' => ['nullable', 'string'],
            'closing_time' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['mode'] !== HyveScheduleOverride::MODE_CUSTOM) {
            return $validated;
        }

        $openingTime = (string) ($validated['opening_time'] ?? '');
        $closingTime = (string) ($validated['closing_time'] ?? '');

        if (! $this->isValidScheduleTime($openingTime, false)) {
            throw ValidationException::withMessages([
                'opening_time' => 'Choose a valid opening time.',
            ]);
        }

        if (! $this->isValidScheduleTime($closingTime, true)) {
            throw ValidationException::withMessages([
                'closing_time' => 'Choose a valid closing time.',
            ]);
        }

        $start = $this->minutesForTime($openingTime);
        $end = $this->minutesForTime($closingTime);

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'closing_time' => 'Closing time must be later than opening time.',
            ]);
        }

        return $validated;
    }

    private function isValidScheduleTime(string $value, bool $allowTwentyFourHour): bool
    {
        if ($value === '24:00') {
            return $allowTwentyFourHour;
        }

        return (bool) preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $value);
    }

    private function minutesForTime(string $value): int
    {
        if ($value === '24:00') {
            return 24 * 60;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return ($hours * 60) + $minutes;
    }

    private function resolvedMonthSeed(Request $request, Carbon $today): Carbon
    {
        $month = (string) $request->query('month', $today->format('Y-m'));

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return $today->copy()->startOfMonth();
        }
    }

    private function resolvedSelectedDate(Request $request, Carbon $monthSeed, Carbon $today): Carbon
    {
        $selected = (string) $request->query('date', $today->toDateString());

        try {
            return Carbon::parse($selected)->startOfDay();
        } catch (\Throwable) {
            return $monthSeed->copy();
        }
    }

    /**
     * @return array<string, string>
     */
    private function redirectParams(string $bookingDate, ?string $month = null, ?int $roomId = null): array
    {
        $date = Carbon::parse($bookingDate);

        return [
            'room' => $roomId ?: null,
            'month' => $month ?: $date->format('Y-m'),
            'date' => $date->toDateString(),
        ];
    }

    private function resolvedSelectedRoom(Request $request, $rooms): HyveRoom
    {
        $selectedRoomId = (int) $request->query('room', $request->input('hyve_room_id', $rooms->first()->getKey()));

        return $rooms->firstWhere('id', $selectedRoomId) ?? $rooms->first();
    }
}
