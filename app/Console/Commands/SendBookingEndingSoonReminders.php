<?php

namespace App\Console\Commands;

use App\Models\BookingDetail;
use App\Services\BookingEndingSoonTextService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBookingEndingSoonReminders extends Command
{
    protected $signature = 'bookings:send-ending-reminders';

    protected $description = 'Send one SMS reminder five minutes before an active booking ends';

    public function handle(BookingEndingSoonTextService $textService): int
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(5);
        $sent = 0;

        BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->whereNull('actual_end_at')
            ->whereNull('end_reminder_sent_at')
            ->where(function ($query) use ($now): void {
                $query->whereDate('booking_date', $now->toDateString())
                    ->orWhereDate('booking_date', $now->copy()->subDay()->toDateString())
                    ->orWhereDate('booking_end_date', $now->toDateString());
            })
            ->get()
            ->each(function (BookingDetail $detail) use ($now, $windowEnd, $textService, &$sent): void {
                $scheduledStart = $this->scheduledStart($detail);
                $scheduledEnd = $this->scheduledEnd($detail);

                if ($scheduledStart->gt($now)
                    || ! $scheduledEnd->gt($now)
                    || $scheduledEnd->gt($windowEnd)
                    || $this->hasLaterSessionDetail($detail, $scheduledEnd)) {
                    return;
                }

                if ($textService->send($detail, $scheduledEnd)) {
                    $detail->forceFill(['end_reminder_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} booking end reminder(s).");

        return self::SUCCESS;
    }

    private function scheduledStart(BookingDetail $detail): Carbon
    {
        return Carbon::parse($detail->booking_date->toDateString().' '.$detail->start_time);
    }

    private function scheduledEnd(BookingDetail $detail): Carbon
    {
        $endDate = ($detail->booking_end_date ?: $detail->booking_date)->toDateString();
        $start = $this->scheduledStart($detail);
        $end = Carbon::parse($endDate.' '.$detail->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }

    private function hasLaterSessionDetail(BookingDetail $detail, Carbon $scheduledEnd): bool
    {
        if (! $detail->booking_header_id || ! $detail->hyve_room_id) {
            return false;
        }

        return BookingDetail::query()
            ->where('booking_header_id', $detail->booking_header_id)
            ->where('hyve_room_id', $detail->hyve_room_id)
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->whereKeyNot($detail->getKey())
            ->get()
            ->contains(function (BookingDetail $otherDetail) use ($scheduledEnd): bool {
                $otherStart = Carbon::parse($otherDetail->booking_date->toDateString().' '.$otherDetail->start_time);

                return $otherStart->lessThanOrEqualTo($scheduledEnd)
                    && $this->scheduledEnd($otherDetail)->greaterThan($scheduledEnd);
            });
    }
}
