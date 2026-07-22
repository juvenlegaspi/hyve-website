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
     *     rate_name: string,
     *     breakdown: array<int, array{label: string, amount: float}>
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
        $exactDailyWindowQuote = $this->exactDailyWindowQuote($rateCard, $start, $end);

        if ($exactDailyWindowQuote) {
            $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($exactDailyWindowQuote['total_amount']);
            $downpaymentAmount = $minimumDownpaymentAmount;
            $balanceAmount = round($exactDailyWindowQuote['total_amount'] - $downpaymentAmount, 2);

            return [
                'rate_card' => $rateCard,
                'payment_setting' => $paymentSetting,
                'charge_period' => $exactDailyWindowQuote['charge_period'],
                'charge_period_label' => $exactDailyWindowQuote['charge_period_label'],
                'duration_hours' => $durationHours,
                'billed_hours' => $durationHours,
                'total_amount' => round($exactDailyWindowQuote['total_amount'], 2),
                'minimum_downpayment_amount' => $minimumDownpaymentAmount,
                'downpayment_amount' => $downpaymentAmount,
                'balance_amount' => $balanceAmount,
                'minimum_hours' => $minimumHours,
                'minimum_rate' => round($exactDailyWindowQuote['total_amount'], 2),
                'succeeding_hour_rate' => 0.0,
                'rate_name' => $rateCard->title.' - '.$exactDailyWindowQuote['charge_period_label'],
                'breakdown' => $exactDailyWindowQuote['breakdown'],
            ];
        }

        if ($isHolidayPricing) {
            $chargePeriod = 'holiday';
            $minimumRate = (float) $rateCard->night_minimum_rate;
            $succeedingHourRate = (float) $rateCard->night_succeeding_hour_rate;
            $additionalHours = max(0, $durationHours - $minimumHours);
            $totalAmount = $minimumRate + ($additionalHours * $succeedingHourRate);
            $chargePeriodLabel = 'Holiday Peak Use';
            $breakdown = $this->buildStandardBreakdown(
                'Holiday peak minimum',
                $minimumRate,
                $minimumHours,
                $durationHours,
                $succeedingHourRate
            );
        } else {
            $splitQuote = $this->splitDayNightQuote($rateCard, $start, $end, $minimumHours);
            $chargePeriod = $splitQuote['charge_period'];
            $chargePeriodLabel = $splitQuote['charge_period_label'];
            $minimumRate = $splitQuote['minimum_rate'];
            $succeedingHourRate = $splitQuote['succeeding_hour_rate'];
            $totalAmount = $splitQuote['total_amount'];
            $breakdown = $splitQuote['breakdown'];
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
            'breakdown' => $breakdown,
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
     *     rate_name: string
     * }|null
     */
    public function quoteExtensionForRoom(HyveRoom $room, string $bookingDate, string $startTime, string $endTime): ?array
    {
        $rateCard = $this->rateForRoom($room);

        if (! $rateCard) {
            return null;
        }

        $paymentSetting = $this->activePaymentSetting();
        [$start, $end] = $this->dateRange($bookingDate, $startTime, $endTime);
        $durationHours = round($start->diffInMinutes($end, true) / 60, 2);
        $minimumHours = max(1, (int) $rateCard->minimum_hours);

        $segments = $this->dayNightSegments($start, $end);
        if ($segments === []) {
            $period = $this->chargePeriodForStart($start);
            $segments = [[
                'period' => $period,
                'minutes' => (int) $start->diffInMinutes($end, true),
            ]];
        }

        $totalAmount = 0.0;
        $periodsUsed = [];
        $lastSucceedingRate = 0.0;
        $chargePeriod = 'day';

        foreach ($segments as $segment) {
            $periodsUsed[$segment['period']] = true;
            $succeedingHourlyRate = (float) ($segment['period'] === 'night'
                ? $rateCard->night_succeeding_hour_rate
                : $rateCard->day_succeeding_hour_rate);

            $lastSucceedingRate = $succeedingHourlyRate;
        }

        $chargePeriod = count($periodsUsed) > 1
            ? 'mixed'
            : array_key_first($periodsUsed);
        $chargePeriodLabel = match ($chargePeriod) {
            'night' => 'Night Use',
            'mixed' => 'Day + Night Use',
            default => 'Day Use',
        };

        if ($chargePeriod === 'mixed') {
            foreach ($segments as $segment) {
                $segmentHours = round($segment['minutes'] / 60, 2);
                $succeedingHourlyRate = (float) ($segment['period'] === 'night'
                    ? $rateCard->night_succeeding_hour_rate
                    : $rateCard->day_succeeding_hour_rate);

                $totalAmount += $segmentHours * $succeedingHourlyRate;
            }
        } else {
            $billableHours = $durationHours;
            $totalAmount = $billableHours * $lastSucceedingRate;
        }

        $totalAmount = round($totalAmount, 2);
        $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
        $downpaymentAmount = $minimumDownpaymentAmount;
        $balanceAmount = round($totalAmount - $downpaymentAmount, 2);
        $billedHours = $durationHours;

        return [
            'rate_card' => $rateCard,
            'payment_setting' => $paymentSetting,
            'charge_period' => $chargePeriod,
            'charge_period_label' => $chargePeriodLabel,
            'duration_hours' => $durationHours,
            'billed_hours' => $billedHours,
            'total_amount' => $totalAmount,
            'minimum_downpayment_amount' => $minimumDownpaymentAmount,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'minimum_hours' => $minimumHours,
            'minimum_rate' => 0.0,
            'succeeding_hour_rate' => round($chargePeriod === 'mixed' ? 0 : $lastSucceedingRate, 2),
            'rate_name' => $rateCard->title.' - Extension',
        ];
    }

    /**
     * @return array<int, array{label: string, amount: float, display_amount: string}>
     */
    public function monthlyOptionsForRoom(HyveRoom $room, ?HyveRate $rateCard = null): array
    {
        $rateCard ??= $this->rateForRoom($room);

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
     *     type: string,
     *     use_type: string|null,
     *     use_type_label: string|null
     * }>
     */
    public function longStayOptionsForRoom(HyveRoom $room, ?HyveRate $rateCard = null): array
    {
        $rateCard ??= $this->rateForRoom($room);

        if (! $rateCard) {
            return [];
        }

        $dailyWeekly = collect([
            'day' => $rateCard->day_use ?? [],
            'night' => $rateCard->night_use ?? [],
        ])->flatMap(function (mixed $rates, string $useType): array {
            return collect(is_array($rates) ? $rates : [])
                ->map(function (mixed $value, mixed $label) use ($useType): ?array {
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

                    $useTypeLabel = $this->longStayUseTypeLabel($useType);

                    return [
                        'label' => $useTypeLabel.' - '.$label,
                        'amount' => $amount,
                        'display_amount' => 'Php '.number_format($amount, 2),
                        'type' => $type,
                        'use_type' => $useType,
                        'use_type_label' => $useTypeLabel,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        });

        $monthly = collect($this->monthlyOptionsForRoom($room, $rateCard))
            ->map(fn (array $option): array => [
                ...$option,
                'type' => 'monthly',
                'use_type' => null,
                'use_type_label' => null,
            ]);

        return collect($dailyWeekly)
            ->concat($monthly)
            ->values()
            ->all();
    }

    public function longStayRequiresUseType(HyveRoom $room, string $startDate, string $endDate): bool
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return true;
        }

        $monthlyOption = collect($this->longStayOptionsForRoom($room))
            ->first(fn (array $option): bool => (string) $option['type'] === 'monthly');

        if (! $monthlyOption) {
            return true;
        }

        $dayCount = max(1, $start->diffInDays($end) + 1);

        return $dayCount < 29;
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
     *     breakdown: array<int, array{type: string, label: string, unit_count: int, days: int, amount: float}>,
     *     long_stay_use_type: string|null,
     *     long_stay_use_label: string|null,
     *     window_start_time: string,
     *     window_end_time: string
     * }|null
     */
    public function quoteForLongStayRoom(HyveRoom $room, string $planLabel, string $startDate, string $endDate, ?string $useType = null): ?array
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
        $monthlyCoverage = $this->flexibleMonthlyCoverage($dayCount);
        $requiresUseType = $this->longStayRequiresUseType($room, $startDate, $endDate);
        $resolvedUseType = $useType !== null && in_array($useType, ['day', 'night'], true)
            ? $useType
            : null;

        if (! $resolvedUseType && $selectedPlan && in_array((string) ($selectedPlan['type'] ?? ''), ['daily', 'weekly'], true)) {
            $resolvedUseType = (string) ($selectedPlan['use_type'] ?? '') ?: null;
        }

        if ($requiresUseType && ! $resolvedUseType) {
            return null;
        }

        [$windowStartTime, $windowEndTime] = $this->longStayWindowForUseType($requiresUseType ? $resolvedUseType : null);
        $useTypeLabel = $resolvedUseType ? $this->longStayUseTypeLabel($resolvedUseType) : null;

        if ($selectedPlan && (
            (string) ($selectedPlan['type'] ?? '') !== 'monthly'
            || $monthlyCoverage['remaining_days'] === 0
        )) {
            $unitType = (string) $selectedPlan['type'];
            $unitCount = match ($unitType) {
                'weekly' => (int) ceil($dayCount / 7),
                'monthly' => $this->calendarMonthUnitsToCoverRange($start, $end),
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
                'long_stay_use_type' => $resolvedUseType,
                'long_stay_use_label' => $useTypeLabel,
                'window_start_time' => $windowStartTime,
                'window_end_time' => $windowEndTime,
                'breakdown' => [[
                    'type' => $unitType,
                    'label' => (string) $selectedPlan['label'],
                    'unit_count' => $unitCount,
                    'days' => $dayCount,
                    'amount' => $totalAmount,
                ]],
            ];
        }

        $remainingDays = $monthlyCoverage['remaining_days'];
        $breakdown = [];
        $totalAmount = 0.0;

        $monthlyOption = $optionsByType->get('monthly');
        $weeklyOption = $options->first(fn (array $option): bool => (string) ($option['type'] ?? '') === 'weekly'
            && (string) ($option['use_type'] ?? '') === ($resolvedUseType ?: 'day')
        );
        $dailyOption = $options->first(fn (array $option): bool => (string) ($option['type'] ?? '') === 'daily'
            && (string) ($option['use_type'] ?? '') === ($resolvedUseType ?: 'day')
        );

        if ($monthlyOption && $monthlyCoverage['month_count'] > 0) {
            $amount = round($monthlyCoverage['month_count'] * (float) $monthlyOption['amount'], 2);
            $breakdown[] = [
                'type' => 'monthly',
                'label' => (string) $monthlyOption['label'],
                'unit_count' => $monthlyCoverage['month_count'],
                'days' => $monthlyCoverage['monthly_days'],
                'amount' => $amount,
            ];
            $totalAmount += $amount;
        }

        if ($monthlyCoverage['month_count'] === 0 && $weeklyOption && $remainingDays >= 7) {
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
            'long_stay_use_type' => $resolvedUseType,
            'long_stay_use_label' => $useTypeLabel,
            'window_start_time' => $windowStartTime,
            'window_end_time' => $windowEndTime,
            'breakdown' => $breakdown,
        ];
    }

    public function longStayUseTypeLabel(string $useType): string
    {
        return $useType === 'night' ? 'Night Use' : 'Day Use';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function longStayWindowForUseType(?string $useType): array
    {
        return match ($useType) {
            'night' => ['20:00', '08:00'],
            'day' => ['08:00', '20:00'],
            default => ['00:00', '23:59'],
        };
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
        $dayStartMinutes = 8 * 60;
        $nightStartMinutes = 20 * 60;

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
     *     total_amount: float,
     *     breakdown: array<int, array{label: string, amount: float}>
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
                'breakdown' => $this->buildStandardBreakdown(
                    $period === 'night' ? 'Night use minimum' : 'Day use minimum',
                    $minimumRate,
                    $minimumHours,
                    $durationHours,
                    $succeedingRate
                ),
            ];
        }

        $startingPeriod = $this->chargePeriodForStart($start);
        $minimumCost = (float) ($startingPeriod === 'night'
            ? $rateCard->night_minimum_rate
            : $rateCard->day_minimum_rate);
        $additionalCost = 0.0;
        $periodsUsed = [];
        $minimumCoveredUntil = $start->copy()->addHours($minimumHours);
        $segmentStart = $start->copy();
        $breakdown = [[
            'label' => sprintf(
                '%s minimum (first %d hour%s)',
                $startingPeriod === 'night' ? 'Night use' : 'Day use',
                $minimumHours,
                $minimumHours === 1 ? '' : 's'
            ),
            'amount' => round($minimumCost, 2),
        ]];

        foreach ($segments as $segment) {
            $periodsUsed[$segment['period']] = true;
            $segmentEnd = $segmentStart->copy()->addMinutes((int) $segment['minutes']);
            $succeedingStart = $segmentStart->greaterThan($minimumCoveredUntil)
                ? $segmentStart
                : $minimumCoveredUntil;
            $succeedingMinutes = $segmentEnd->greaterThan($succeedingStart)
                ? (int) $succeedingStart->diffInMinutes($segmentEnd, true)
                : 0;
            $succeedingHourlyRate = (float) ($segment['period'] === 'night'
                ? $rateCard->night_succeeding_hour_rate
                : $rateCard->day_succeeding_hour_rate);

            if ($succeedingMinutes > 0) {
                $succeedingHours = round($succeedingMinutes / 60, 2);
                $amount = round($succeedingHours * $succeedingHourlyRate, 2);
                $additionalCost += $amount;
                $breakdown[] = [
                    'label' => sprintf(
                        '%s succeeding hours (%s x %s)',
                        $segment['period'] === 'night' ? 'Night' : 'Day',
                        $this->formatHoursForBreakdown($succeedingHours),
                        $this->formatCurrencyForBreakdown($succeedingHourlyRate)
                    ),
                    'amount' => $amount,
                ];
            }

            $segmentStart = $segmentEnd;
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
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array{
     *     charge_period: string,
     *     charge_period_label: string,
     *     total_amount: float,
     *     breakdown: array<int, array{label: string, amount: float}>
     * }|null
     */
    private function exactDailyWindowQuote(HyveRate $rateCard, Carbon $start, Carbon $end): ?array
    {
        $startClock = $start->format('H:i');

        if ($startClock === '08:00') {
            $baseEnd = $start->copy()->setTime(20, 0);

            if ($end->lt($baseEnd)) {
                return null;
            }

            $amount = $this->dailyRateAmount($rateCard->day_use ?? []);

            if ($amount === null) {
                return null;
            }

            $overflowBreakdown = $this->overflowSucceedingBreakdown($rateCard, $baseEnd, $end);
            $overflowAmount = collect($overflowBreakdown)->sum('amount');

            return [
                'charge_period' => $overflowAmount > 0 ? 'mixed' : 'day',
                'charge_period_label' => $overflowAmount > 0 ? 'Day Daily Use + Extension' : 'Day Daily Use',
                'total_amount' => round($amount + $overflowAmount, 2),
                'breakdown' => array_merge([
                    [
                        'label' => 'Day daily rate (8:00 AM - 8:00 PM)',
                        'amount' => round($amount, 2),
                    ],
                ], $overflowBreakdown),
            ];
        }

        if ($startClock === '20:00') {
            $baseEnd = $start->copy()->addDay()->setTime(8, 0);

            if ($end->lt($baseEnd)) {
                return null;
            }

            $amount = $this->dailyRateAmount($rateCard->night_use ?? []);

            if ($amount === null) {
                return null;
            }

            $overflowBreakdown = $this->overflowSucceedingBreakdown($rateCard, $baseEnd, $end);
            $overflowAmount = collect($overflowBreakdown)->sum('amount');

            return [
                'charge_period' => $overflowAmount > 0 ? 'mixed' : 'night',
                'charge_period_label' => $overflowAmount > 0 ? 'Night Daily Use + Extension' : 'Night Daily Use',
                'total_amount' => round($amount + $overflowAmount, 2),
                'breakdown' => array_merge([
                    [
                        'label' => 'Night daily rate (8:00 PM - 8:00 AM)',
                        'amount' => round($amount, 2),
                    ],
                ], $overflowBreakdown),
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function buildStandardBreakdown(
        string $minimumLabel,
        float $minimumRate,
        int $minimumHours,
        float $durationHours,
        float $succeedingHourlyRate
    ): array {
        $breakdown = [[
            'label' => sprintf('%s (first %d hour%s)', $minimumLabel, $minimumHours, $minimumHours === 1 ? '' : 's'),
            'amount' => round($minimumRate, 2),
        ]];

        $additionalHours = round(max(0, $durationHours - $minimumHours), 2);

        if ($additionalHours > 0 && $succeedingHourlyRate > 0) {
            $breakdown[] = [
                'label' => sprintf(
                    'Succeeding hours (%s x %s)',
                    $this->formatHoursForBreakdown($additionalHours),
                    $this->formatCurrencyForBreakdown($succeedingHourlyRate)
                ),
                'amount' => round($additionalHours * $succeedingHourlyRate, 2),
            ];
        }

        return $breakdown;
    }

    private function overflowSucceedingCharge(HyveRate $rateCard, Carbon $start, Carbon $end): float
    {
        return round(collect($this->overflowSucceedingBreakdown($rateCard, $start, $end))->sum('amount'), 2);
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function overflowSucceedingBreakdown(HyveRate $rateCard, Carbon $start, Carbon $end): array
    {
        if ($end->lte($start)) {
            return [];
        }

        $segments = $this->dayNightSegments($start, $end);
        $breakdown = [];

        foreach ($segments as $segment) {
            $segmentHours = round($segment['minutes'] / 60, 2);
            $succeedingRate = (float) ($segment['period'] === 'night'
                ? $rateCard->night_succeeding_hour_rate
                : $rateCard->day_succeeding_hour_rate);

            $breakdown[] = [
                'label' => sprintf(
                    '%s extension after %s (%s x %s)',
                    $segment['period'] === 'night' ? 'Night' : 'Day',
                    $segment['period'] === 'night' ? '8:00 PM' : '8:00 AM',
                    $this->formatHoursForBreakdown($segmentHours),
                    $this->formatCurrencyForBreakdown($succeedingRate)
                ),
                'amount' => round($segmentHours * $succeedingRate, 2),
            ];
        }

        return $breakdown;
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
            return $moment->copy()->setTime(20, 0);
        }

        $minutes = ((int) $moment->format('H') * 60) + (int) $moment->format('i');

        if ($minutes >= 20 * 60) {
            return $moment->copy()->addDay()->setTime(8, 0);
        }

        return $moment->copy()->setTime(8, 0);
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

    private function dailyRateAmount(array $rates): ?float
    {
        foreach ($rates as $label => $value) {
            if (! is_string($label) || ! is_string($value)) {
                continue;
            }

            if (! str_contains(strtolower($label), 'daily')) {
                continue;
            }

            return $this->extractAmountFromText($value);
        }

        return null;
    }

    private function formatHoursForBreakdown(float $hours): string
    {
        if (fmod($hours, 1.0) === 0.0) {
            return (string) (int) $hours;
        }

        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    private function formatCurrencyForBreakdown(float $amount): string
    {
        return 'Php '.number_format($amount, 2);
    }

    /**
     * @return array{start: Carbon, end: Carbon, next_start: Carbon, days: int}
     */
    private function calendarMonthSegment(Carbon $start): array
    {
        $nextStart = $start->copy()->addMonthNoOverflow();
        $segmentEnd = $nextStart->copy()->subDay();

        return [
            'start' => $start->copy(),
            'end' => $segmentEnd,
            'next_start' => $nextStart,
            'days' => max(1, $start->diffInDays($segmentEnd) + 1),
        ];
    }

    /**
     * Treat every flexible 29-31 day block as one monthly unit. Any days beyond
     * the largest complete monthly band are billed as daily excess days.
     *
     * @return array{month_count: int, monthly_days: int, remaining_days: int}
     */
    private function flexibleMonthlyCoverage(int $dayCount): array
    {
        $monthCount = intdiv(max(0, $dayCount), 29);
        $monthlyDays = $monthCount > 0
            ? min($dayCount, $monthCount * 31)
            : 0;

        return [
            'month_count' => $monthCount,
            'monthly_days' => $monthlyDays,
            'remaining_days' => max(0, $dayCount - $monthlyDays),
        ];
    }

    private function calendarMonthUnitsToCoverRange(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $count++;
            $segment = $this->calendarMonthSegment($cursor);

            if ($segment['end']->gte($end)) {
                break;
            }

            $cursor = $segment['next_start'];
        }

        return max(1, $count);
    }
}
