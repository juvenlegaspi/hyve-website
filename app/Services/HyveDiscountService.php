<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRate;
use Illuminate\Support\Collection;

class HyveDiscountService
{
    public const NONE = 'none';
    public const SENIOR = 'senior';
    public const PWD = 'pwd';
    public const VOUCHER_COMMON_2H = 'voucher_common_2h';
    public const ENGAGEMENT = 'engagement';
    public const BOARD_REVIEWEE = 'board_reviewee';
    public const EARLY_BIRD = 'early_bird';

    /** @return array<string, array{label: string}> */
    public function definitions(): array
    {
        return [
            self::NONE => ['label' => 'No discount'],
            self::SENIOR => ['label' => 'Senior Citizen (20%)'],
            self::PWD => ['label' => 'PWD (20%)'],
            self::VOUCHER_COMMON_2H => ['label' => 'HYVE Voucher - 2 Hours Free (Common Area)'],
            self::ENGAGEMENT => ['label' => 'Engagement Discount (20% hourly/daily, 10% weekly/monthly)'],
            self::BOARD_REVIEWEE => ['label' => 'Board Exam Reviewee (20% Common, 15% 2 Seats, 10% 4 Seats)'],
            self::EARLY_BIRD => ['label' => 'Early Bird Promo (10%, 4:00 AM-10:00 AM)'],
        ];
    }

    public function isValidCode(?string $code): bool
    {
        return array_key_exists((string) $code, $this->definitions());
    }

    public function requiresReference(?string $code): bool
    {
        return in_array((string) $code, [
            self::SENIOR,
            self::PWD,
            self::VOUCHER_COMMON_2H,
            self::BOARD_REVIEWEE,
            self::ENGAGEMENT,
        ], true);
    }

    /**
     * @param array<int, array<string, mixed>> $overrides keyed by booking detail id
     * @param list<array<string, mixed>> $extraLines
     * @return array{discount_code:?string,discount_label:?string,discount_rate:?float,discount_amount:float,discounted_total_amount:float,gross_total:float,eligible:bool,eligibility_note:string}
     */
    public function calculate(
        BookingHeader $header,
        ?string $code = null,
        array $overrides = [],
        array $extraLines = [],
    ): array {
        $normalizedCode = $this->isValidCode($code) ? (string) $code : self::NONE;
        $header->loadMissing(['details.space']);
        $lines = $header->details
            ->where('status', '!=', BookingDetail::STATUS_CANCELLED)
            ->map(fn (BookingDetail $detail): array => $this->lineFromDetail($detail, $overrides[$detail->getKey()] ?? []))
            ->concat($extraLines)
            ->values();
        $grossTotal = round((float) $lines->sum('subtotal'), 2);
        $discountAmount = round($this->discountAmount($normalizedCode, $lines), 2);
        $discountAmount = min($grossTotal, max(0, $discountAmount));
        $definition = $this->definitions()[$normalizedCode];
        $eligible = $normalizedCode === self::NONE || $discountAmount > 0;
        $effectiveRate = $grossTotal > 0 ? round(($discountAmount / $grossTotal) * 100, 4) : 0.0;

        return [
            'discount_code' => $normalizedCode === self::NONE ? null : $normalizedCode,
            'discount_label' => $normalizedCode === self::NONE ? null : $definition['label'],
            'discount_rate' => $normalizedCode === self::NONE ? null : $effectiveRate,
            'discount_amount' => $discountAmount,
            'discounted_total_amount' => round(max(0, $grossTotal - $discountAmount), 2),
            'gross_total' => $grossTotal,
            'eligible' => $eligible,
            'eligibility_note' => $this->eligibilityNote($normalizedCode, $eligible),
        ];
    }

    /** @return array<string, mixed> */
    private function lineFromDetail(BookingDetail $detail, array $override): array
    {
        return [
            'subtotal' => round((float) ($override['subtotal'] ?? $detail->subtotal ?? 0), 2),
            'space_slug' => (string) ($override['space_slug'] ?? $detail->space?->slug ?? ''),
            'charge_period' => strtolower((string) ($override['charge_period'] ?? $detail->charge_period ?? '')),
            'start_time' => substr((string) ($override['start_time'] ?? $detail->start_time ?? ''), 0, 5),
            'billed_hours' => (float) ($override['billed_hours'] ?? $detail->billed_hours ?? 0),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $lines */
    private function discountAmount(string $code, Collection $lines): float
    {
        return match ($code) {
            self::SENIOR, self::PWD => (float) $lines->sum(fn (array $line): float => $line['subtotal'] * 0.20),
            self::VOUCHER_COMMON_2H => $this->voucherAmount($lines),
            self::ENGAGEMENT => (float) $lines->sum(fn (array $line): float =>
                $line['subtotal'] * (in_array($line['charge_period'], ['weekly', 'monthly'], true) ? 0.10 : 0.20)),
            self::BOARD_REVIEWEE => (float) $lines->sum(fn (array $line): float =>
                $line['subtotal'] * $this->boardRevieweeRate($line['space_slug'])),
            self::EARLY_BIRD => (float) $lines->sum(fn (array $line): float =>
                $this->isEarlyBirdLine($line) ? $line['subtotal'] * 0.10 : 0.0),
            default => 0.0,
        };
    }

    /** @param Collection<int, array<string, mixed>> $lines */
    private function voucherAmount(Collection $lines): float
    {
        $eligibleLine = $lines->first(fn (array $line): bool =>
            $line['space_slug'] === 'common-area'
            && ! in_array($line['charge_period'], ['weekly', 'monthly'], true));

        if (! $eligibleLine) {
            return 0.0;
        }

        $rate = HyveRate::query()->where('space_slug', 'common-area')->where('is_active', true)->first();
        $startMinutes = $this->timeMinutes($eligibleLine['start_time']);
        $isNight = $startMinutes < 8 * 60 || $startMinutes >= 20 * 60;
        $twoHourValue = (float) ($isNight ? $rate?->night_minimum_rate : $rate?->day_minimum_rate);

        return min((float) $eligibleLine['subtotal'], max(0, $twoHourValue));
    }

    private function boardRevieweeRate(string $spaceSlug): float
    {
        return match ($spaceSlug) {
            'common-area' => 0.20,
            'fortitude-office-2-seats' => 0.15,
            'tenacity-office-4-seats' => 0.10,
            default => 0.0,
        };
    }

    /** @param array<string, mixed> $line */
    private function isEarlyBirdLine(array $line): bool
    {
        if (in_array($line['charge_period'], ['weekly', 'monthly'], true)) {
            return false;
        }

        $minutes = $this->timeMinutes((string) $line['start_time']);

        return $minutes >= 4 * 60 && $minutes <= 10 * 60;
    }

    private function timeMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return ($hour * 60) + $minute;
    }

    private function eligibilityNote(string $code, bool $eligible): string
    {
        if ($eligible) {
            return 'Eligible for this booking.';
        }

        return match ($code) {
            self::VOUCHER_COMMON_2H => 'Requires an hourly/day/night Common Area booking.',
            self::BOARD_REVIEWEE => 'Applies only to Common Area, 2 Seats Office, or 4 Seats Office.',
            self::EARLY_BIRD => 'Requires a booking start from 4:00 AM through 10:00 AM.',
            default => 'This booking is not eligible for the selected discount.',
        };
    }
}
