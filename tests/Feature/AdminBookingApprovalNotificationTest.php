<?php

namespace Tests\Feature;

use App\Mail\BookingApprovedMail;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminBookingApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_booking_line_sends_email_and_sms_to_the_customer(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 12345, 'status' => 'Pending'],
            ]),
        ]);

        config()->set('services.semaphore.api_key', 'test-api-key');
        config()->set('services.semaphore.sender_name', 'HYVE');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-APPROVAL-0001',
            'customer_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '0917-123-4567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'total_amount' => 1598,
            'downpayment_amount' => 500,
            'balance_amount' => 1098,
            'status' => 'pending',
        ]);

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $spaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

        $detail = BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $spaceId,
            'hyve_room_id' => $room->getKey(),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 4,
            'subtotal' => 1598,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.booking-details.approve', $detail))
            ->assertOk()
            ->assertJsonPath('detail.status', 'Approved');

        Mail::assertSent(BookingApprovedMail::class, function (BookingApprovedMail $mail): bool {
            return $mail->hasTo('maria@example.com')
                && $mail->context['reference_no'] === 'HYVE-APPROVAL-0001'
                && $mail->context['balance_amount'] === 1098.0;
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['apikey'] === 'test-api-key'
                && $request['number'] === '639171234567'
                && $request['sendername'] === 'HYVE'
                && str_contains((string) $request['message'], 'Your HYVE booking is approved')
                && str_contains((string) $request['message'], 'HYVE-APPROVAL-0001');
        });
    }
}
