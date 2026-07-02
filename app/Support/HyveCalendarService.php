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
        foreach ($years as $year) {
            $this->ensureSystemHolidaysForYear((int) $year);
        }
    }

    public function ensureSystemHolidaysForYear(int $year): void
    {
        foreach ($this->standardHolidayPayloads($year) as $payload) {
            HyveCalendarEvent::query()->updateOrCreate(
                [
                    'title' => $payload['title'],
                    'source' => HyveCalendarEvent::SOURCE_SYSTEM,
                    'start_date' => $payload['start_date'],
                ],
                $payload,
            );
        }
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
}
