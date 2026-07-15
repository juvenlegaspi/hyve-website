<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingEndingSoonReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_one_sms_during_the_five_minute_end_window(): void
    {
        Carbon::setTestNow('2026-07-15 10:55:00');

        try {
            Http::fake([
                'https://api.semaphore.co/api/v4/messages' => Http::response([
                    ['message_id' => 98765, 'status' => 'Pending'],
                ]),
            ]);

            config()->set('services.semaphore.api_key', 'test-api-key');
            config()->set('services.semaphore.sender_name', 'HYVE');
            config()->set('services.semaphore.ca_bundle');

            $detail = $this->createActiveBookingEndingAt('11:00');

            $this->artisan('bookings:send-ending-reminders')
                ->expectsOutput('Sent 1 booking end reminder(s).')
                ->assertSuccessful();

            $this->assertNotNull($detail->fresh()->end_reminder_sent_at);

            Http::assertSent(function (Request $request): bool {
                return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                    && $request['number'] === '639086343827'
                    && str_contains((string) $request['message'], 'ends in 5 minutes at 11:00 AM')
                    && str_contains((string) $request['message'], 'HYVE-END-0001');
            });

            $this->artisan('bookings:send-ending-reminders')
                ->expectsOutput('Sent 0 booking end reminder(s).')
                ->assertSuccessful();

            Http::assertSentCount(1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_does_not_send_before_the_five_minute_window(): void
    {
        Carbon::setTestNow('2026-07-15 10:54:00');

        try {
            Http::fake();
            config()->set('services.semaphore.api_key', 'test-api-key');

            $detail = $this->createActiveBookingEndingAt('11:00');

            $this->artisan('bookings:send-ending-reminders')
                ->expectsOutput('Sent 0 booking end reminder(s).')
                ->assertSuccessful();

            Http::assertNothingSent();
            $this->assertNull($detail->fresh()->end_reminder_sent_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_uses_the_confirmed_schedule_even_when_the_admin_page_did_not_auto_start_the_booking(): void
    {
        Carbon::setTestNow('2026-07-15 10:55:00');

        try {
            Http::fake([
                'https://api.semaphore.co/api/v4/messages' => Http::response([
                    ['message_id' => 98766, 'status' => 'Pending'],
                ]),
            ]);
            config()->set('services.semaphore.api_key', 'test-api-key');

            $detail = $this->createActiveBookingEndingAt('11:00');
            $detail->forceFill(['actual_start_at' => null])->save();

            $this->artisan('bookings:send-ending-reminders')
                ->expectsOutput('Sent 1 booking end reminder(s).')
                ->assertSuccessful();

            Http::assertSentCount(1);
            $this->assertNotNull($detail->fresh()->end_reminder_sent_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createActiveBookingEndingAt(string $endTime): BookingDetail
    {
        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-END-0001',
            'customer_name' => 'Reminder Customer',
            'email' => 'reminder@example.com',
            'phone' => '0908-634-3827',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'status' => 'confirmed',
        ]);

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $spaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

        return BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $spaceId,
            'hyve_room_id' => $room->getKey(),
            'booking_date' => '2026-07-15',
            'start_time' => '09:00',
            'end_time' => $endTime,
            'guests' => 2,
            'subtotal' => 1598,
            'status' => BookingDetail::STATUS_CONFIRMED,
            'progress_status' => BookingDetail::PROGRESS_IN_PROGRESS,
            'actual_start_at' => '2026-07-15 09:00:00',
        ]);
    }
}
