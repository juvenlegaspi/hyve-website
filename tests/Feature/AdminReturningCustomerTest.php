<?php

namespace Tests\Feature;

use App\Models\BookingHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturningCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_select_a_previous_walk_in_and_latest_booking_is_identified(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $oldBooking = $this->createBooking('HYVE-RETURN-OLD', '0917 123 4567', BookingHeader::SOURCE_WEB);
        $oldBooking->forceFill([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ])->save();
        $latestBooking = $this->createBooking('HYVE-RETURN-NEW', '+63 917 123 4567', BookingHeader::SOURCE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.bookings.create'))
            ->assertOk()
            ->assertSee('Returning customer')
            ->assertSee('data-returning-customer-search', false)
            ->assertSee('data-returning-customer-results', false)
            ->assertSee('data-returning-customer-option', false)
            ->assertSee('Charles Returning')
            ->assertSee('2 previous bookings');

        $response = $this->actingAs($admin)->getJson(route('admin.bookings.feed'));

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('bookings.0.id', $latestBooking->id)
            ->assertJsonPath('bookings.0.is_returning', true)
            ->assertJsonPath('bookings.0.is_new', true)
            ->assertJsonPath('bookings.0.source_label', 'Walk-in')
            ->assertJsonPath('bookings.1.id', $oldBooking->id)
            ->assertJsonPath('bookings.1.is_returning', true)
            ->assertJsonPath('bookings.1.is_new', false)
            ->assertJsonPath('bookings.1.source_label', 'Online');
    }

    public function test_payment_listing_identifies_online_walk_in_and_new_bookings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $onlineBooking = $this->createBooking('HYVE-PAY-ONLINE', '09171111111', BookingHeader::SOURCE_WEB);
        $walkInBooking = $this->createBooking('HYVE-PAY-WALKIN', '09172222222', BookingHeader::SOURCE_ADMIN);
        $onlineBooking->update(['status' => 'confirmed']);
        $walkInBooking->update(['status' => 'confirmed']);

        $this->actingAs($admin)
            ->get(route('admin.sections.payments'))
            ->assertOk()
            ->assertSee('Online')
            ->assertSee('Walk-in')
            ->assertSee('New booking')
            ->assertSee('data-new-payment-booking-badge', false);
    }

    private function createBooking(string $reference, string $phone, string $source): BookingHeader
    {
        return BookingHeader::query()->create([
            'reference_no' => $reference,
            'customer_name' => 'Charles Returning',
            'email' => 'charles@example.com',
            'phone' => $phone,
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => $source,
            'payment_method' => 'cash',
            'payment_status' => 'pending_verification',
            'total_amount' => 500,
            'downpayment_amount' => 0,
            'balance_amount' => 500,
            'status' => BookingHeader::STATUS_PENDING,
        ]);
    }
}
