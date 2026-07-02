<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HyveRate;
use App\Models\HyveRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminPricingRuleController extends Controller
{
    public function index(Request $request): View
    {
        $rates = HyveRate::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $roomMappings = HyveRoom::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (HyveRoom $room): string => $room->mappedSpaceSlug())
            ->map(fn (Collection $group): array => $group->pluck('room_name')->values()->all());

        return view('admin.pricing-rules.index', [
            'meta' => [
                'title' => 'Pricing Rules | HYVE Admin',
                'description' => 'Manage the live HYVE rate cards used by the booking flow.',
            ],
            'adminUser' => $request->user(),
            'rates' => $rates,
            'roomMappings' => $roomMappings,
        ]);
    }

    public function update(Request $request, HyveRate $rate): RedirectResponse
    {
        $validated = $request->validate([
            'rate_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'minimum_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'day_minimum_rate' => ['required', 'numeric', 'min:0'],
            'day_succeeding_hour_rate' => ['required', 'numeric', 'min:0'],
            'night_minimum_rate' => ['required', 'numeric', 'min:0'],
            'night_succeeding_hour_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $minimumHours = (int) $validated['minimum_hours'];
        $dayMinimumRate = round((float) $validated['day_minimum_rate'], 2);
        $daySucceedingRate = round((float) $validated['day_succeeding_hour_rate'], 2);
        $nightMinimumRate = round((float) $validated['night_minimum_rate'], 2);
        $nightSucceedingRate = round((float) $validated['night_succeeding_hour_rate'], 2);

        $rate->update([
            'title' => $validated['title'],
            'minimum_hours' => $minimumHours,
            'day_minimum_rate' => $dayMinimumRate,
            'day_succeeding_hour_rate' => $daySucceedingRate,
            'night_minimum_rate' => $nightMinimumRate,
            'night_succeeding_hour_rate' => $nightSucceedingRate,
            'day_use' => $this->syncDisplayRates($rate->day_use, $minimumHours, $dayMinimumRate, $daySucceedingRate),
            'night_use' => $this->syncDisplayRates($rate->night_use, $minimumHours, $nightMinimumRate, $nightSucceedingRate),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.sections.pricing-rules')
            ->with('admin_success', 'Pricing rule updated. Booking quotes will now use the new rates.');
    }

    /**
     * @param  array<mixed>|null  $existing
     * @return array<string, string>
     */
    private function syncDisplayRates(?array $existing, int $minimumHours, float $minimumRate, float $succeedingRate): array
    {
        $existing = collect($existing ?? [])
            ->reject(function (mixed $value, mixed $key): bool {
                $normalizedKey = strtolower((string) $key);

                return str_contains($normalizedKey, 'hrs min')
                    || str_contains($normalizedKey, 'hr min')
                    || str_contains($normalizedKey, 'succeeding hr');
            })
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [(string) $key => (string) $value])
            ->all();

        $minimumLabel = $minimumHours === 1 ? '1 hr min' : $minimumHours.' hrs min';

        return [
            $minimumLabel => $this->formatPeso($minimumRate),
            'Succeeding hr' => $this->formatPeso($succeedingRate),
            ...$existing,
        ];
    }

    private function formatPeso(float $amount): string
    {
        if (fmod($amount, 1.0) === 0.0) {
            return 'Php '.number_format($amount, 0);
        }

        return 'Php '.number_format($amount, 2);
    }
}
