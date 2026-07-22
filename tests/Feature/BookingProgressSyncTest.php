<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingProgressSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_command_starts_and_completes_due_confirmed_bookings(): void
    {
        Carbon::setTestNow('2026-07-16 10:30:00');

        try {
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-PROGRESS-0001',
                'customer_name' => 'Progress Test Customer',
                'email' => 'progress@example.com',
                'phone' => '+639171234567',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'gcash',
                'total_amount' => 2000,
                'status' => 'confirmed',
            ]);
            $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
            $space = Space::query()->where('slug', 'zeal-room-8-seats')->firstOrFail();

            $inProgress = $this->createDetail($header, $space, $room, '10:00', '11:00');
            $completed = $this->createDetail($header, $space, $room, '08:00', '09:00');

            $this->artisan('bookings:sync-progress')->assertSuccessful();

            $this->assertSame(BookingDetail::PROGRESS_IN_PROGRESS, $inProgress->fresh()->progress_status);
            $this->assertNotNull($inProgress->fresh()->actual_start_at);
            $this->assertNull($inProgress->fresh()->actual_end_at);

            $this->assertSame(BookingDetail::PROGRESS_COMPLETED, $completed->fresh()->progress_status);
            $this->assertNotNull($completed->fresh()->actual_start_at);
            $this->assertNotNull($completed->fresh()->actual_end_at);

            $this->assertDatabaseHas('booking_activities', [
                'booking_detail_id' => $inProgress->getKey(),
                'event_key' => 'booking_auto_started',
            ]);
            $this->assertDatabaseHas('booking_activities', [
                'booking_detail_id' => $completed->getKey(),
                'event_key' => 'booking_auto_completed',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_overnight_booking_remains_in_progress_until_its_next_day_end_time(): void
    {
        Carbon::setTestNow('2026-07-17 01:00:00');

        try {
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-PROGRESS-NIGHT',
                'customer_name' => 'Overnight Customer',
                'email' => 'overnight-progress@example.com',
                'phone' => '+639171234567',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'gcash',
                'total_amount' => 2000,
                'status' => 'confirmed',
            ]);
            $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
            $space = Space::query()->where('slug', 'zeal-room-8-seats')->firstOrFail();
            $detail = BookingDetail::query()->create([
                'booking_header_id' => $header->getKey(),
                'space_id' => $space->getKey(),
                'hyve_room_id' => $room->getKey(),
                'booking_date' => '2026-07-16',
                'booking_end_date' => '2026-07-17',
                'start_time' => '23:00',
                'end_time' => '03:00',
                'charge_period' => 'hourly',
                'guests' => 2,
                'subtotal' => 1000,
                'status' => BookingDetail::STATUS_CONFIRMED,
                'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
            ]);

            $this->artisan('bookings:sync-progress')->assertSuccessful();

            $detail->refresh();
            $this->assertSame(BookingDetail::PROGRESS_IN_PROGRESS, $detail->progress_status);
            $this->assertNull($detail->actual_end_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createDetail(
        BookingHeader $header,
        Space $space,
        HyveRoom $room,
        string $startTime,
        string $endTime
    ): BookingDetail {
        return BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $space->getKey(),
            'hyve_room_id' => $room->getKey(),
            'booking_date' => '2026-07-16',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'guests' => 2,
            'subtotal' => 1000,
            'status' => BookingDetail::STATUS_CONFIRMED,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
        ]);
    }
}
