<?php

namespace Tests\Feature;

use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use App\Services\HyveDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_hyve_discount_rules_calculate_only_eligible_booking_lines(): void
    {
        $header = $this->header();
        $this->line($header, 'common-area', 1000, 'day', '04:00');
        $this->line($header, 'fortitude-office-2-seats', 1000, 'weekly', '08:00');
        $this->line($header, 'tenacity-office-4-seats', 1000, 'monthly', '08:00');
        $this->line($header, 'zeal-room-8-seats', 1000, 'day', '10:30');
        $service = app(HyveDiscountService::class);

        $this->assertSame(800.0, $service->calculate($header, HyveDiscountService::SENIOR)['discount_amount']);
        $this->assertSame(800.0, $service->calculate($header, HyveDiscountService::PWD)['discount_amount']);
        $this->assertSame(190.0, $service->calculate($header, HyveDiscountService::VOUCHER_COMMON_2H)['discount_amount']);
        $this->assertSame(600.0, $service->calculate($header, HyveDiscountService::ENGAGEMENT)['discount_amount']);
        $this->assertSame(450.0, $service->calculate($header, HyveDiscountService::BOARD_REVIEWEE)['discount_amount']);
        $this->assertSame(100.0, $service->calculate($header, HyveDiscountService::EARLY_BIRD)['discount_amount']);
    }

    public function test_discount_is_persisted_recalculates_balance_and_creates_audit_activity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = $this->header();
        $this->line($header, 'common-area', 1000, 'day', '08:00');

        $this->actingAs($admin)
            ->post(route('admin.payments.discount', $header), [
                'discount_code' => HyveDiscountService::BOARD_REVIEWEE,
                'discount_reference' => 'REVIEWEE-ID-001',
            ])
            ->assertSessionHas('admin_success');

        $header->refresh();
        $this->assertSame(HyveDiscountService::BOARD_REVIEWEE, $header->discount_code);
        $this->assertSame(200.0, (float) $header->discount_amount);
        $this->assertSame(800.0, (float) $header->discounted_total_amount);
        $this->assertSame(800.0, (float) $header->balance_amount);
        $this->assertTrue(BookingActivity::query()
            ->where('booking_header_id', $header->id)
            ->where('event_key', 'booking_discount_updated')
            ->where('actor_user_id', $admin->id)
            ->exists());
    }

    public function test_ineligible_and_fully_paid_discount_changes_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = $this->header();
        $this->line($header, 'zeal-room-8-seats', 1000, 'day', '11:00');

        $this->actingAs($admin)
            ->from(route('admin.sections.payments'))
            ->post(route('admin.payments.discount', $header), [
                'discount_code' => HyveDiscountService::BOARD_REVIEWEE,
                'discount_reference' => 'REVIEWEE-ID-002',
            ])
            ->assertSessionHasErrors('discount_code');

        $header->update(['payment_status' => 'paid', 'balance_amount' => 0]);

        $this->actingAs($admin)
            ->from(route('admin.sections.payments'))
            ->post(route('admin.payments.discount', $header), [
                'discount_code' => HyveDiscountService::SENIOR,
                'discount_reference' => 'SENIOR-ID-001',
            ])
            ->assertSessionHasErrors('discount_code');
    }

    public function test_manual_digital_payment_requires_reference_and_records_audit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = $this->header();
        $this->line($header, 'common-area', 1000, 'day', '08:00');

        $payload = [
            'payment_submission_token' => (string) Str::uuid(),
            'amount' => 500,
            'payment_method' => 'gcash',
            'discount_code' => HyveDiscountService::NONE,
        ];

        $this->actingAs($admin)
            ->post(route('admin.payments.record', $header), $payload)
            ->assertSessionHasErrors('notes');

        $payload['notes'] = 'GCASH-REF-001';
        $this->actingAs($admin)
            ->post(route('admin.payments.record', $header), $payload)
            ->assertSessionHas('admin_success');

        $payment = BookingPayment::query()->where('booking_header_id', $header->id)->firstOrFail();
        $this->assertSame(BookingPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame('GCASH-REF-001', $payment->notes);
        $this->assertTrue(BookingActivity::query()
            ->where('booking_header_id', $header->id)
            ->where('event_key', 'payment_recorded_by_admin')
            ->exists());
    }

    public function test_two_hour_voucher_can_record_zero_payment_when_it_fully_covers_booking(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = $this->header();
        $this->line($header, 'common-area', 160, 'hourly', '08:00');

        $this->actingAs($admin)
            ->post(route('admin.payments.record', $header), [
                'payment_submission_token' => (string) Str::uuid(),
                'amount' => 0,
                'payment_method' => 'cash',
                'discount_code' => HyveDiscountService::VOUCHER_COMMON_2H,
                'discount_reference' => 'FREE-2H-001',
            ])
            ->assertSessionHas('admin_success');

        $header->refresh();
        $payment = BookingPayment::query()->where('booking_header_id', $header->id)->firstOrFail();

        $this->assertSame(0.0, (float) $payment->amount);
        $this->assertSame(BookingPayment::STATUS_APPROVED, $payment->status);
        $this->assertSame(HyveDiscountService::VOUCHER_COMMON_2H, $header->discount_code);
        $this->assertSame(0.0, (float) $header->discounted_total_amount);
        $this->assertSame(0.0, (float) $header->balance_amount);
        $this->assertSame('paid', $header->payment_status);
    }

    public function test_zero_payment_is_rejected_when_discount_does_not_cover_balance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = $this->header();
        $this->line($header, 'common-area', 1000, 'day', '08:00');

        $this->actingAs($admin)
            ->post(route('admin.payments.record', $header), [
                'payment_submission_token' => (string) Str::uuid(),
                'amount' => 0,
                'payment_method' => 'cash',
                'discount_code' => HyveDiscountService::NONE,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertFalse(BookingPayment::query()->where('booking_header_id', $header->id)->exists());
    }

    private function header(): BookingHeader
    {
        return BookingHeader::query()->create([
            'reference_no' => 'HYVE-DISCOUNT-'.Str::upper(Str::random(8)),
            'customer_name' => 'Discount Customer',
            'email' => 'discount-'.Str::random(6).'@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_ADMIN,
            'payment_method' => 'cash',
            'payment_status' => 'pending_verification',
            'total_amount' => 0,
            'discounted_total_amount' => 0,
            'downpayment_amount' => 0,
            'balance_amount' => 0,
            'status' => 'confirmed',
        ]);
    }

    private function line(BookingHeader $header, string $spaceSlug, float $subtotal, string $period, string $start): BookingDetail
    {
        $space = Space::query()->where('slug', $spaceSlug)->firstOrFail();
        $roomName = match ($spaceSlug) {
            'common-area' => 'Table 1-A',
            'fortitude-office-2-seats' => 'Room 1',
            'tenacity-office-4-seats' => 'Room 7',
            default => 'Conference Room',
        };
        $room = HyveRoom::query()->where('room_name', $roomName)->firstOrFail();
        $detail = $header->details()->create([
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-10',
            'booking_end_date' => '2026-08-10',
            'start_time' => $start,
            'end_time' => '12:00',
            'charge_period' => $period,
            'duration_hours' => 2,
            'billed_hours' => 2,
            'guests' => 1,
            'rate_name' => 'Discount test',
            'rate_amount' => $subtotal,
            'subtotal' => $subtotal,
            'status' => BookingDetail::STATUS_CONFIRMED,
            'progress_status' => BookingDetail::PROGRESS_SCHEDULED,
        ]);

        $gross = round((float) $header->details()->sum('subtotal'), 2);
        $header->update([
            'total_amount' => $gross,
            'discounted_total_amount' => $gross,
            'balance_amount' => $gross,
        ]);

        return $detail;
    }
}
