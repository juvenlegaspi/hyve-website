<?php

namespace Tests\Feature;

use App\Mail\BookingRescheduledMail;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminBookingRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_open_reschedule_for_a_future_booking(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createBooking(now()->addDays(3)->toDateString());

        $this->actingAs($admin)
            ->get(route('admin.booking-details.reschedule', $detail))
            ->assertOk()
            ->assertSee('Reschedule booking')
            ->assertSee($detail->bookingHeader->reference_no);
    }

    public function test_reschedule_is_unavailable_once_the_scheduled_start_arrives(): void
    {
        Carbon::setTestNow('2026-08-10 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createBooking('2026-08-10', '10:00', '12:00');

        $this->actingAs($admin)
            ->get(route('admin.booking-details.reschedule', $detail))
            ->assertRedirect(route('admin.bookings.index'));

        $this->actingAs($admin)
            ->patch(route('admin.booking-details.reschedule.update', $detail), [
                'hyve_room_id' => $detail->hyve_room_id,
                'booking_date' => '2026-08-11',
                'start_time' => '10:00',
                'end_time' => '12:00',
            ])
            ->assertSessionHasErrors('booking_date');

        $this->assertSame('2026-08-10', $detail->fresh()->booking_date->toDateString());
    }

    public function test_admin_reschedule_preserves_reference_and_payments_and_notifies_customer(): void
    {
        Mail::fake();
        Http::fake();
        config()->set('services.semaphore.api_key', 'test-key');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$header, $detail] = $this->createBooking(now()->addDays(3)->toDateString());
        $newDate = now()->addDays(5)->toDateString();

        BookingPayment::query()->create([
            'booking_header_id' => $header->id,
            'booking_detail_id' => $detail->id,
            'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
            'amount' => 500,
            'payment_method' => 'gcash',
            'status' => BookingPayment::STATUS_APPROVED,
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.booking-details.reschedule.update', $detail), [
                'hyve_room_id' => $detail->hyve_room_id,
                'booking_date' => $newDate,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ])
            ->assertRedirect(route('admin.bookings.index'))
            ->assertSessionHas('admin_success');

        $freshDetail = $detail->fresh();
        $freshHeader = $header->fresh();

        $this->assertSame($newDate, $freshDetail->booking_date->toDateString());
        $this->assertSame('HYVE-RESCHEDULE-0001', $freshHeader->reference_no);
        $this->assertSame(500.0, (float) $freshHeader->downpayment_amount);
        $this->assertSame(1, $freshHeader->payments()->count());
        $this->assertDatabaseHas('booking_activities', [
            'booking_detail_id' => $detail->id,
            'actor_user_id' => $admin->id,
            'event_key' => 'booking_rescheduled_by_admin',
        ]);

        Mail::assertSent(BookingRescheduledMail::class, fn (BookingRescheduledMail $mail): bool =>
            $mail->hasTo('reschedule@example.com')
            && $mail->context['reference_no'] === 'HYVE-RESCHEDULE-0001'
            && $mail->context['new_schedule'] !== ''
        );
    }

    public function test_admin_cannot_reschedule_into_an_existing_booking(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createBooking(now()->addDays(3)->toDateString());
        $targetDate = now()->addDays(5)->toDateString();
        $this->createBooking($targetDate, '08:00', '10:00', 'HYVE-CONFLICT-0002');

        $this->actingAs($admin)
            ->from(route('admin.booking-details.reschedule', $detail))
            ->patch(route('admin.booking-details.reschedule.update', $detail), [
                'hyve_room_id' => $detail->hyve_room_id,
                'booking_date' => $targetDate,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ])
            ->assertRedirect(route('admin.booking-details.reschedule', $detail))
            ->assertSessionHasErrors('end_time');

        $this->assertNotSame($targetDate, $detail->fresh()->booking_date->toDateString());
    }

    public function test_slot_picker_excludes_the_current_line_and_disables_conflicting_times(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $currentDate = now()->addDays(3)->toDateString();
        [, $detail] = $this->createBooking($currentDate);

        $currentSlots = $this->actingAs($admin)
            ->postJson(route('admin.booking-details.reschedule.slots', $detail), [
                'hyve_room_id' => $detail->hyve_room_id,
                'booking_date' => $currentDate,
                'start_time' => '08:00',
            ])
            ->assertOk()
            ->json();

        $currentStart = collect($currentSlots['start_times'])->firstWhere('value', '08:00');
        $this->assertTrue($currentStart['available']);
        $this->assertTrue(collect($currentSlots['end_times'])->firstWhere('value', '10:00')['available']);

        $conflictDate = now()->addDays(5)->toDateString();
        $this->createBooking($conflictDate, '08:00', '10:00', 'HYVE-SLOT-CONFLICT');

        $conflictSlots = $this->actingAs($admin)
            ->postJson(route('admin.booking-details.reschedule.slots', $detail), [
                'hyve_room_id' => $detail->hyve_room_id,
                'booking_date' => $conflictDate,
            ])
            ->assertOk()
            ->json();

        $blockedStart = collect($conflictSlots['start_times'])->firstWhere('value', '08:00');
        $this->assertFalse($blockedStart['available']);
        $this->assertSame('Booked', $blockedStart['reason']);
        $this->assertTrue(collect($conflictSlots['start_times'])->firstWhere('value', '10:00')['available']);
    }

    public function test_price_change_requires_explicit_admin_confirmation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $detail] = $this->createBooking(now()->addDays(3)->toDateString());
        $newDate = now()->addDays(5)->toDateString();

        $payload = [
            'hyve_room_id' => $detail->hyve_room_id,
            'booking_date' => $newDate,
            'start_time' => '08:00',
            'end_time' => '11:00',
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.reschedule.preview', $detail), $payload)
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('requires_price_confirmation', true);

        $this->actingAs($admin)
            ->patch(route('admin.booking-details.reschedule.update', $detail), $payload)
            ->assertSessionHasErrors('confirm_price_change');

        $this->assertNotSame($newDate, $detail->fresh()->booking_date->toDateString());

        $this->actingAs($admin)
            ->patch(route('admin.booking-details.reschedule.update', $detail), [
                ...$payload,
                'confirm_price_change' => true,
            ])
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertSame($newDate, $detail->fresh()->booking_date->toDateString());
    }

    /** @return array{BookingHeader, BookingDetail} */
    private function createBooking(string $date, string $start = '08:00', string $end = '10:00', string $reference = 'HYVE-RESCHEDULE-0001'): array
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $space = Space::query()->where('slug', 'zeal-room-8-seats')->firstOrFail();
        $header = BookingHeader::query()->create([
            'reference_no' => $reference,
            'customer_name' => 'Reschedule Customer',
            'email' => 'reschedule@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'total_amount' => 1598,
            'discounted_total_amount' => 1598,
            'downpayment_amount' => 0,
            'balance_amount' => 1598,
            'payment_status' => 'pending_verification',
            'status' => BookingHeader::STATUS_PENDING,
        ]);
        $detail = BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'booking_date' => $date,
            'booking_end_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'charge_period' => 'day',
            'duration_hours' => 2,
            'billed_hours' => 2,
            'guests' => 4,
            'rate_name' => 'Zeal Room (8 Seats) - Day Use',
            'rate_amount' => 799,
            'subtotal' => 1598,
            'status' => BookingDetail::STATUS_PENDING,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
        ]);

        return [$header, $detail];
    }
}
