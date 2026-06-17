<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_users_can_submit_a_booking_request_without_an_account(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Zeal Room (8 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->post(route('bookings.store'), [
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '+639181112222',
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 6,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'notes' => 'Need the room for a client presentation.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $this->assertDatabaseHas('booking_headers', [
            'customer_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '+639181112222',
            'booking_type' => 'guest',
            'payment_method' => 'gcash',
            'downpayment_amount' => 500,
            'balance_amount' => 1098,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('booking_details', [
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'guests' => 6,
            'rate_name' => 'Zeal Room (8 Seats) - Day Use',
            'status' => 'pending',
        ]);

        $header = BookingHeader::query()->latest('id')->firstOrFail();
        Storage::disk('public')->assertExists($header->payment_proof_path);
    }

    public function test_an_authenticated_user_can_submit_a_booking_request(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Zeal Room (8 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $user = User::factory()->create([
            'username' => 'juandelacruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'number' => '+639171234567',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('bookings.store'), [
                'hyve_room_id' => $room->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'guests' => 6,
                'downpayment_amount' => 800,
                'payment_method' => 'bank_transfer',
                'payment_proof' => UploadedFile::fake()->create('bank-proof.jpg', 120, 'image/jpeg'),
                'notes' => 'Need the room for a client presentation.',
            ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $this->assertDatabaseHas('booking_headers', [
            'user_id' => $user->id,
            'customer_name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'booking_type' => 'member',
            'payment_method' => 'bank_transfer',
            'downpayment_amount' => 800,
            'balance_amount' => 798,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('booking_details', [
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'guests' => 6,
            'rate_name' => 'Zeal Room (8 Seats) - Day Use',
            'status' => 'pending',
        ]);
    }

    public function test_quote_endpoint_returns_total_downpayment_and_balance(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Zeal Room (8 Seats) - Day Use',
            'total_amount' => 1598,
            'minimum_downpayment_amount' => 500,
        ]);
    }

    public function test_guest_users_can_submit_a_full_schedule_booking_with_multiple_room_slots(): void
    {
        Storage::fake('public');

        $roomOne = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $roomTwo = HyveRoom::query()->where('room_name', 'Room 2')->firstOrFail();

        $response = $this->post(route('bookings.store'), [
            'booking_mode' => 'schedule',
            'selected_schedule_items' => json_encode([
                [
                    'hyve_room_id' => $roomOne->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ],
                [
                    'hyve_room_id' => $roomTwo->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                ],
            ], JSON_THROW_ON_ERROR),
            'guests' => 2,
            'downpayment_amount' => 299,
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'full_name' => 'Schedule Guest',
            'email' => 'schedule@example.com',
            'phone' => '+639181234567',
            'notes' => 'Testing full schedule checkout.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $header = BookingHeader::query()->latest('id')->firstOrFail();

        $this->assertSame(2, $header->details()->count());
        $this->assertDatabaseHas('booking_headers', [
            'id' => $header->id,
            'booking_type' => 'guest',
            'payment_method' => 'gcash',
            'downpayment_amount' => 299,
            'status' => 'pending',
        ]);
    }

    public function test_guest_users_can_submit_a_full_schedule_booking_with_adjacent_hours_in_the_same_room(): void
    {
        Storage::fake('public');

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->post(route('bookings.store'), [
            'booking_mode' => 'schedule',
            'selected_schedule_items' => json_encode([
                [
                    'hyve_room_id' => $room->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ],
                [
                    'hyve_room_id' => $room->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                ],
            ], JSON_THROW_ON_ERROR),
            'guests' => 2,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'full_name' => 'Schedule Guest',
            'email' => 'schedule@example.com',
            'phone' => '+639181234567',
            'notes' => 'Testing adjacent hours in same room.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $header = BookingHeader::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $header->details()->count());
    }

    public function test_full_schedule_booking_preserves_selected_slots_after_validation_error(): void
    {
        Storage::fake('public');

        $roomOne = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $roomTwo = HyveRoom::query()->where('room_name', 'Room 2')->firstOrFail();

        $response = $this
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), [
                'booking_mode' => 'schedule',
                'selected_schedule_items' => json_encode([
                    [
                        'hyve_room_id' => $roomOne->id,
                        'booking_date' => now()->addDay()->toDateString(),
                        'start_time' => '08:00',
                        'end_time' => '09:00',
                    ],
                    [
                        'hyve_room_id' => $roomTwo->id,
                        'booking_date' => now()->addDays(2)->toDateString(),
                        'start_time' => '09:00',
                        'end_time' => '10:00',
                    ],
                ], JSON_THROW_ON_ERROR),
                'guests' => 2,
                'downpayment_amount' => 10,
                'payment_method' => 'gcash',
                'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
                'full_name' => 'Schedule Guest',
                'email' => 'schedule@example.com',
                'phone' => '+639181234567',
                'notes' => 'Testing full schedule validation restore.',
            ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors('downpayment_amount');
        $this->assertSame('schedule', session()->getOldInput('booking_mode'));
        $oldScheduleItems = session()->getOldInput('selected_schedule_items');
        $decodedScheduleItems = is_string($oldScheduleItems) ? json_decode($oldScheduleItems, true) : $oldScheduleItems;
        $this->assertIsArray($decodedScheduleItems);
        $this->assertCount(2, $decodedScheduleItems);
    }

    public function test_full_schedule_booking_rejects_expired_current_day_slots_with_a_specific_message(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-06-17 08:58:00');

        try {
            $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

            $response = $this
                ->from(route('bookings.index'))
                ->post(route('bookings.store'), [
                    'booking_mode' => 'schedule',
                    'selected_schedule_items' => json_encode([
                        [
                            'hyve_room_id' => $room->id,
                            'booking_date' => now()->toDateString(),
                            'start_time' => '08:00',
                            'end_time' => '09:00',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'guests' => 1,
                    'downpayment_amount' => 500,
                    'payment_method' => 'gcash',
                    'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
                    'full_name' => 'Expired Slot Guest',
                    'email' => 'expired@example.com',
                    'phone' => '+639181234567',
                    'notes' => 'Testing expired current-day schedule slot.',
                ]);

            $response->assertRedirect(route('bookings.index'));
            $response->assertSessionHasErrors([
                'selected_schedule_items' => 'These schedule slots are no longer available: Conference Room - June 17, 2026 - 8:00 AM to 9:00 AM. Please remove them and choose another time.',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_quote_endpoint_uses_half_of_total_for_bookings_up_to_one_thousand(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Fortitude Office (2 Seats) - Day Use',
            'total_amount' => 299,
            'minimum_downpayment_amount' => 149.5,
        ]);
    }

    public function test_availability_endpoint_excludes_existing_booked_slots(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-TEST-000001',
            'customer_name' => 'Booked Client',
            'email' => 'booked@example.com',
            'phone' => '+639181112222',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => Space::query()->where('slug', 'zeal-room-8-seats')->value('id'),
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 4,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonMissing([
            'value' => '08:00',
        ]);
        $response->assertJsonFragment(['value' => '00:00']);
        $response->assertJsonFragment(['value' => '07:00']);
        $response->assertJsonFragment(['value' => '10:00']);
    }

    public function test_unavailable_dates_endpoint_returns_fully_booked_days(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-TEST-000003',
            'customer_name' => 'Full Day Client',
            'email' => 'fullday@example.com',
            'phone' => '+639181112224',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        $bookingDate = now()->addDay()->toDateString();

        foreach ([
            ['00:00', '02:00'],
            ['02:00', '04:00'],
            ['04:00', '06:00'],
            ['06:00', '08:00'],
            ['08:00', '10:00'],
            ['10:00', '12:00'],
            ['12:00', '14:00'],
            ['14:00', '16:00'],
            ['16:00', '18:00'],
            ['18:00', '20:00'],
            ['20:00', '22:00'],
            ['22:00', '00:00'],
        ] as [$startTime, $endTime]) {
            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => Space::query()->where('slug', 'zeal-room-8-seats')->value('id'),
                'hyve_room_id' => $room->id,
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'guests' => 2,
                'status' => BookingDetail::STATUS_PENDING,
            ]);
        }

        $response = $this->getJson(route('bookings.unavailable-dates', [
            'hyve_room_id' => $room->id,
            'horizon_days' => 7,
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'value' => $bookingDate,
        ]);
    }

    public function test_a_booking_cannot_use_a_slot_that_has_already_been_taken(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Zeal Room (8 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-TEST-000002',
            'customer_name' => 'Existing Client',
            'email' => 'existing@example.com',
            'phone' => '+639181112223',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'guests' => 2,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '+639181112222',
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30',
            'end_time' => '11:30',
            'guests' => 3,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'notes' => 'Trying an occupied slot.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors('end_time');
    }

    public function test_guest_users_can_submit_a_small_booking_with_a_downpayment_below_five_hundred(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Fortitude Office (2 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $response = $this->post(route('bookings.store'), [
            'full_name' => 'Paula Reyes',
            'email' => 'paula@example.com',
            'phone' => '+639191234567',
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'downpayment_amount' => 149.50,
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'notes' => 'Short private booking.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $this->assertDatabaseHas('booking_headers', [
            'customer_name' => 'Paula Reyes',
            'booking_type' => 'guest',
            'payment_method' => 'gcash',
            'total_amount' => 299,
            'downpayment_amount' => 149.50,
            'balance_amount' => 149.50,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('booking_details', [
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'rate_name' => 'Fortitude Office (2 Seats) - Day Use',
            'status' => 'pending',
        ]);
    }

    public function test_guest_users_can_upload_supported_payment_proof_image_types(): void
    {
        Storage::fake('public');

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        foreach ([
            UploadedFile::fake()->image('proof.gif'),
        ] as $paymentProof) {
            try {
                $response = $this->post(route('bookings.store'), [
                    'full_name' => 'Maria Santos',
                    'email' => 'maria@example.com',
                    'phone' => '+639181112222',
                    'hyve_room_id' => $room->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'guests' => 6,
                    'downpayment_amount' => 500,
                    'payment_method' => 'gcash',
                    'payment_proof' => $paymentProof,
                    'notes' => 'Testing supported image uploads.',
                ]);

                $response->assertRedirect(route('bookings.index'));
                $response->assertSessionHas('booking_success');
            } catch (\Throwable $throwable) {
                $this->fail("Upload type failed for {$paymentProof->getClientOriginalName()}: {$throwable->getMessage()}");
            }
        }

        $this->assertDatabaseCount('booking_headers', 1);
    }

    public function test_room_layout_endpoint_returns_room_statuses_and_slots(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-TEST-000004',
            'customer_name' => 'Layout Client',
            'email' => 'layout@example.com',
            'phone' => '+639181112225',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => Space::query()->where('slug', 'zeal-room-8-seats')->value('id'),
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('bookings.room-layout', [
            'booking_date' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'room_name' => 'Conference Room',
            'status' => 'booked',
        ]);
    }

    public function test_today_room_layout_only_returns_current_and_future_hourly_slots(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 9, 16, 3, 0));

        try {
            $response = $this->getJson(route('bookings.room-layout', [
                'booking_date' => '2026-06-09',
            ]));

            $response->assertOk();
            $response->assertJsonMissing([
                'label' => '4:00 PM - 5:00 PM',
            ]);
            $response->assertJsonFragment([
                'label' => '4:30 PM - 5:30 PM',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_today_availability_only_shows_future_half_hour_slots(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 9, 16, 3, 0));

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-06-09',
        ]));

        $response->assertOk();
        $response->assertJsonMissing(['value' => '16:00']);
        $response->assertJsonFragment(['value' => '16:30']);

        Carbon::setTestNow();
    }

    public function test_availability_endpoint_returns_end_times_for_the_selected_start_time(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'value' => '09:00',
            'duration_label' => '1 hour',
        ]);
        $response->assertJsonFragment([
            'value' => '10:00',
            'duration_label' => '2 hours',
        ]);
    }

    public function test_today_end_time_options_only_show_future_ranges_from_the_selected_start_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 9, 16, 3, 0));

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-06-09',
            'start_time' => '16:30',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'value' => '17:30',
            'duration_label' => '1 hour',
        ]);

        Carbon::setTestNow();
    }
}
