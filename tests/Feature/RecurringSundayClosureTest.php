<?php

namespace Tests\Feature;

use App\Models\HyveRecurringClosure;
use App\Models\HyveRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringSundayClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_sunday_is_closed_for_all_rooms_by_default(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $weeklyRoom = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $sunday = '2026-08-16';

        $this->assertDatabaseHas('hyve_recurring_closures', [
            'weekday' => HyveRecurringClosure::SUNDAY,
            'is_active' => true,
        ]);

        $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $weeklyRoom->id,
            'booking_date' => $sunday,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'start_times')
            ->assertJsonPath('room.status', 'closed');

        $this->getJson(route('bookings.unavailable-dates', [
            'hyve_room_id' => $weeklyRoom->id,
            'start_date' => $sunday,
            'horizon_days' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('unavailable_dates.0.value', $sunday)
            ->assertJsonPath('recurring_closure_dates.0.value', $sunday);

        $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $weeklyRoom->id,
            'booking_date' => '2026-08-10',
            'booking_end_date' => $sunday,
            'monthly_plan' => 'Weekly',
            'long_stay_use_type' => 'day',
        ]))
            ->assertOk()
            ->assertJsonPath('unit_type', 'weekly');

        $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => $sunday,
            'booking_end_date' => $sunday,
            'monthly_plan' => 'Daily',
            'long_stay_use_type' => 'day',
        ]))
            ->assertUnprocessable();
    }

    public function test_admin_can_reopen_and_close_every_sunday_with_one_control(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $sunday = '2026-08-16';

        $this->actingAs($admin)
            ->get(route('admin.sections.room-schedule', [
                'room' => $room->id,
                'month' => '2026-08',
                'date' => $sunday,
            ]))
            ->assertOk()
            ->assertSee('Every Sunday — Closed for all rooms')
            ->assertSee('Globally closed every Sunday.');

        $this->actingAs($admin)
            ->post(route('admin.room-schedule.sunday-closure'), [
                'is_active' => 0,
                'reason' => 'HYVE is temporarily closed every Sunday.',
                'room' => $room->id,
                'month' => '2026-08',
                'date' => $sunday,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hyve_recurring_closures', [
            'weekday' => HyveRecurringClosure::SUNDAY,
            'is_active' => false,
        ]);

        $reopened = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => $sunday,
        ]));
        $reopened
            ->assertOk()
            ->assertJsonPath('room.status', 'available');
        $this->assertNotEmpty($reopened->json('start_times'));

        $this->actingAs($admin)
            ->post(route('admin.room-schedule.sunday-closure'), [
                'is_active' => 1,
                'reason' => 'Sunday day off',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hyve_recurring_closures', [
            'weekday' => HyveRecurringClosure::SUNDAY,
            'is_active' => true,
            'reason' => 'Sunday day off',
        ]);
    }
}
