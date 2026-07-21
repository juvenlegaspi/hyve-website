<?php

namespace Tests\Feature;

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

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_uses_active_booking_ranges_and_verified_payments(): void
    {
        Carbon::setTestNow('2026-07-21 12:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $maintenanceRoom = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();
        $space = Space::query()->where('slug', $room->mappedSpaceSlug())->firstOrFail();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-DASHBOARD-001',
            'customer_name' => 'Dashboard Customer',
            'email' => 'dashboard@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_MONTHLY,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => 'confirmed',
            'total_amount' => 3000,
            'downpayment_amount' => 500,
            'balance_amount' => 2500,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $space->getKey(),
            'hyve_room_id' => $room->getKey(),
            'booking_date' => now()->subDay()->toDateString(),
            'booking_end_date' => now()->addDay()->toDateString(),
            'start_time' => '00:00',
            'end_time' => '23:59',
            'duration_hours' => 72,
            'subtotal' => 3000,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $space->getKey(),
            'hyve_room_id' => $maintenanceRoom->getKey(),
            'booking_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'subtotal' => 9999,
            'status' => BookingDetail::STATUS_CANCELLED,
        ]);

        BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
            'amount' => 500,
            'payment_method' => 'gcash',
            'status' => BookingPayment::STATUS_APPROVED,
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $admin->getKey(),
        ]);

        BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'payment_type' => BookingPayment::TYPE_BALANCE,
            'amount' => 2500,
            'payment_method' => 'gcash',
            'status' => BookingPayment::STATUS_PENDING,
            'paid_at' => now(),
        ]);

        HyveScheduleOverride::query()->create([
            'hyve_room_id' => $maintenanceRoom->getKey(),
            'booking_date' => now()->toDateString(),
            'mode' => HyveScheduleOverride::MODE_CLOSED,
            'reason' => 'Aircon maintenance',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('bookingsThisMonth', 1)
            ->assertViewHas('revenueThisMonth', 500.0)
            ->assertViewHas('verifiedThisMonth', 1)
            ->assertViewHas('pendingThisMonth', 1)
            ->assertViewHas('bookedHoursThisMonth', 72.0)
            ->assertViewHas('utilization', fn ($utilization): bool => (float) $utilization > 0)
            ->assertViewHas('roomStatus', function ($statuses) use ($room, $maintenanceRoom): bool {
                $byRoom = collect($statuses)->keyBy('room_name');

                return $byRoom->get($room->room_name)['status'] === 'occupied'
                    && $byRoom->get($maintenanceRoom->room_name)['status'] === 'maintenance';
            });
    }
}
