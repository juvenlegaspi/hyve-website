<?php

namespace App\Support;

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
        $chargePeriod = $this->chargePeriodForStart($start);
        $minimumRate = (float) ($chargePeriod === 'night' ? $rateCard->night_minimum_rate : $rateCard->day_minimum_rate);
        $succeedingHourRate = (float) ($chargePeriod === 'night' ? $rateCard->night_succeeding_hour_rate : $rateCard->day_succeeding_hour_rate);
        $additionalHours = max(0, $durationHours - $minimumHours);
        $totalAmount = $minimumRate + ($additionalHours * $succeedingHourRate);
        $minimumDownpaymentAmount = $this->minimumDownpaymentForTotal($totalAmount);
        $downpaymentAmount = $minimumDownpaymentAmount;
        $balanceAmount = round($totalAmount - $downpaymentAmount, 2);

        return [
            'rate_card' => $rateCard,
            'payment_setting' => $paymentSetting,
            'charge_period' => $chargePeriod,
            'charge_period_label' => $chargePeriod === 'night' ? 'Night Use' : 'Day Use',
            'duration_hours' => $durationHours,
            'billed_hours' => max($durationHours, (float) $minimumHours),
            'total_amount' => round($totalAmount, 2),
            'minimum_downpayment_amount' => $minimumDownpaymentAmount,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => $balanceAmount,
            'minimum_hours' => $minimumHours,
            'minimum_rate' => round($minimumRate, 2),
            'succeeding_hour_rate' => round($succeedingHourRate, 2),
            'rate_name' => $rateCard->title.' - '.($chargePeriod === 'night' ? 'Night Use' : 'Day Use'),
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
}
