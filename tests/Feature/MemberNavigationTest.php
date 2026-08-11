<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\HyveCalendarEvent;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MemberNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_navigation_keeps_its_smooth_scroll_anchors(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="#overview"', false)
            ->assertSee('data-nav-mode="home"', false);
    }

    public function test_member_portal_uses_a_separate_member_only_navigation(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->get(route('member.dashboard'));

        $response
            ->assertOk()
            ->assertSee('HYVE Member')
            ->assertSee('Member Portal')
            ->assertSee('Dashboard')
            ->assertSee('Action center')
            ->assertSee('Live booking updates enabled')
            ->assertSee('Book a space')
            ->assertSee('Upcoming events &amp; notices', false)
            ->assertSee('Discount &amp; promo guide', false)
            ->assertDontSee('Back to HYVE website')
            ->assertDontSee('href="'.route('home').'#overview"', false)
            ->assertDontSee('href="#overview"', false);

        $this->actingAs($member)
            ->get(route('home'))
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_my_bookings_is_a_separate_focused_booking_list(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('member.index'))
            ->assertOk()
            ->assertSee('My Bookings')
            ->assertSee('Booking activity')
            ->assertSee('Upcoming')
            ->assertSee('Past')
            ->assertDontSee('Action center')
            ->assertDontSee('Upcoming events &amp; notices', false)
            ->assertDontSee('Discount &amp; promo guide', false);
    }

    public function test_member_dashboard_shows_privacy_safe_live_room_availability(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');

        try {
            $member = User::factory()->create();
            $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
            $header = BookingHeader::query()->create([
                'user_id' => $member->id,
                'reference_no' => 'HYVE-LIVE-ROOM-1',
                'customer_name' => 'Private Customer Name',
                'email' => 'private-room@example.com',
                'phone' => '09171234567',
                'booking_type' => BookingHeader::TYPE_MEMBER,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'cash',
                'total_amount' => 319,
                'downpayment_amount' => 319,
                'balance_amount' => 0,
                'status' => 'confirmed',
            ]);

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => Space::query()->where('slug', $room->mappedSpaceSlug())->value('id'),
                'hyve_room_id' => $room->id,
                'booking_date' => now()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_hours' => 2,
                'guests' => 1,
                'subtotal' => 319,
                'status' => BookingDetail::STATUS_CONFIRMED,
            ]);

            $this->actingAs($member)
                ->get(route('member.dashboard'))
                ->assertOk()
                ->assertSee('Live availability')
                ->assertSee('Find a room available right now')
                ->assertSee('Occupied now')
                ->assertDontSee('Private Customer Name');

            $response = $this->getJson(route('member.live-rooms'))
                ->assertOk()
                ->assertJsonMissing(['customer_name' => 'Private Customer Name']);

            $roomSnapshot = collect($response->json('rooms'))->firstWhere('room_name', 'Room 1');
            $this->assertSame('occupied', $roomSnapshot['status']);
            $this->assertSame('Available after 10:00 AM', $roomSnapshot['availability_detail']);
            $this->assertNotNull(collect($response->json('rooms'))->firstWhere('room_name', 'Common Area'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_logged_in_member_booking_desk_does_not_link_back_to_public_home(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('HYVE Member')
            ->assertSee('Member dashboard')
            ->assertDontSee('Back Home')
            ->assertDontSee('Back to HYVE website');
    }

    public function test_member_dashboard_shows_only_public_upcoming_calendar_events(): void
    {
        $member = User::factory()->create();

        HyveCalendarEvent::query()->create([
            'title' => 'Member Networking Night',
            'type' => HyveCalendarEvent::TYPE_CUSTOM,
            'scope' => HyveCalendarEvent::SCOPE_ALL_ROOMS,
            'source' => HyveCalendarEvent::SOURCE_ADMIN,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'all_day' => true,
            'affects_booking' => false,
            'show_to_members' => true,
            'status' => true,
        ]);
        HyveCalendarEvent::query()->create([
            'title' => 'Private Admin Maintenance Note',
            'type' => HyveCalendarEvent::TYPE_CUSTOM,
            'scope' => HyveCalendarEvent::SCOPE_ALL_ROOMS,
            'source' => HyveCalendarEvent::SOURCE_ADMIN,
            'start_date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'all_day' => true,
            'affects_booking' => false,
            'show_to_members' => false,
            'status' => true,
        ]);

        $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Member Networking Night')
            ->assertDontSee('Private Admin Maintenance Note');
    }

    public function test_admin_can_publish_a_calendar_event_to_members(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.calendar-events.store'), [
                'title' => 'Community Meetup',
                'type' => HyveCalendarEvent::TYPE_CUSTOM,
                'scope' => HyveCalendarEvent::SCOPE_ALL_ROOMS,
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addWeek()->toDateString(),
                'all_day' => '1',
                'show_to_members' => '1',
                'status' => '1',
            ])
            ->assertRedirect(route('admin.sections.calendar-events'));

        $this->assertDatabaseHas('hyve_calendar_events', [
            'title' => 'Community Meetup',
            'show_to_members' => true,
        ]);
    }

    public function test_member_booking_state_changes_after_admin_updates_the_booking(): void
    {
        $member = User::factory()->create();
        $header = BookingHeader::query()->create([
            'user_id' => $member->id,
            'reference_no' => 'HYVE-LIVE-MEMBER-1',
            'customer_name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'booking_type' => BookingHeader::TYPE_MEMBER,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'total_amount' => 749,
            'downpayment_amount' => 0,
            'balance_amount' => 749,
            'status' => BookingHeader::STATUS_PENDING,
        ]);
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $detail = BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => Space::query()->where('slug', $room->mappedSpaceSlug())->value('id'),
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 1,
            'subtotal' => 749,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $initialVersion = $this->actingAs($member)
            ->getJson(route('member.bookings.state'))
            ->assertOk()
            ->json('version');

        $header->update(['status' => 'confirmed']);
        $detail->update(['status' => BookingDetail::STATUS_CONFIRMED]);

        $updatedVersion = $this->getJson(route('member.bookings.state'))
            ->assertOk()
            ->json('version');

        $this->assertNotSame($initialVersion, $updatedVersion);
        $this->get(route('member.index'))
            ->assertOk()
            ->assertSee('data-member-booking-live-sync', false)
            ->assertSee('data-state-url="'.route('member.bookings.state').'"', false)
            ->assertSee('Confirmed');
    }
}
