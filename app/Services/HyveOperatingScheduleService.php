<?php

namespace App\Services;

use App\Models\HyveRecurringClosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HyveOperatingScheduleService
{
    /** @var Collection<int, HyveRecurringClosure>|null */
    private ?Collection $activeClosures = null;

    public function isGloballyClosed(string|Carbon $date): bool
    {
        $weekday = $date instanceof Carbon ? $date->dayOfWeek : Carbon::parse($date)->dayOfWeek;

        return $this->activeClosures()->contains(
            fn (HyveRecurringClosure $closure): bool => $closure->weekday === $weekday
        );
    }

    public function reasonFor(string|Carbon $date): ?string
    {
        $weekday = $date instanceof Carbon ? $date->dayOfWeek : Carbon::parse($date)->dayOfWeek;

        return $this->activeClosures()
            ->first(fn (HyveRecurringClosure $closure): bool => $closure->weekday === $weekday)
            ?->reason;
    }

    public function forgetCachedClosures(): void
    {
        $this->activeClosures = null;
    }

    public function cacheSignature(): string
    {
        return sha1($this->activeClosures()
            ->map(fn (HyveRecurringClosure $closure): string => $closure->weekday.'|'.$closure->updated_at?->timestamp)
            ->implode(','));
    }

    public function hasClosureWithin(string|Carbon $startDate, string|Carbon $endDate): bool
    {
        $start = $startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay();
        $end = $endDate instanceof Carbon ? $endDate->copy()->startOfDay() : Carbon::parse($endDate)->startOfDay();

        for ($cursor = $start; $cursor->lte($end); $cursor->addDay()) {
            if ($this->isGloballyClosed($cursor)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, HyveRecurringClosure> */
    private function activeClosures(): Collection
    {
        return $this->activeClosures ??= HyveRecurringClosure::query()
            ->where('is_active', true)
            ->get();
    }
}
