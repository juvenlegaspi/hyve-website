<?php

namespace Tests\Feature;

use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\HyveScheduleOverride;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminBookingExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_choose_a_priced_available_extension_and_confirm_it(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$header, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');

        BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'booking_detail_id' => $detail->getKey(),
            'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
            'amount' => 500,
            'payment_method' => 'gcash',
            'status' => BookingPayment::STATUS_APPROVED,
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $admin->getKey(),
        ]);

        $options = $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk()
            ->assertJsonPath('current_end', 'Aug 12, 2026 12:00 PM')
            ->assertJsonPath('options.0.duration_minutes', 30)
            ->assertJsonPath('options.0.end_at', '2026-08-12 12:30');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.booking-details.extend', $detail), [
                'extension_end_at' => $options->json('options.0.end_at'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Booking extended successfully.')
            ->assertJsonPath('booking.bookings.0.can_start', false)
            ->assertJsonPath('booking.bookings.0.can_reschedule', false)
            ->assertJsonPath('booking.bookings.1.can_start', true)
            ->assertJsonPath('booking.bookings.1.can_extend', true)
            ->assertJsonPath('booking.bookings.1.can_reschedule', true);

        $extension = BookingDetail::query()
            ->where('booking_header_id', $header->getKey())
            ->whereKeyNot($detail->getKey())
            ->firstOrFail();

        $this->assertSame('12:00', substr((string) $extension->start_time, 0, 5));
        $this->assertSame('12:30', substr((string) $extension->end_time, 0, 5));
        $this->assertSame(BookingDetail::STATUS_CONFIRMED, $extension->status);
        $this->assertStringEndsWith(
            '/admin/booking-details/'.$detail->getKey().'/start',
            (string) $response->json('booking.bookings.1.start_url'),
        );
        $this->assertGreaterThan(1000, (float) $header->fresh()->total_amount);
        $this->assertTrue(BookingActivity::query()
            ->where('booking_detail_id', $extension->getKey())
            ->where('event_key', 'booking_line_extended')
            ->exists());
    }

    public function test_extension_options_stop_before_another_booking_and_confirmation_rechecks_conflicts(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$header, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');
        [, $nextDetail] = $this->createConfirmedBooking('2026-08-12', '12:30', '14:00', $detail->hyve_room_id);

        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk()
            ->assertJsonCount(1, 'options')
            ->assertJsonPath('options.0.end_at', '2026-08-12 12:30');

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.extend', $detail), [
                'extension_end_at' => '2026-08-12 13:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extension_end_at');

        $this->assertSame(1, BookingDetail::query()
            ->where('booking_header_id', $header->getKey())
            ->count());
        $this->assertDatabaseHas('booking_details', ['id' => $nextDetail->getKey()]);
    }

    public function test_extension_options_respect_room_schedule_closing_time(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');

        HyveScheduleOverride::query()->create([
            'hyve_room_id' => $detail->hyve_room_id,
            'booking_date' => '2026-08-12',
            'mode' => HyveScheduleOverride::MODE_CUSTOM,
            'opening_time' => '08:00',
            'closing_time' => '12:30',
            'reason' => 'Early room closure',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk()
            ->assertJsonCount(1, 'options')
            ->assertJsonPath('options.0.end_at', '2026-08-12 12:30');

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.extend', $detail), [
                'extension_end_at' => '2026-08-12 13:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extension_end_at');
    }

    public function test_extend_remains_available_until_thirty_minutes_after_end_time(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');

        Carbon::setTestNow('2026-08-12 12:30:00');

        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk()
            ->assertJsonPath('options.0.end_at', '2026-08-12 12:30');

        Carbon::setTestNow('2026-08-12 12:30:01');

        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booked line cannot be extended right now.');
    }

    public function test_open_time_booking_cannot_be_extended(): void
    {
        Carbon::setTestNow('2026-08-12 11:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '22:00');
        $detail->update(['is_open_time' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booked line cannot be extended right now.');

        $this->actingAs($admin)
            ->getJson(route('admin.bookings.summary', $detail->booking_header_id))
            ->assertOk()
            ->assertJsonPath('booking.bookings.0.can_extend', false);
    }

    public function test_ended_booking_can_still_be_extended_within_twenty_four_hours(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$header, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');
        $detail->update([
            'progress_status' => BookingDetail::PROGRESS_COMPLETED,
            'actual_start_at' => '2026-08-12 10:00:00',
            'actual_end_at' => '2026-08-12 12:05:00',
        ]);
        Carbon::setTestNow('2026-08-13 12:04:00');

        $options = $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk()
            ->assertJsonPath('options.0.end_at', '2026-08-12 12:30');

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.extend', $detail), [
                'extension_end_at' => $options->json('options.0.end_at'),
            ])
            ->assertOk()
            ->assertJsonPath('booking.bookings.1.progress_key', BookingDetail::PROGRESS_COMPLETED)
            ->assertJsonPath('booking.bookings.1.can_extend', true);

        $extension = BookingDetail::query()
            ->where('booking_header_id', $header->id)
            ->whereKeyNot($detail->id)
            ->firstOrFail();
        $this->assertSame('2026-08-12 12:00:00', $extension->actual_start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 12:30:00', $extension->actual_end_at?->format('Y-m-d H:i:s'));
    }

    public function test_ended_booking_extension_expires_after_twenty_four_hours(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createConfirmedBooking('2026-08-12', '10:00', '12:00');
        $detail->update([
            'progress_status' => BookingDetail::PROGRESS_COMPLETED,
            'actual_start_at' => '2026-08-12 10:00:00',
            'actual_end_at' => '2026-08-12 12:00:00',
        ]);

        Carbon::setTestNow('2026-08-13 12:00:00');
        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertOk();

        Carbon::setTestNow('2026-08-13 12:00:01');
        $this->actingAs($admin)
            ->getJson(route('admin.booking-details.extension-options', $detail))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booked line cannot be extended right now.');
    }

    /** @return array{0: BookingHeader, 1: BookingDetail} */
    private function createConfirmedBooking(
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $roomId = null,
    ): array {
        $room = $roomId
            ? HyveRoom::query()->findOrFail($roomId)
            : HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $space = Space::query()->where('slug', $room->mappedSpaceSlug())->firstOrFail();
        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-EXT-'.str()->upper(str()->random(8)),
            'customer_name' => 'Extension Customer',
            'email' => 'extension@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'payment_status' => 'partially_paid',
            'total_amount' => 1000,
            'discounted_total_amount' => 1000,
            'downpayment_amount' => 0,
            'balance_amount' => 1000,
            'status' => 'confirmed',
        ]);
        $detail = BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $space->getKey(),
            'hyve_room_id' => $room->getKey(),
            'booking_date' => $bookingDate,
            'booking_end_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'charge_period' => 'day',
            'duration_hours' => 2,
            'billed_hours' => 2,
            'guests' => 2,
            'rate_name' => 'Test booking',
            'rate_amount' => 1000,
            'subtotal' => 1000,
            'status' => BookingDetail::STATUS_CONFIRMED,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
        ]);

        return [$header, $detail];
    }
}
