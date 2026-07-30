<?php

namespace App\Support;

use App\Models\HyveCalendarEvent;
use Illuminate\Support\Carbon;

class HyveCalendarService
{
    /**
     * @param array<int, int|string> $years
     */
    public function ensureSystemHolidaysForYears(array $years): void
    {
        $years = collect($years)
            ->map(fn (int|string $year): int => (int) $year)
            ->filter(fn (int $year): bool => $year > 0)
            ->unique()
            ->sort()
            ->values();

        if ($years->isEmpty()) {
            return;
        }

        $payloads = $years
            ->flatMap(fn (int $year): array => $this->standardHolidayPayloads($year))
            ->values();
        $existing = HyveCalendarEvent::query()
            ->where('source', HyveCalendarEvent::SOURCE_SYSTEM)
            ->whereDate('start_date', '>=', $years->first().'-01-01')
            ->whereDate('start_date', '<=', $years->last().'-12-31')
            ->get()
            ->keyBy(fn (HyveCalendarEvent $event): string => $this->holidayKey(
                (string) $event->title,
                $event->start_date?->toDateString() ?? '',
            ));

        foreach ($payloads as $payload) {
            $key = $this->holidayKey($payload['title'], $payload['start_date']);
            $event = $existing->get($key);

            if (! $event instanceof HyveCalendarEvent) {
                $existing->put($key, HyveCalendarEvent::query()->create($payload));
                continue;
            }

            $event->fill($payload);

            if ($event->isDirty()) {
                $event->save();
            }
        }
    }

    public function ensureSystemHolidaysForYear(int $year): void
    {
        $this->ensureSystemHolidaysForYears([$year]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function standardHolidayPayloads(int $year): array
    {
        $nationalHeroesDay = Carbon::create($year, 8, 1)->lastOfMonth(Carbon::MONDAY);

        return [
            $this->holidayPayload('New Year\'s Day', Carbon::create($year, 1, 1)),
            $this->holidayPayload('Araw ng Kagitingan', Carbon::create($year, 4, 9)),
            $this->holidayPayload('Independence Day', Carbon::create($year, 6, 12)),
            $this->holidayPayload('Ninoy Aquino Day', Carbon::create($year, 8, 21)),
            $this->holidayPayload('National Heroes Day', $nationalHeroesDay),
            $this->holidayPayload('Bonifacio Day', Carbon::create($year, 11, 30)),
            $this->holidayPayload('Christmas Day', Carbon::create($year, 12, 25)),
            $this->holidayPayload('Rizal Day', Carbon::create($year, 12, 30)),
        ];
    }

    private function holidayPayload(string $title, Carbon $date): array
    {
        return [
            'title' => $title,
            'type' => HyveCalendarEvent::TYPE_HOLIDAY,
            'scope' => HyveCalendarEvent::SCOPE_ALL_ROOMS,
            'source' => HyveCalendarEvent::SOURCE_SYSTEM,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'start_time' => null,
            'end_time' => null,
            'all_day' => true,
            'affects_booking' => false,
            'status' => true,
            'notes' => 'PH Holiday',
        ];
    }

    private function holidayKey(string $title, string $date): string
    {
        return $title.'|'.$date;
    }
}
