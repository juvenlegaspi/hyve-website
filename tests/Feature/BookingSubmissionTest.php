<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'rules_agreement' => '1',
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

    public function test_admin_can_create_a_walk_in_booking_with_no_downpayment_to_be_paid_at_checkout(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.bookings.store'), [
                'booking_mode' => 'room',
                'full_name' => 'Walk In Customer',
                'email' => 'walkin@example.com',
                'phone' => '09171234567',
                'hyve_room_id' => $room->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'guests' => 4,
                'downpayment_amount' => 0,
                'payment_method' => 'pay_later',
                'notes' => 'Customer will pay upon checkout.',
            ]);

        $response->assertRedirect(route('admin.bookings.index'));
        $response->assertSessionHas('admin_success');

        $header = BookingHeader::query()->where('email', 'walkin@example.com')->firstOrFail();

        $this->assertSame(BookingHeader::SOURCE_ADMIN, $header->source);
        $this->assertSame('pay_later', $header->payment_method);
        $this->assertSame('pending_verification', $header->payment_status);
        $this->assertSame(0.0, (float) $header->downpayment_amount);
        $this->assertSame((float) $header->total_amount, (float) $header->balance_amount);
        $this->assertDatabaseMissing('booking_payments', [
            'booking_header_id' => $header->getKey(),
        ]);
    }

    public function test_online_booking_still_rejects_a_zero_downpayment(): void
    {
        Storage::fake('public');

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $this->from(route('bookings.index'))
            ->post(route('bookings.store'), [
                'booking_mode' => 'room',
                'full_name' => 'Online Customer',
                'email' => 'online-zero@example.com',
                'phone' => '09181234567',
                'hyve_room_id' => $room->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'guests' => 2,
                'downpayment_amount' => 0,
                'payment_method' => 'gcash',
                'rules_agreement' => '1',
                'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            ])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors('downpayment_amount');

        $this->assertDatabaseMissing('booking_headers', [
            'email' => 'online-zero@example.com',
        ]);
    }

    public function test_online_common_area_booking_can_pay_at_hyve_without_a_downpayment(): void
    {
        Storage::fake('public');

        $commonArea = HyveRoom::query()->where('room_name', 'Table 1-A')->firstOrFail();

        $this->post(route('bookings.store'), [
            'booking_mode' => 'room',
            'full_name' => 'Common Area Customer',
            'email' => 'common-pay-later@example.com',
            'phone' => '09191234567',
            'hyve_room_id' => $commonArea->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 1,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
            'rules_agreement' => '1',
        ])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('booking_success');

        $header = BookingHeader::query()->where('email', 'common-pay-later@example.com')->firstOrFail();

        $this->assertSame(BookingHeader::SOURCE_WEB, $header->source);
        $this->assertSame('pay_later', $header->payment_method);
        $this->assertSame(0.0, (float) $header->downpayment_amount);
        $this->assertSame((float) $header->total_amount, (float) $header->balance_amount);
        $this->assertDatabaseMissing('booking_payments', [
            'booking_header_id' => $header->getKey(),
        ]);
    }

    public function test_repeated_checkout_submission_token_creates_only_one_booking(): void
    {
        $commonArea = HyveRoom::query()->where('room_name', 'Table 1-A')->firstOrFail();
        $submissionToken = fake()->uuid();
        $payload = [
            'submission_token' => $submissionToken,
            'booking_mode' => 'room',
            'full_name' => 'Single Submit Customer',
            'email' => 'single-submit@example.com',
            'phone' => '09191234567',
            'hyve_room_id' => $commonArea->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 1,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
            'rules_agreement' => '1',
        ];

        $this->post(route('bookings.store'), $payload)
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('booking_success');
        $this->post(route('bookings.store'), $payload)
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('booking_success');

        $this->assertSame(1, BookingHeader::query()->where('email', 'single-submit@example.com')->count());
        $this->assertSame(1, BookingDetail::query()->where('submission_token', $submissionToken)->count());
    }

    public function test_common_area_long_stay_uses_another_table_when_one_table_has_an_hourly_conflict(): void
    {
        $selectedTable = HyveRoom::query()->where('room_name', 'Table 1-A')->firstOrFail();
        $bookingDate = now()->addDays(5)->startOfDay();
        $blockedHeader = BookingHeader::query()->create([
            'reference_no' => 'HYVE-COMMON-POOL-ONE',
            'customer_name' => 'Existing Common Area Guest',
            'email' => 'existing-common@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);
        BookingDetail::query()->create([
            'booking_header_id' => $blockedHeader->id,
            'space_id' => Space::query()->where('slug', 'common-area')->value('id'),
            'hyve_room_id' => $selectedTable->id,
            'booking_date' => $bookingDate->toDateString(),
            'booking_end_date' => $bookingDate->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'charge_period' => 'day',
            'guests' => 1,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $totalTables = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->count();
        $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $selectedTable->id,
            'booking_date' => $bookingDate->toDateString(),
            'booking_end_date' => $bookingDate->copy()->addDays(2)->toDateString(),
            'long_stay_use_type' => 'day',
        ]))
            ->assertOk()
            ->assertJsonPath('common_area_availability.available_tables', $totalTables - 1)
            ->assertJsonPath('common_area_availability.total_tables', $totalTables);

        $response = $this->post(route('bookings.store'), [
            'booking_mode' => 'monthly',
            'full_name' => 'Common Area Day User',
            'email' => 'common-day-user@example.com',
            'phone' => '09181234567',
            'hyve_room_id' => $selectedTable->id,
            'booking_date' => $bookingDate->toDateString(),
            'booking_end_date' => $bookingDate->copy()->addDays(2)->toDateString(),
            'long_stay_use_type' => 'day',
            'guests' => 1,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
            'rules_agreement' => '1',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $assignedDetail = BookingHeader::query()
            ->where('email', 'common-day-user@example.com')
            ->firstOrFail()
            ->details()
            ->firstOrFail();

        $this->assertNotSame($selectedTable->id, $assignedDetail->hyve_room_id);
        $this->assertTrue($assignedDetail->hyveRoom->isSharedTable());
        $this->assertSame('daily', (string) $assignedDetail->charge_period);
    }

    public function test_common_area_long_stay_is_rejected_when_every_table_has_a_conflict(): void
    {
        $tables = HyveRoom::query()->active()->where('room_name', 'like', 'Table %')->orderBy('id')->get();
        $this->assertNotEmpty($tables);
        $bookingDate = now()->addDays(5)->startOfDay();
        $blockedHeader = BookingHeader::query()->create([
            'reference_no' => 'HYVE-COMMON-POOL-FULL',
            'customer_name' => 'Existing Common Area Guests',
            'email' => 'full-common@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        foreach ($tables as $table) {
            BookingDetail::query()->create([
                'booking_header_id' => $blockedHeader->id,
                'space_id' => Space::query()->where('slug', 'common-area')->value('id'),
                'hyve_room_id' => $table->id,
                'booking_date' => $bookingDate->toDateString(),
                'booking_end_date' => $bookingDate->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'charge_period' => 'day',
                'guests' => 1,
                'status' => BookingDetail::STATUS_PENDING,
            ]);
        }

        $response = $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'booking_mode' => 'monthly',
            'full_name' => 'Rejected Common Area User',
            'email' => 'rejected-common@example.com',
            'phone' => '09181234567',
            'hyve_room_id' => $tables->first()->id,
            'booking_date' => $bookingDate->toDateString(),
            'booking_end_date' => $bookingDate->copy()->addDays(2)->toDateString(),
            'long_stay_use_type' => 'day',
            'guests' => 1,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
            'rules_agreement' => '1',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors('booking_end_date');
        $this->assertDatabaseMissing('booking_headers', ['email' => 'rejected-common@example.com']);
    }

    public function test_online_private_room_cannot_use_pay_at_hyve_to_bypass_the_downpayment(): void
    {
        Storage::fake('public');

        $privateRoom = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $this->from(route('bookings.index'))
            ->post(route('bookings.store'), [
                'booking_mode' => 'room',
                'full_name' => 'Private Room Customer',
                'email' => 'private-pay-later@example.com',
                'phone' => '09201234567',
                'hyve_room_id' => $privateRoom->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'guests' => 2,
                'downpayment_amount' => 0,
                'payment_method' => 'pay_later',
                'rules_agreement' => '1',
            ])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseMissing('booking_headers', [
            'email' => 'private-pay-later@example.com',
        ]);
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
                'rules_agreement' => '1',
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

    public function test_an_authenticated_member_reuses_the_latest_pending_header_for_new_bookings(): void
    {
        Storage::fake('public');

        $roomOne = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $roomTwo = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $user = User::factory()->create([
            'username' => 'juvcruz',
            'first_name' => 'Juven',
            'last_name' => 'Cruz',
            'email' => 'cruz@gmail.com',
            'number' => '09085737384',
        ]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'hyve_room_id' => $roomOne->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 4,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('first-proof.png', 120, 'image/png'),
            'notes' => 'First member booking.',
        ])->assertRedirect(route('bookings.index'));

        $firstHeader = BookingHeader::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post(route('bookings.store'), [
            'hyve_room_id' => $roomTwo->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'downpayment_amount' => 149.50,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('second-proof.png', 120, 'image/png'),
            'notes' => 'Second member booking.',
        ])->assertRedirect(route('bookings.index'));

        $this->assertDatabaseCount('booking_headers', 1);

        $header = BookingHeader::query()
            ->with('details')
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame($firstHeader->id, $header->id);
        $this->assertCount(2, $header->details);
        $this->assertSame(1897.00, (float) $header->total_amount);
        $this->assertSame(649.50, (float) $header->downpayment_amount);
        $this->assertSame(1247.50, (float) $header->balance_amount);
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

    public function test_quote_endpoint_uses_night_rate_starting_at_8_pm(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Fortitude Office (2 Seats) - Night Use',
            'charge_period' => 'night',
            'charge_period_label' => 'Night Use',
            'total_amount' => 319,
        ]);
    }

    public function test_quote_crossing_from_day_to_night_uses_one_minimum_then_night_succeeding_rate(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '21:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('charge_period', 'mixed')
            ->assertJsonPath('charge_period_label', 'Day + Night Use')
            ->assertJsonPath('minimum_rate', 719)
            ->assertJsonPath('total_amount', 1108)
            ->assertJsonPath('breakdown.0.amount', 719)
            ->assertJsonPath('breakdown.1.amount', 389);
    }

    public function test_quote_crossing_from_night_to_day_uses_one_minimum_then_day_succeeding_rate(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '06:00',
            'end_time' => '09:00',
        ]));

        $response->assertOk()
            ->assertJsonPath('charge_period', 'mixed')
            ->assertJsonPath('charge_period_label', 'Day + Night Use')
            ->assertJsonPath('minimum_rate', 789)
            ->assertJsonPath('total_amount', 1148)
            ->assertJsonPath('breakdown.0.amount', 789)
            ->assertJsonPath('breakdown.1.amount', 359);
    }

    public function test_quote_endpoint_returns_monthly_plan_amounts(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();
        $startDate = '2026-08-01';
        $endDate = '2026-08-31';

        $response = $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => $startDate,
            'booking_end_date' => $endDate,
            'monthly_plan' => 'Monthly Rental',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Tenacity Office (4 Seats) - Monthly Rental',
            'monthly_plan_label' => 'Monthly Rental',
            'total_amount' => 30000,
            'minimum_downpayment_amount' => 500,
            'unit_type' => 'monthly',
            'unit_count' => 1,
        ]);
    }

    public function test_29_30_and_31_day_ranges_automatically_use_one_month_without_day_or_night(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        foreach (['2026-08-29', '2026-08-30', '2026-08-31'] as $endDate) {
            $response = $this->getJson(route('bookings.quote', [
                'booking_mode' => 'monthly',
                'hyve_room_id' => $room->id,
                'booking_date' => '2026-08-01',
                'booking_end_date' => $endDate,
            ]));

            $response->assertOk()
                ->assertJsonPath('unit_type', 'monthly')
                ->assertJsonPath('unit_count', 1)
                ->assertJsonPath('unit_label', '1 month')
                ->assertJsonPath('total_amount', 30000)
                ->assertJsonPath('long_stay_use_type', null)
                ->assertJsonPath('long_stay_use_label', null);
        }
    }

    public function test_an_arbitrary_29_day_range_uses_one_month_without_calendar_month_boundaries(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-10',
            'booking_end_date' => '2026-09-07',
        ]))
            ->assertOk()
            ->assertJsonPath('unit_label', '1 month')
            ->assertJsonPath('total_amount', 30000)
            ->assertJsonPath('long_stay_use_type', null);
    }

    public function test_days_beyond_the_monthly_band_are_billed_as_daily_excess(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-01',
            'booking_end_date' => '2026-09-01',
        ]))
            ->assertOk()
            ->assertJsonPath('unit_label', '1 month + 1 day')
            ->assertJsonPath('total_amount', 32159)
            ->assertJsonPath('long_stay_use_type', null)
            ->assertJsonPath('breakdown.0.type', 'monthly')
            ->assertJsonPath('breakdown.0.unit_count', 1)
            ->assertJsonPath('breakdown.1.type', 'daily')
            ->assertJsonPath('breakdown.1.unit_count', 1);
    }

    public function test_quote_endpoint_uses_calendar_month_breakdown_for_long_stay_ranges(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $response = $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-01',
            'booking_end_date' => '2026-09-03',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'unit_type' => 'monthly',
            'unit_count' => 1,
            'monthly_plan_label' => '1 month + 3 days',
            'unit_label' => '1 month + 3 days',
            'total_amount' => 36477,
        ]);
    }

    public function test_quote_endpoint_returns_daily_plan_amounts_for_selected_date_range(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $startDate = now()->addMonth()->startOfMonth();

        $response = $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => $startDate->toDateString(),
            'booking_end_date' => $startDate->copy()->addDays(2)->toDateString(),
            'monthly_plan' => 'Daily',
            'long_stay_use_type' => 'day',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Fortitude Office (2 Seats) - 3 days',
            'unit_type' => 'daily',
            'unit_count' => 3,
            'total_amount' => 2247,
        ]);
    }

    public function test_quote_endpoint_returns_weekly_plan_amounts_for_selected_date_range(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
        $startDate = now()->addMonth()->startOfMonth();

        $response = $this->getJson(route('bookings.quote', [
            'booking_mode' => 'monthly',
            'hyve_room_id' => $room->id,
            'booking_date' => $startDate->toDateString(),
            'booking_end_date' => $startDate->copy()->addDays(7)->toDateString(),
            'monthly_plan' => 'Weekly',
            'long_stay_use_type' => 'day',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'rate_name' => 'Fortitude Office (2 Seats) - 1 week + 1 day',
            'unit_type' => 'weekly',
            'unit_count' => 1,
            'total_amount' => 5244,
        ]);
    }

    public function test_guest_users_can_submit_a_monthly_booking_request(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Tenacity Office (4 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Room 7')->firstOrFail();

        $response = $this->post(route('bookings.store'), [
            'booking_mode' => 'monthly',
            'full_name' => 'Monthly Guest',
            'email' => 'monthly@example.com',
            'phone' => '+639171110000',
            'hyve_room_id' => $room->id,
            'booking_date' => '2026-08-01',
            'booking_end_date' => '2026-08-31',
            'monthly_plan' => 'Monthly Rental',
            'guests' => 4,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('monthly-proof.png', 120, 'image/png'),
            'notes' => 'Need a one-month private office setup.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $this->assertDatabaseHas('booking_headers', [
            'customer_name' => 'Monthly Guest',
            'email' => 'monthly@example.com',
            'booking_type' => 'monthly',
            'payment_method' => 'gcash',
            'total_amount' => 30000,
            'downpayment_amount' => 500,
            'balance_amount' => 29500,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('booking_details', [
            'space_id' => $space->id,
            'hyve_room_id' => $room->id,
            'guests' => 4,
            'rate_name' => 'Tenacity Office (4 Seats) - Monthly Rental',
            'charge_period' => 'monthly',
            'subtotal' => 30000,
            'status' => 'pending',
        ]);

        $detail = BookingDetail::query()->where('hyve_room_id', $room->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-08-31', optional($detail->booking_end_date)->toDateString());
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
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'full_name' => 'Schedule Guest',
            'email' => 'schedule@example.com',
            'phone' => '+639181234567',
            'notes' => 'Testing full schedule checkout.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $header = BookingHeader::query()->latest('id')->first();

        $this->assertNotNull($header);
        $this->assertSame(2, BookingDetail::query()->where('booking_header_id', $header->id)->count());
        $this->assertDatabaseHas('booking_headers', [
            'id' => $header?->id,
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
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'full_name' => 'Schedule Guest',
            'email' => 'schedule@example.com',
            'phone' => '+639181234567',
            'notes' => 'Testing adjacent hours in same room.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('booking_success');

        $header = BookingHeader::query()->latest('id')->first();

        $this->assertNotNull($header);
        $this->assertSame(2, BookingDetail::query()->where('booking_header_id', $header->id)->count());
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
                'rules_agreement' => '1',
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
                        [
                            'hyve_room_id' => $room->id,
                            'booking_date' => now()->toDateString(),
                            'start_time' => '09:00',
                            'end_time' => '10:00',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'guests' => 1,
                    'downpayment_amount' => 500,
                    'payment_method' => 'gcash',
                    'rules_agreement' => '1',
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
        $response->assertJsonFragment(['value' => '06:00']);
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

    public function test_unavailable_dates_endpoint_can_load_a_future_month_without_scanning_from_today(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $startDate = now()->addDays(40)->toDateString();
        $endDate = now()->addDays(70)->toDateString();
        $requestedDate = now()->addDays(60)->toDateString();

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-TEST-FUTURE-MONTH',
            'customer_name' => 'Monthly Client',
            'email' => 'monthly@example.com',
            'phone' => '+639181112225',
            'booking_type' => BookingHeader::TYPE_MONTHLY,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => Space::query()->where('slug', 'zeal-room-8-seats')->value('id'),
            'hyve_room_id' => $room->id,
            'booking_date' => $startDate,
            'booking_end_date' => $endDate,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'charge_period' => 'monthly',
            'guests' => 2,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->getJson(route('bookings.unavailable-dates', [
            'hyve_room_id' => $room->id,
            'start_date' => now()->addDays(50)->toDateString(),
            'horizon_days' => 31,
        ]))
            ->assertOk()
            ->assertJsonFragment(['value' => $requestedDate]);

        $this->assertLessThan(25, $queryCount, 'Unavailable dates should be loaded with bulk queries, not one query per day.');
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
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'notes' => 'Trying an occupied slot.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors('end_time');
    }

    public function test_submission_rechecks_availability_after_acquiring_the_room_lock(): void
    {
        Storage::fake('public');

        $space = Space::query()->where('name', 'Zeal Room (8 Seats)')->firstOrFail();
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $bookingDate = now()->addDay()->toDateString();
        $conflictInjected = false;
        $baseTransactionLevel = DB::transactionLevel();

        HyveRoom::retrieved(function (HyveRoom $retrievedRoom) use ($room, $space, $bookingDate, $baseTransactionLevel, &$conflictInjected): void {
            if ($conflictInjected || $retrievedRoom->isNot($room) || DB::transactionLevel() <= $baseTransactionLevel) {
                return;
            }

            $conflictInjected = true;
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-RACE-WINNER',
                'customer_name' => 'Concurrent Customer',
                'email' => 'winner@example.com',
                'phone' => '+639181112224',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'status' => BookingHeader::STATUS_PENDING,
            ]);

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => $space->id,
                'hyve_room_id' => $room->id,
                'booking_date' => $bookingDate,
                'start_time' => '10:00',
                'end_time' => '12:00',
                'guests' => 2,
                'status' => BookingDetail::STATUS_PENDING,
            ]);
        });

        $response = $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'full_name' => 'Second Customer',
            'email' => 'second@example.com',
            'phone' => '+639181112225',
            'hyve_room_id' => $room->id,
            'booking_date' => $bookingDate,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'guests' => 2,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors([
            'end_time' => 'Sorry, another customer booked this schedule moments ago. Please select another available time.',
        ]);
        $this->assertTrue($conflictInjected);
        $this->assertDatabaseMissing('booking_headers', ['email' => 'second@example.com']);
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
            'rules_agreement' => '1',
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
                    'rules_agreement' => '1',
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

    public function test_member_can_open_the_balance_payment_page_for_their_booking(): void
    {
        $user = User::factory()->create();

        $header = BookingHeader::query()->create([
            'user_id' => $user->id,
            'reference_no' => 'HYVE-BAL-000001',
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'booking_type' => BookingHeader::TYPE_MEMBER,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'payment_status' => 'pending_verification',
            'total_amount' => 1598,
            'downpayment_amount' => 500,
            'balance_amount' => 1098,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $spaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $spaceId,
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'subtotal' => 1598,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('member.bookings.balance-payment', ['bookingHeader' => $header, 'detail' => $header->details()->first()->id]))
            ->assertOk()
            ->assertSee('Pay Remaining Balance')
            ->assertSee('Pay selected booking only')
            ->assertSee('Pay all remaining');
    }

    public function test_member_can_submit_a_remaining_balance_payment(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $spaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

        $header = BookingHeader::query()->create([
            'user_id' => $user->id,
            'reference_no' => 'HYVE-BAL-000002',
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'booking_type' => BookingHeader::TYPE_MEMBER,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'payment_status' => 'pending_verification',
            'total_amount' => 1598,
            'downpayment_amount' => 500,
            'balance_amount' => 1098,
            'status' => BookingHeader::STATUS_PENDING,
            'notes' => 'Initial booking.',
        ]);

        $detail = BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $spaceId,
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'subtotal' => 1598,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)
            ->post(route('member.bookings.balance-payment.store', $header), [
                'payment_scope' => 'single',
                'detail_id' => $detail->id,
                'payment_method' => 'bank_transfer',
                'rules_agreement' => '1',
                'payment_proof' => UploadedFile::fake()->create('balance-proof.png', 120, 'image/png'),
                'notes' => 'Paid the remaining balance today.',
            ]);

        $response->assertRedirect(route('member.index'));
        $response->assertSessionHas('member_success');

        $header->refresh();

        $this->assertSame(1598.00, (float) $header->downpayment_amount);
        $this->assertSame(0.00, (float) $header->balance_amount);
        $this->assertSame('bank_transfer', $header->payment_method);
        $this->assertSame('pending_balance_verification', $header->payment_status);
        $this->assertStringContainsString('Paid the remaining balance today.', (string) $header->notes);
        Storage::disk('public')->assertExists((string) $header->payment_proof_path);
    }

    public function test_member_can_submit_only_the_selected_booking_balance_without_paying_all(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $roomOne = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $roomTwo = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();

        $header = BookingHeader::query()->create([
            'user_id' => $user->id,
            'reference_no' => 'HYVE-BAL-000003',
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'booking_type' => BookingHeader::TYPE_MEMBER,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'payment_status' => 'pending_verification',
            'total_amount' => 1897,
            'downpayment_amount' => 500,
            'balance_amount' => 1397,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        $spaceOneId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');
        $spaceTwoId = Space::query()->where('slug', 'fortitude-office-2-seats')->value('id');

        $detailOne = BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $spaceOneId,
            'hyve_room_id' => $roomOne->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'subtotal' => 1598,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => $spaceTwoId,
            'hyve_room_id' => $roomTwo->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'subtotal' => 299,
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)
            ->post(route('member.bookings.balance-payment.store', $header), [
                'payment_scope' => 'single',
                'detail_id' => $detailOne->id,
                'payment_method' => 'gcash',
                'rules_agreement' => '1',
                'payment_proof' => UploadedFile::fake()->create('single-balance-proof.png', 120, 'image/png'),
                'notes' => 'Pay only the selected booking.',
            ]);

        $response->assertRedirect(route('member.index'));

        $header->refresh();

        $this->assertSame(1897.00, (float) $header->downpayment_amount);
        $this->assertSame(0.00, (float) $header->balance_amount);
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
                'label' => '4:30 PM - 6:30 PM',
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
            'value' => '10:00',
            'duration_label' => '2 hours',
        ]);
    }

    public function test_book_by_room_offers_next_day_end_times_for_an_overnight_booking(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $bookingDate = now()->addDays(2)->toDateString();

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => $bookingDate,
            'start_time' => '23:00',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'value' => '03:00',
            'label' => '3:00 AM next day',
            'duration_label' => '4 hours',
        ]);
    }

    public function test_overnight_booking_saves_the_actual_next_day_end_date(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $bookingDate = now()->addDays(2);

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'booking_mode' => 'room',
            'full_name' => 'Overnight Customer',
            'email' => 'overnight@example.com',
            'phone' => '09171234567',
            'hyve_room_id' => $room->id,
            'booking_date' => $bookingDate->toDateString(),
            'start_time' => '23:00',
            'end_time' => '03:00',
            'guests' => 4,
            'downpayment_amount' => 0,
            'payment_method' => 'pay_later',
        ]);

        $response->assertRedirect(route('admin.bookings.index'));
        $detail = BookingDetail::query()->where('hyve_room_id', $room->id)->latest('id')->firstOrFail();
        $this->assertSame($bookingDate->toDateString(), $detail->booking_date->toDateString());
        $this->assertSame($bookingDate->copy()->addDay()->toDateString(), $detail->booking_end_date->toDateString());
        $this->assertSame('23:00', (string) $detail->start_time);
        $this->assertSame('03:00', (string) $detail->end_time);
    }

    public function test_overnight_booking_blocks_its_early_morning_time_on_the_next_date(): void
    {
        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $bookingDate = now()->addDays(2);
        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-OVERNIGHT-001',
            'customer_name' => 'Overnight Client',
            'email' => 'overnight-conflict@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        BookingDetail::query()->create([
            'booking_header_id' => $header->id,
            'space_id' => Space::query()->where('slug', 'zeal-room-8-seats')->value('id'),
            'hyve_room_id' => $room->id,
            'booking_date' => $bookingDate->toDateString(),
            'booking_end_date' => $bookingDate->copy()->addDay()->toDateString(),
            'start_time' => '23:00',
            'end_time' => '03:00',
            'guests' => 4,
            'charge_period' => 'hourly',
            'status' => BookingDetail::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('bookings.availability', [
            'hyve_room_id' => $room->id,
            'booking_date' => $bookingDate->copy()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonMissing(['value' => '01:00']);
        $response->assertJsonFragment(['value' => '03:00']);
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
            'value' => '18:30',
            'duration_label' => '2 hours',
        ]);

        Carbon::setTestNow();
    }

    public function test_regular_booking_requires_a_minimum_of_two_hours(): void
    {
        Storage::fake('public');

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'full_name' => 'Minimum Duration Guest',
            'email' => 'minimum@example.com',
            'phone' => '+639181112999',
            'hyve_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'guests' => 2,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'notes' => 'Trying to book for only one hour.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors('end_time');
    }

    public function test_full_schedule_booking_requires_at_least_two_hours_total(): void
    {
        Storage::fake('public');

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();

        $response = $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'booking_mode' => 'schedule',
            'selected_schedule_items' => json_encode([
                [
                    'hyve_room_id' => $room->id,
                    'booking_date' => now()->addDay()->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ],
            ], JSON_THROW_ON_ERROR),
            'guests' => 1,
            'downpayment_amount' => 500,
            'payment_method' => 'gcash',
            'rules_agreement' => '1',
            'payment_proof' => UploadedFile::fake()->create('proof.png', 120, 'image/png'),
            'full_name' => 'Schedule Minimum Guest',
            'email' => 'schedule-minimum@example.com',
            'phone' => '+639181111000',
            'notes' => 'Trying to book only one schedule hour.',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasErrors([
            'selected_schedule_items' => 'Select at least 2 hours of schedule slots before continuing to checkout.',
        ]);
    }
}
