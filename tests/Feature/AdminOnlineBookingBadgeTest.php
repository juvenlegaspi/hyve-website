<?php

namespace Tests\Feature;

use App\Models\BookingActivity;
use App\Models\BookingHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOnlineBookingBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_counts_unique_unread_online_bookings_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $online = $this->booking('HYVE-ONLINE-1', BookingHeader::SOURCE_WEB);
        $walkIn = $this->booking('HYVE-WALKIN-1', BookingHeader::SOURCE_ADMIN);

        $this->activity($online);
        $latest = $this->activity($online);
        $this->activity($walkIn);

        $this->actingAs($admin)
            ->getJson(route('admin.bookings.online-unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('latest_booking.id', $latest->getKey())
            ->assertJsonPath('latest_booking.reference_no', 'HYVE-ONLINE-1');
    }

    public function test_opening_bookings_marks_online_booking_badge_as_read(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $online = $this->booking('HYVE-ONLINE-2', BookingHeader::SOURCE_WEB);
        $walkIn = $this->booking('HYVE-WALKIN-2', BookingHeader::SOURCE_ADMIN);
        $onlineActivity = $this->activity($online);
        $walkInActivity = $this->activity($walkIn);

        $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();

        $this->assertNotNull($onlineActivity->fresh()->read_at);
        $this->assertNull($walkInActivity->fresh()->read_at);

        $this->getJson(route('admin.bookings.online-unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);
    }

    private function booking(string $reference, string $source): BookingHeader
    {
        return BookingHeader::query()->create([
            'reference_no' => $reference,
            'customer_name' => $source === BookingHeader::SOURCE_WEB ? 'Online Customer' : 'Walk-in Customer',
            'email' => strtolower($reference).'@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => $source,
            'payment_method' => 'cash',
            'total_amount' => 160,
            'downpayment_amount' => 0,
            'balance_amount' => 160,
            'status' => BookingHeader::STATUS_PENDING,
        ]);
    }

    private function activity(BookingHeader $booking): BookingActivity
    {
        return BookingActivity::query()->create([
            'booking_header_id' => $booking->getKey(),
            'event_key' => 'booking_submitted',
            'event_label' => 'Booking submitted',
            'reference_no' => $booking->reference_no,
            'customer_name' => $booking->customer_name,
            'message' => 'New booking request submitted.',
        ]);
    }
}
