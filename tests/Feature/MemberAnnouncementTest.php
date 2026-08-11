<?php

namespace Tests\Feature;

use App\Models\MemberAnnouncement;
use App\Models\MemberAnnouncementRead;
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
            ->assertSee('HYVE announcements')
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
