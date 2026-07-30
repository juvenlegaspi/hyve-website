<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminOpenTimeBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_open_time_starts_immediately_and_stops_at_the_next_booking(): void
    {
        Carbon::setTestNow('2026-08-03 10:05:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $space = Space::query()->where('slug', $room->mappedSpaceSlug())->firstOrFail();
        $blockedHeader = BookingHeader::query()->create([
            'reference_no' => 'HYVE-NEXT-BOOKING',
            'customer_name' => 'Next Customer',
            'email' => 'next@example.com',
            'phone' => '09170000001',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => 'confirmed',
        ]);
        $blockedHeader->details()->create([
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-03',
            'booking_end_date' => '2026-08-03',
            'start_time' => '15:00',
            'end_time' => '17:00',
            'charge_period' => 'day',
            'duration_hours' => 2,
            'billed_hours' => 2,
            'guests' => 4,
            'rate_name' => 'Existing',
            'rate_amount' => 0,
            'subtotal' => 100,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bookings.store'), [
                'booking_mode' => 'open_time',
                'full_name' => 'Open Time Customer',
                'email' => 'open-time@example.com',
                'phone' => '09170000002',
                'hyve_room_id' => $room->id,
                'booking_date' => '2026-08-03',
                'guests' => 3,
                'downpayment_amount' => 0,
                'payment_method' => 'pay_later',
            ])
            ->assertRedirect(route('admin.bookings.index'))
            ->assertSessionHas('admin_success');

        $header = BookingHeader::query()->where('email', 'open-time@example.com')->firstOrFail();
        $detail = $header->details()->firstOrFail();

        $this->assertSame(BookingHeader::SOURCE_ADMIN, $header->source);
        $this->assertSame('confirmed', $header->status);
        $this->assertSame(BookingDetail::STATUS_CONFIRMED, $detail->status);
        $this->assertSame(BookingDetail::PROGRESS_IN_PROGRESS, $detail->progress_status);
        $this->assertTrue($detail->is_open_time);
        $this->assertSame('2026-08-03 10:05:00', $detail->actual_start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('15:00', substr((string) $detail->end_time, 0, 5));
        $this->assertSame('pay_later', $header->payment_method);
        $this->assertSame('pending_verification', $header->payment_status);
    }

    public function test_open_time_checkout_applies_minimum_charge_and_records_approved_payment(): void
    {
        Carbon::setTestNow('2026-08-03 10:05:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'open_time',
            'full_name' => 'Checkout Customer',
            'email' => 'checkout-open@example.com',
            'phone' => '09170000003',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-03',
            'guests' => 2,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ])->assertRedirect(route('admin.bookings.index'));

        $header = BookingHeader::query()->where('email', 'checkout-open@example.com')->firstOrFail();
        $detail = $header->details()->firstOrFail();
        Carbon::setTestNow('2026-08-03 11:00:00');

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.end', $detail), [
                'payment_method' => 'cash',
                'payment_notes' => 'OR-OPEN-001',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Open Time session ended and payment recorded successfully.');

        $detail->refresh();
        $header->refresh();
        $payment = BookingPayment::query()->where('booking_header_id', $header->id)->firstOrFail();

        $this->assertSame(BookingDetail::PROGRESS_COMPLETED, $detail->progress_status);
        $this->assertSame('2.00', (string) $detail->billed_hours);
        $this->assertSame('paid', $header->payment_status);
        $this->assertSame('cash', $header->payment_method);
        $this->assertSame(0.0, (float) $header->balance_amount);
        $this->assertSame((float) $detail->subtotal, (float) $payment->amount);
        $this->assertSame(BookingPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame('OR-OPEN-001', $payment->notes);
    }

    public function test_online_customer_cannot_submit_open_time_mode(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->post(route('bookings.store'), [
            'booking_mode' => 'open_time',
            'full_name' => 'Online Open Time',
            'email' => 'online-open@example.com',
            'phone' => '09170000004',
            'hyve_room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'guests' => 2,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
            'rules_agreement' => '1',
        ])->assertSessionHasErrors('booking_mode');

        $this->assertDatabaseMissing('booking_headers', ['email' => 'online-open@example.com']);
    }
}
