<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentReceiptMail;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use App\Services\BookingProgressSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();
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

        $preview = $this->actingAs($admin)
            ->getJson(route('admin.booking-details.open-time-checkout-preview', $detail))
            ->assertOk()
            ->assertJsonPath('actual_duration', '55 min')
            ->assertJsonPath('billed_duration', '2 hrs')
            ->assertJsonPath('approved_before_label', 'Php 0.00');

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.end', $detail), [
                'payment_method' => 'cash',
                'payment_notes' => 'OR-OPEN-001',
                'previewed_amount_due' => $preview->json('amount_due'),
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
        Mail::assertSent(BookingPaymentReceiptMail::class, fn (BookingPaymentReceiptMail $mail): bool =>
            $mail->hasTo('checkout-open@example.com'));
    }

    public function test_digital_open_time_checkout_requires_a_transaction_reference(): void
    {
        Carbon::setTestNow('2026-08-03 10:05:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'open_time',
            'full_name' => 'Digital Checkout Customer',
            'email' => 'digital-checkout@example.com',
            'phone' => '09170000005',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-03',
            'guests' => 2,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ])->assertRedirect(route('admin.bookings.index'));

        $detail = BookingHeader::query()->where('email', 'digital-checkout@example.com')->firstOrFail()->details()->firstOrFail();

        $preview = $this->actingAs($admin)
            ->getJson(route('admin.booking-details.open-time-checkout-preview', $detail));

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.end', $detail), [
                'payment_method' => 'gcash',
                'previewed_amount_due' => $preview->json('amount_due'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_notes');

        $this->assertNull($detail->fresh()->actual_end_at);
    }

    public function test_auto_ended_open_time_session_can_still_be_checked_out(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-08-03 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'open_time',
            'full_name' => 'Late Checkout Customer',
            'email' => 'late-checkout@example.com',
            'phone' => '09170000006',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-03',
            'guests' => 2,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ])->assertRedirect(route('admin.bookings.index'));

        $header = BookingHeader::query()->where('email', 'late-checkout@example.com')->firstOrFail();
        $detail = $header->details()->firstOrFail();
        $detail->update(['end_time' => '11:00:00', 'booking_end_date' => '2026-08-03']);
        Carbon::setTestNow('2026-08-03 11:31:00');
        app(BookingProgressSyncService::class)->sync($admin->id);

        $this->assertNotNull($detail->fresh()->actual_end_at);

        $this->actingAs($admin)
            ->getJson(route('admin.bookings.summary', $header))
            ->assertOk()
            ->assertJsonPath('booking.bookings.0.can_end', true);

        $preview = $this->actingAs($admin)
            ->getJson(route('admin.booking-details.open-time-checkout-preview', $detail));

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.end', $detail), [
                'payment_method' => 'cash',
                'payment_notes' => 'Late checkout collection',
                'previewed_amount_due' => $preview->json('amount_due'),
            ])
            ->assertOk();

        $this->assertSame('paid', $header->fresh()->payment_status);
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
