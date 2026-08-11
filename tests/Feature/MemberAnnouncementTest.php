<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\MemberAnnouncement;
use App\Models\MemberAnnouncementRead;
use App\Models\MemberBookingNotificationRead;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_update_and_delete_member_announcements(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Extended Weekend Hours',
                'body' => 'HYVE will stay open later this Saturday.',
                'priority' => MemberAnnouncement::PRIORITY_IMPORTANT,
                'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'expires_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'is_active' => '1',
            ])
            ->assertRedirect();

        $announcement = MemberAnnouncement::query()->firstOrFail();
        $this->assertSame($admin->id, $announcement->created_by);

        $this->patch(route('admin.announcements.update', $announcement), [
            'title' => 'Updated Weekend Hours',
            'body' => 'Please check the new weekend schedule.',
            'priority' => MemberAnnouncement::PRIORITY_URGENT,
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('member_announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Weekend Hours',
            'priority' => MemberAnnouncement::PRIORITY_URGENT,
        ]);

        $this->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('member_announcements', ['id' => $announcement->id]);
    }

    public function test_unread_status_is_tracked_separately_for_each_member(): void
    {
        $memberOne = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $memberTwo = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $announcement = $this->publishedAnnouncement();

        $this->actingAs($memberOne)
            ->getJson(route('member.announcements.feed'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('announcements.0.title', 'Community Update')
            ->assertJsonPath('announcements.0.is_read', false);

        $this->postJson(route('member.announcements.read', $announcement))
            ->assertOk();

        $this->getJson(route('member.announcements.feed'))
            ->assertJsonPath('unread_total', 0)
            ->assertJsonPath('announcements.0.is_read', true);

        $this->actingAs($memberTwo)
            ->getJson(route('member.announcements.feed'))
            ->assertJsonPath('unread_total', 1);

        $this->assertSame(1, MemberAnnouncementRead::query()->count());
    }

    public function test_member_dashboard_hides_future_expired_and_inactive_announcements(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $this->publishedAnnouncement();

        MemberAnnouncement::query()->create([
            'title' => 'Future Notice',
            'body' => 'Not published yet.',
            'priority' => MemberAnnouncement::PRIORITY_INFO,
            'published_at' => now()->addDay(),
            'is_active' => true,
        ]);
        MemberAnnouncement::query()->create([
            'title' => 'Expired Notice',
            'body' => 'No longer active.',
            'priority' => MemberAnnouncement::PRIORITY_INFO,
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Community Update')
            ->assertSee('HYVE notifications')
            ->assertDontSee('Future Notice')
            ->assertDontSee('Expired Notice');
    }

    public function test_front_desk_cannot_access_admin_announcements(): void
    {
        $frontDesk = User::factory()->create(['role' => User::ROLE_FRONT_DESK]);

        $this->actingAs($frontDesk)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

        $this->post(route('admin.announcements.store'), [
            'title' => 'Not allowed',
            'body' => 'Front desk cannot publish.',
            'priority' => MemberAnnouncement::PRIORITY_INFO,
            'published_at' => now()->toDateTimeString(),
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_admin_booking_approval_creates_an_unread_member_notification(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $header = BookingHeader::query()->create([
            'user_id' => $member->id,
            'reference_no' => 'HYVE-MEMBER-NOTIFY-1',
            'customer_name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'booking_type' => BookingHeader::TYPE_MEMBER,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'total_amount' => 749,
            'downpayment_amount' => 749,
            'balance_amount' => 0,
            'status' => BookingHeader::STATUS_PENDING,
        ]);
        BookingDetail::query()->create([
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

        $this->actingAs($admin)
            ->post(route('admin.bookings.approve', $header))
            ->assertRedirect();

        $feed = $this->actingAs($member)
            ->getJson(route('member.announcements.feed'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('booking_notifications.0.title', 'Booking approved')
            ->assertJsonPath('booking_notifications.0.reference_no', 'HYVE-MEMBER-NOTIFY-1')
            ->assertJsonPath('booking_notifications.0.is_read', false);

        $readUrl = $feed->json('booking_notifications.0.read_url');
        $this->postJson($readUrl)->assertOk();
        $this->getJson(route('member.announcements.feed'))
            ->assertJsonPath('unread_total', 0)
            ->assertJsonPath('booking_notifications.0.is_read', true);

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Booking approvals &amp; team updates', false)
            ->assertSee('Your booking HYVE-MEMBER-NOTIFY-1 has been approved by HYVE.');
        $this->assertSame(1, MemberBookingNotificationRead::query()->count());
    }

    private function publishedAnnouncement(): MemberAnnouncement
    {
        return MemberAnnouncement::query()->create([
            'title' => 'Community Update',
            'body' => 'Welcome to the latest HYVE member update.',
            'priority' => MemberAnnouncement::PRIORITY_INFO,
            'published_at' => now()->subMinute(),
            'expires_at' => now()->addWeek(),
            'is_active' => true,
        ]);
    }
}
