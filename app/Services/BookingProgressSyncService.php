<?php

namespace App\Services;

use App\Models\BookingActivity;
use App\Models\BookingDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class BookingProgressSyncService
{
    public function sync(?int $actorUserId = null): int
    {
        $now = now();
        $updated = 0;

        BookingDetail::query()
            ->with(['bookingHeader', 'hyveRoom', 'space'])
            ->where('status', BookingDetail::STATUS_CONFIRMED)
            ->whereNull('actual_end_at')
            ->whereDate('booking_date', '<=', $now->toDateString())
            ->chunkById(100, function ($details) use ($actorUserId, $now, &$updated): void {
                foreach ($details as $detail) {
                    $scheduledStart = $this->scheduledDateTime($detail, (string) $detail->start_time);
                    $scheduledEnd = $this->scheduledDateTime($detail, (string) $detail->end_time);
                    $header = $detail->bookingHeader;

                    if (! $detail->actual_start_at && $scheduledStart->lte($now) && $scheduledEnd->gt($now)) {
                        $detail->update([
                            'progress_status' => BookingDetail::PROGRESS_IN_PROGRESS,
                            'actual_start_at' => $scheduledStart,
                            'actual_end_at' => null,
                        ]);
                        $updated++;

                        if ($header) {
                            $this->recordActivity(
                                $detail,
                                $actorUserId,
                                'booking_auto_started',
                                'Booking auto-started',
                                'Auto-started '.$this->roomName($detail).' for '.$header->customer_name.'.'
                            );
                        }
                    }

                    if ($scheduledEnd->lte($now)) {
                        $detail->update([
                            'progress_status' => BookingDetail::PROGRESS_COMPLETED,
                            'actual_start_at' => $detail->actual_start_at ?: $scheduledStart,
                            'actual_end_at' => $scheduledEnd,
                        ]);
                        $updated++;

                        if ($header) {
                            $this->recordActivity(
                                $detail,
                                $actorUserId,
                                'booking_auto_completed',
                                'Booking auto-ended',
                                'Auto-ended '.$this->roomName($detail).' for '.$header->customer_name.'.'
                            );
                        }
                    }
                }
            });

        return $updated;
    }

    private function scheduledDateTime(BookingDetail $detail, string $time): Carbon
    {
        return Carbon::parse(optional($detail->booking_date)->format('Y-m-d').' '.$time);
    }

    private function recordActivity(
        BookingDetail $detail,
        ?int $actorUserId,
        string $eventKey,
        string $eventLabel,
        string $message
    ): void {
        if (! Schema::hasTable('booking_activities') || ! $detail->bookingHeader) {
            return;
        }

        $header = $detail->bookingHeader;

        BookingActivity::query()->create([
            'booking_header_id' => $header->getKey(),
            'booking_detail_id' => $detail->getKey(),
            'actor_user_id' => $actorUserId,
            'event_key' => $eventKey,
            'event_label' => $eventLabel,
            'reference_no' => $header->reference_no,
            'customer_name' => $header->customer_name,
            'room_name' => $this->roomName($detail),
            'booking_date' => $detail->booking_date,
            'time_range' => $this->timeRange($detail),
            'message' => $message,
        ]);
    }

    private function roomName(BookingDetail $detail): string
    {
        return $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room';
    }

    private function timeRange(BookingDetail $detail): string
    {
        return Carbon::parse((string) $detail->start_time)->format('g:i A')
            .' - '
            .Carbon::parse((string) $detail->end_time)->format('g:i A');
    }
}
