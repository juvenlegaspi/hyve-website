<?php

namespace App\Support;

use App\Models\HyveCalendarEvent;
use App\Models\HyveRate;
use App\Models\HyveRoom;
use App\Models\PaymentSetting;
use Illuminate\Support\Carbon;

class HyvePricing
{
    public function activePaymentSetting(): ?PaymentSetting
    {
        return PaymentSetting::query()->active()->latest('id')->first();
    }

    public function rateForRoom(HyveRoom $room): ?HyveRate
    {
        return HyveRate::query()
            ->active()
            ->where('space_slug', $room->mappedSpaceSlug())
            ->first();
    }

    /**
     * @return array{
     *     rate_card: HyveRate,
     *     payment_setting: PaymentSetting|null,
     *     charge_period: string,
     *     charge_period_label: string,
     *     duration_hours: float,
     *     billed_hours: float,
     *     total_amount: float,
     *     minimum_downpayment_amount: float,
     *     downpayment_amount: float,
     *     balance_amount: float,
     *     minimum_hours: int,
     *     minimum_rate: float,
     *     succeeding_hour_rate: float,
     *     rate_name: string
     * }|null
     */
    public function quoteForRoom(HyveRoom $room, string $bookingDate, string $startTime, string $endTime): ?array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return null;
        }

        $paymentSetting = $this->activePaymentSetting();
        [$start, $end] = $this->dateRange($bookingDate, $startTime, $endTime);
        $durationHours = round($start->diffInMinutes($end, true) / 60, 2);
        $minimumHours = max(1, (int) $rateCard->minimum_hours);
        $isHolidayPricing = $this->isHolidayPricingDate($bookingDate);
        $baseChargePeriod = $this->chargePeriodForStart($start);

        if ($isHolidayPricing) {
            $chargePeriod = 'holiday';
            $minimumRate = (float) $rateCard->night_minimum_rate;
            $succeedingHourRate = (float) $rateCard->night_succeeding_hour_rate;
            $additionalHours = max(0, $durationHours - $minimumHours);
            $totalAmount = $minimumRate + ($additionalHours * $succeedingHourRate);
            $chargePeriodLabel = 'Holiday Peak Use';
        } else {
            $splitQuote = $this->splitDayNightQuote($rateCard, $start, $end, $minimumHours);
            $chargePeriod = $splitQuote['charge_period'];
            $chargePeriodLabel = $splitQuote['charge_period_label'];
            $minimumRate = $splitQuote['minimum_rate'];
            $succeedingHourRate = $splitQuote['succeeding_hour_rate'];
            $totalAmount = $splitQuote['total_amount'];
        }

        $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
        $downpaymentAmount = $minimumDownpaymentAmount;
        $balanceAmount = round($totalAmount - $downpaymentAmount, 2);

        return [
            'rate_card' => $rateCard,
            'payment_setting' => $paymentSetting,
            'charge_period' => $chargePeriod,
            'charge_period_label' => $chargePeriodLabel,
            'duration_hours' => $durationHours,
            'billed_hours' => max($durationHours, (float) $minimumHours),
            'total_amount' => round($totalAmount, 2),
            'minimum_downpayment_amount' => $minimumDownpaymentAmount,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'minimum_hours' => $minimumHours,
            'minimum_rate' => round($minimumRate, 2),
            'succeeding_hour_rate' => round($succeedingHourRate, 2),
            'rate_name' => $rateCard->title.' - '.$chargePeriodLabel,
        ];
    }

    /**
     * @return array<int, array{label: string, amount: float, display_amount: string}>
     */
    public function monthlyOptionsForRoom(HyveRoom $room): array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return [];
        }

        return collect($rateCard->memberships ?? [])
            ->map(function (mixed $value, mixed $label): ?array {
                if (! is_string($label) || ! is_string($value)) {
                    return null;
                }

                $amount = $this->extractAmountFromText($value);

                if ($amount === null || ! str_contains(strtolower($label), 'monthly')) {
                    return null;
                }

                return [
                    'label' => $label,
                    'amount' => $amount,
                    'display_amount' => 'Php '.number_format($amount, 2),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     amount: float,
     *     display_amount: string,
     *     type: string
     * }>
     */
    public function longStayOptionsForRoom(HyveRoom $room): array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return [];
        }

        $dailyWeekly = collect($rateCard->day_use ?? [])
            ->map(function (mixed $value, mixed $label): ?array {
                if (! is_string($label) || ! is_string($value)) {
                    return null;
                }

                $normalized = strtolower($label);
                $type = null;

                if (str_contains($normalized, 'daily')) {
                    $type = 'daily';
                } elseif (str_contains($normalized, 'weekly')) {
                    $type = 'weekly';
                }

                if (! $type) {
                    return null;
                }

                $amount = $this->extractAmountFromText($value);

                if ($amount === null) {
                    return null;
                }

                return [
                    'label' => $label,
                    'amount' => $amount,
                    'display_amount' => 'Php '.number_format($amount, 2),
                    'type' => $type,
                ];
            })
            ->filter();

        $monthly = collect($this->monthlyOptionsForRoom($room))
            ->map(fn (array $option): array => [
                ...$option,
                'type' => 'monthly',
            ]);

        return $dailyWeekly
            ->concat($monthly)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     rate_card: HyveRate,
     *     payment_setting: PaymentSetting|null,
     *     charge_period: string,
     *     charge_period_label: string,
     *     duration_hours: float,
     *     billed_hours: float,
     *     total_amount: float,
     *     minimum_downpayment_amount: float,
     *     downpayment_amount: float,
     *     balance_amount: float,
     *     minimum_hours: int,
     *     minimum_rate: float,
     *     succeeding_hour_rate: float,
     *     rate_name: string,
     *     monthly_plan_label: string
     * }|null
     */
    public function quoteForMonthlyRoom(HyveRoom $room, string $planLabel): ?array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return null;
        }

        $selectedPlan = collect($this->monthlyOptionsForRoom($room))
            ->first(fn (array $option): bool => $option['label'] === $planLabel);

        if (! $selectedPlan) {
            return null;
        }

        $paymentSetting = $this->activePaymentSetting();
        $totalAmount = (float) $selectedPlan['amount'];
        $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
        $downpaymentAmount = $minimumDownpaymentAmount;
        $balanceAmount = round($totalAmount - $downpaymentAmount, 2);

        return [
            'rate_card' => $rateCard,
            'payment_setting' => $paymentSetting,
            'charge_period' => 'monthly',
            'charge_period_label' => 'Monthly Booking',
            'duration_hours' => 0.0,
            'billed_hours' => 0.0,
            'total_amount' => round($totalAmount, 2),
            'minimum_downpayment_amount' => $minimumDownpaymentAmount,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'minimum_hours' => 0,
            'minimum_rate' => round($totalAmount, 2),
            'succeeding_hour_rate' => 0.0,
            'rate_name' => $rateCard->title.' - '.$selectedPlan['label'],
            'monthly_plan_label' => $selectedPlan['label'],
        ];
    }

    /**
     * @return array{
     *     rate_card: HyveRate,
     *     payment_setting: PaymentSetting|null,
     *     charge_period: string,
     *     charge_period_label: string,
     *     duration_hours: float,
     *     billed_hours: float,
     *     total_amount: float,
     *     minimum_downpayment_amount: float,
     *     downpayment_amount: float,
     *     balance_amount: float,
     *     minimum_hours: int,
     *     minimum_rate: float,
     *     succeeding_hour_rate: float,
     *     rate_name: string,
     *     monthly_plan_label: string,
     *     unit_type: string,
     *     unit_count: int,
     *     unit_label: string,
     *     booking_end_date: string,
     *     breakdown: array<int, array{type: string, label: string, unit_count: int, days: int, amount: float}>
     * }|null
     */
    public function quoteForLongStayRoom(HyveRoom $room, string $planLabel, string $startDate, string $endDate): ?array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return null;
        }

        $options = collect($this->longStayOptionsForRoom($room));
        $optionsByType = $options->keyBy(fn (array $option): string => (string) $option['type']);
        $selectedPlan = $planLabel !== ''
            ? $options->first(fn (array $option): bool => $option['label'] === $planLabel)
            : null;

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return null;
        }

        $dayCount = max(1, $start->diffInDays($end) + 1);

        if ($selectedPlan) {
            $unitType = (string) $selectedPlan['type'];
            $unitCount = match ($unitType) {
                'weekly' => (int) ceil($dayCount / 7),
                'monthly' => (int) ceil($dayCount / 30),
                default => $dayCount,
            };
            $unitLabel = match ($unitType) {
                'weekly' => $unitCount.' week'.($unitCount === 1 ? '' : 's'),
                'monthly' => $unitCount.' month'.($unitCount === 1 ? '' : 's'),
                default => $unitCount.' day'.($unitCount === 1 ? '' : 's'),
            };
            $paymentSetting = $this->activePaymentSetting();
            $totalAmount = round($unitCount * (float) $selectedPlan['amount'], 2);
            $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
            $downpaymentAmount = $minimumDownpaymentAmount;
            $balanceAmount = round($totalAmount - $downpaymentAmount, 2);
            $chargePeriodLabel = match ($unitType) {
                'weekly' => 'Weekly Booking',
                'monthly' => 'Monthly Booking',
                default => 'Daily Booking',
            };

            return [
                'rate_card' => $rateCard,
                'payment_setting' => $paymentSetting,
                'charge_period' => $unitType,
                'charge_period_label' => $chargePeriodLabel,
                'duration_hours' => 0.0,
                'billed_hours' => 0.0,
                'total_amount' => $totalAmount,
                'minimum_downpayment_amount' => $minimumDownpaymentAmount,
                'downpayment_amount' => $downpaymentAmount,
                'balance_amount' => $balanceAmount,
                'minimum_hours' => 0,
                'minimum_rate' => round((float) $selectedPlan['amount'], 2),
                'succeeding_hour_rate' => 0.0,
                'rate_name' => $rateCard->title.' - '.$selectedPlan['label'],
                'monthly_plan_label' => $selectedPlan['label'],
                'unit_type' => $unitType,
                'unit_count' => $unitCount,
                'unit_label' => $unitLabel,
                'booking_end_date' => $end->toDateString(),
                'breakdown' => [[
                    'type' => $unitType,
                    'label' => (string) $selectedPlan['label'],
                    'unit_count' => $unitCount,
                    'days' => $dayCount,
                    'amount' => $totalAmount,
                ]],
            ];
        }

        $remainingDays = $dayCount;
        $breakdown = [];
        $totalAmount = 0.0;

        $monthlyOption = $optionsByType->get('monthly');
        $weeklyOption = $optionsByType->get('weekly');
        $dailyOption = $optionsByType->get('daily');

        if ($monthlyOption && $remainingDays >= 30) {
            $monthCount = intdiv($remainingDays, 30);
            $remainingDays -= $monthCount * 30;
            $amount = round($monthCount * (float) $monthlyOption['amount'], 2);
            $breakdown[] = [
                'type' => 'monthly',
                'label' => (string) $monthlyOption['label'],
                'unit_count' => $monthCount,
                'days' => $monthCount * 30,
                'amount' => $amount,
            ];
            $totalAmount += $amount;
        }

        if ($weeklyOption && $remainingDays >= 7) {
            $weekCount = intdiv($remainingDays, 7);
            $remainingDays -= $weekCount * 7;
            $amount = round($weekCount * (float) $weeklyOption['amount'], 2);
            $breakdown[] = [
                'type' => 'weekly',
                'label' => (string) $weeklyOption['label'],
                'unit_count' => $weekCount,
                'days' => $weekCount * 7,
                'amount' => $amount,
            ];
            $totalAmount += $amount;
        }

        if ($dailyOption && $remainingDays > 0) {
            $amount = round($remainingDays * (float) $dailyOption['amount'], 2);
            $breakdown[] = [
                'type' => 'daily',
                'label' => (string) $dailyOption['label'],
                'unit_count' => $remainingDays,
                'days' => $remainingDays,
                'amount' => $amount,
            ];
            $totalAmount += $amount;
            $remainingDays = 0;
        }

        if ($breakdown === []) {
            return null;
        }

        if ($remainingDays > 0) {
            return null;
        }

        $unitLabel = collect($breakdown)
            ->map(function (array $item): string {
                return match ($item['type']) {
                    'monthly' => $item['unit_count'].' month'.($item['unit_count'] === 1 ? '' : 's'),
                    'weekly' => $item['unit_count'].' week'.($item['unit_count'] === 1 ? '' : 's'),
                    default => $item['unit_count'].' day'.($item['unit_count'] === 1 ? '' : 's'),
                };
            })
            ->implode(' + ');

        $primaryBreakdown = $breakdown[0];
        $unitType = (string) $primaryBreakdown['type'];
        $unitCount = (int) $primaryBreakdown['unit_count'];

        $paymentSetting = $this->activePaymentSetting();
        $totalAmount = round($totalAmount, 2);
        $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
        $downpaymentAmount = $minimumDownpaymentAmount;
        $balanceAmount = round($totalAmount - $downpaymentAmount, 2);
        $chargePeriodLabel = 'Long Stay Booking';
        $planSummaryLabel = collect($breakdown)
            ->map(function (array $item): string {
                return match ($item['type']) {
                    'monthly' => $item['unit_count'].' month'.($item['unit_count'] === 1 ? '' : 's'),
                    'weekly' => $item['unit_count'].' week'.($item['unit_count'] === 1 ? '' : 's'),
                    default => $item['unit_count'].' day'.($item['unit_count'] === 1 ? '' : 's'),
                };
            })
            ->implode(' + ');

        return [
            'rate_card' => $rateCard,
            'payment_setting' => $paymentSetting,
            'charge_period' => $unitType,
            'charge_period_label' => $chargePeriodLabel,
            'duration_hours' => 0.0,
            'billed_hours' => 0.0,
            'total_amount' => $totalAmount,
            'minimum_downpayment_amount' => $minimumDownpaymentAmount,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'minimum_hours' => 0,
            'minimum_rate' => round((float) $primaryBreakdown['amount'], 2),
            'succeeding_hour_rate' => 0.0,
            'rate_name' => $rateCard->title.' - '.$planSummaryLabel,
            'monthly_plan_label' => $planSummaryLabel,
            'unit_type' => $unitType,
            'unit_count' => $unitCount,
            'unit_label' => $unitLabel,
            'booking_end_date' => $end->toDateString(),
            'breakdown' => $breakdown,
        ];
    }

    public function minimumDownpaymentForTotal(float $totalAmount): float
    {
        if ($totalAmount <= 1000) {
            return round($totalAmount / 2, 2);
        }

        return 500.00;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(string $bookingDate, string $startTime, string $endTime): array
    {
        $start = Carbon::parse($bookingDate.' '.$startTime);
        $end = Carbon::parse($bookingDate.' '.$endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function chargePeriodForStart(Carbon $start): string
    {
        $minutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $dayStartMinutes = 6 * 60;
        $nightStartMinutes = 18 * 60;

        return $minutes >= $nightStartMinutes || $minutes < $dayStartMinutes
            ? 'night'
            : 'day';
    }

    /**
     * @return array{
     *     charge_period: string,
     *     charge_period_label: string,
     *     minimum_rate: float,
     *     succeeding_hour_rate: float,
     *     total_amount: float
     * }
     */
    private function splitDayNightQuote(HyveRate $rateCard, Carbon $start, Carbon $end, int $minimumHours): array
    {
        $segments = $this->dayNightSegments($start, $end);

        if ($segments === []) {
            $period = $this->chargePeriodForStart($start);
            $minimumRate = (float) ($period === 'night' ? $rateCard->night_minimum_rate : $rateCard->day_minimum_rate);
            $succeedingRate = (float) ($period === 'night' ? $rateCard->night_succeeding_hour_rate : $rateCard->day_succeeding_hour_rate);
            $durationHours = round($start->diffInMinutes($end, true) / 60, 2);

            return [
                'charge_period' => $period,
                'charge_period_label' => $period === 'night' ? 'Night Use' : 'Day Use',
                'minimum_rate' => round($minimumRate, 2),
                'succeeding_hour_rate' => round($succeedingRate, 2),
                'total_amount' => round($minimumRate + (max(0, $durationHours - $minimumHours) * $succeedingRate), 2),
            ];
        }

        $minimumCost = 0.0;
        $additionalCost = 0.0;
        $periodsUsed = [];

        foreach ($segments as $segment) {
            $periodsUsed[$segment['period']] = true;
            $segmentHours = round($segment['minutes'] / 60, 2);
            $minimumRate = (float) ($segment['period'] === 'night'
                ? $rateCard->night_minimum_rate
                : $rateCard->day_minimum_rate);
            $succeedingHourlyRate = (float) ($segment['period'] === 'night'
                ? $rateCard->night_succeeding_hour_rate
                : $rateCard->day_succeeding_hour_rate);
            $additionalHours = max(0, $segmentHours - $minimumHours);

            $minimumCost += $minimumRate;
            $additionalCost += $additionalHours * $succeedingHourlyRate;
        }

        $chargePeriod = count($periodsUsed) > 1
            ? 'mixed'
            : array_key_first($periodsUsed);
        $chargePeriodLabel = match ($chargePeriod) {
            'night' => 'Night Use',
            'mixed' => 'Day + Night Use',
            default => 'Day Use',
        };

        return [
            'charge_period' => $chargePeriod,
            'charge_period_label' => $chargePeriodLabel,
            'minimum_rate' => round($minimumCost, 2),
            'succeeding_hour_rate' => round($chargePeriod === 'mixed' ? 0 : ($chargePeriod === 'night'
                ? (float) $rateCard->night_succeeding_hour_rate
                : (float) $rateCard->day_succeeding_hour_rate), 2),
            'total_amount' => round($minimumCost + $additionalCost, 2),
        ];
    }

    /**
     * @return array<int, array{period: string, minutes: int}>
     */
    private function dayNightSegments(Carbon $start, Carbon $end): array
    {
        $segments = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $period = $this->chargePeriodForStart($cursor);
            $boundary = $this->nextDayNightBoundary($cursor, $period);
            $segmentEnd = $boundary->lt($end) ? $boundary : $end;
            $minutes = (int) $cursor->diffInMinutes($segmentEnd, true);

            if ($minutes > 0) {
                $segments[] = [
                    'period' => $period,
                    'minutes' => $minutes,
                ];
            }

            $cursor = $segmentEnd->copy();
        }

        return $segments;
    }

    private function nextDayNightBoundary(Carbon $moment, string $period): Carbon
    {
        if ($period === 'day') {
            return $moment->copy()->setTime(18, 0);
        }

        $minutes = ((int) $moment->format('H') * 60) + (int) $moment->format('i');

        if ($minutes >= 18 * 60) {
            return $moment->copy()->addDay()->setTime(6, 0);
        }

        return $moment->copy()->setTime(6, 0);
    }

    private function isHolidayPricingDate(string $bookingDate): bool
    {
        return HyveCalendarEvent::query()
            ->active()
            ->where('type', HyveCalendarEvent::TYPE_HOLIDAY)
            ->whereDate('start_date', '<=', $bookingDate)
            ->whereDate('end_date', '>=', $bookingDate)
            ->exists();
    }

    private function extractAmountFromText(string $value): ?float
    {
        if (! preg_match('/([\d,]+(?:\.\d+)?)/', $value, $matches)) {
            return null;
        }

        return (float) str_replace(',', '', $matches[1]);
    }
}
