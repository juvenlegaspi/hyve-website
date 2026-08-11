<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminManualWalkInStartTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_walk_in_page_uses_browser_independent_manual_time_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.create'))
            ->assertOk()
            ->assertSee('data-walk-in-manual-start-hour', false)
            ->assertSee('data-walk-in-manual-start-minute', false)
            ->assertSee('data-walk-in-manual-start-period', false)
            ->assertDontSee('type="time" step="60"', false);
    }

    public function test_admin_can_load_end_times_from_an_exact_manual_walk_in_start(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('bookings.availability', [
                'hyve_room_id' => $room->id,
                'booking_date' => '2026-08-04',
                'start_time' => '09:14',
                'walk_in_manual_start' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('end_times.0.value', '11:14')
            ->assertJsonPath('end_times.0.duration_label', '2 hours');
    }

    public function test_admin_can_save_a_book_by_room_walk_in_on_exact_minutes(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.bookings.store'), [
                'booking_mode' => 'room',
                'walk_in_manual_start' => 1,
                'full_name' => 'Exact Minute Walk In',
                'email' => 'exact-minute@example.com',
                'phone' => '09171234567',
                'hyve_room_id' => $room->id,
                'booking_date' => '2026-08-04',
                'start_time' => '09:14',
                'end_time' => '11:14',
                'guests' => 2,
                'downpayment_amount' => 0,
                'payment_method' => 'pay_later',
            ])
            ->assertRedirect(route('admin.bookings.index'))
            ->assertSessionHas('admin_success');

        $header = BookingHeader::query()->where('email', 'exact-minute@example.com')->firstOrFail();
        $detail = $header->details()->firstOrFail();

        $this->assertSame(BookingHeader::SOURCE_ADMIN, $header->source);
        $this->assertSame('09:14', substr((string) $detail->start_time, 0, 5));
        $this->assertSame('11:14', substr((string) $detail->end_time, 0, 5));
    }

    public function test_recently_verified_exact_start_remains_valid_while_admin_finishes_checkout(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-04',
            'start_time' => '09:14',
            'walk_in_manual_start' => 1,
        ]))->assertOk()->assertJsonPath('end_times.0.value', '11:14');

        Carbon::setTestNow('2026-08-04 09:20:00');

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'room',
            'walk_in_manual_start' => 1,
            'full_name' => 'Delayed Exact Checkout',
            'email' => 'delayed-exact@example.com',
            'phone' => '09171234571',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-04',
            'start_time' => '09:14',
            'end_time' => '11:14',
            'guests' => 2,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ])->assertRedirect(route('admin.bookings.index'))->assertSessionHas('admin_success');

        $this->assertDatabaseHas('booking_headers', ['email' => 'delayed-exact@example.com']);
    }

    public function test_common_area_manual_walk_in_uses_an_available_table_on_exact_minutes(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $commonArea = HyveRoom::query()->where('room_name', 'Table 1-A')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('bookings.availability', [
                'hyve_room_id' => $commonArea->id,
                'booking_date' => '2026-08-04',
                'start_time' => '09:14',
                'walk_in_manual_start' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('end_times.0.value', '11:14');

        $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'room',
            'walk_in_manual_start' => 1,
            'full_name' => 'Common Exact Minute',
            'email' => 'common-exact@example.com',
            'phone' => '09171234570',
            'hyve_room_id' => $commonArea->id,
            'booking_date' => '2026-08-04',
            'start_time' => '09:14',
            'end_time' => '11:14',
            'guests' => 1,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ])->assertRedirect(route('admin.bookings.index'));

        $detail = BookingHeader::query()->where('email', 'common-exact@example.com')->firstOrFail()->details()->firstOrFail();
        $this->assertTrue($detail->hyveRoom->isSharedTable());
        $this->assertSame('09:14', substr((string) $detail->start_time, 0, 5));
    }

    public function test_admin_manual_walk_in_start_cannot_be_in_the_past(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.bookings.create'))
            ->post(route('admin.bookings.store'), [
                'booking_mode' => 'room',
                'walk_in_manual_start' => 1,
                'full_name' => 'Past Walk In',
                'email' => 'past-minute@example.com',
                'phone' => '09171234568',
                'hyve_room_id' => $room->id,
                'booking_date' => '2026-08-04',
                'start_time' => '09:13',
                'end_time' => '11:13',
                'guests' => 2,
                'downpayment_amount' => 0,
                'payment_method' => 'pay_later',
            ])
            ->assertRedirect(route('admin.bookings.create'))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseMissing('booking_headers', ['email' => 'past-minute@example.com']);
    }

    public function test_online_booking_cannot_enable_admin_manual_start_mode(): void
    {
        Carbon::setTestNow('2026-08-04 09:14:00');
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->post(route('bookings.store'), [
            'booking_mode' => 'room',
            'walk_in_manual_start' => 1,
            'full_name' => 'Online Exact Minute',
            'email' => 'online-exact@example.com',
            'phone' => '09171234569',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-04',
            'start_time' => '09:14',
            'end_time' => '11:14',
            'guests' => 2,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => 1,
        ])->assertSessionHasErrors('walk_in_manual_start');

        $this->assertDatabaseMissing('booking_headers', ['email' => 'online-exact@example.com']);
    }
}
