<?php

namespace Tests\Feature;

use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingWifiVoucher;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Services\BookingWifiVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingWifiVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_booking_generates_a_wifi_voucher_with_booking_window(): void
    {
        Carbon::setTestNow('2026-06-26 09:00:00');

        try {
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-WIFI-000001',
                'customer_name' => 'Voucher Guest',
                'email' => 'voucher@example.com',
                'phone' => '+639181234567',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'gcash',
                'status' => 'confirmed',
            ]);

            $conferenceRoom = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
            $roomOne = HyveRoom::query()->where('room_name', 'Room 1')->firstOrFail();
            $conferenceSpaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');
            $roomOneSpaceId = Space::query()->where('slug', 'fortitude-office-2-seats')->value('id');

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => $conferenceSpaceId,
                'hyve_room_id' => $conferenceRoom->id,
                'booking_date' => '2026-06-27',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'guests' => 4,
                'subtotal' => 1598,
                'status' => BookingDetail::STATUS_CONFIRMED,
            ]);

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => $roomOneSpaceId,
                'hyve_room_id' => $roomOne->id,
                'booking_date' => '2026-06-27',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'guests' => 2,
                'subtotal' => 299,
                'status' => BookingDetail::STATUS_CONFIRMED,
            ]);

            $voucher = app(BookingWifiVoucherService::class)->ensureVoucherForBooking($header->fresh('details'));

            $this->assertNotNull($voucher);
            $this->assertStringStartsWith('HYVE-WIFI-', (string) $voucher->code);
            $this->assertSame((string) $voucher->code, (string) $voucher->username);
            $this->assertSame((string) $voucher->code, (string) $voucher->password);
            $this->assertSame(240, (int) $voucher->access_minutes);
            $this->assertSame('2026-06-27 08:00:00', $voucher->valid_from?->format('Y-m-d H:i:s'));
            $this->assertSame('2026-06-27 12:00:00', $voucher->valid_until?->format('Y-m-d H:i:s'));
            $this->assertSame(BookingWifiVoucher::STATUS_READY, (string) $voucher->status);
            $this->assertSame('pending_device', (string) $voucher->sync_status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_payload_for_booking_exposes_wifi_voucher_details_for_the_modal(): void
    {
        Carbon::setTestNow('2026-06-26 09:00:00');

        try {
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-WIFI-000002',
                'customer_name' => 'Payload Guest',
                'email' => 'payload@example.com',
                'phone' => '+639181234568',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'gcash',
                'status' => 'confirmed',
            ]);

            $conferenceRoom = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
            $conferenceSpaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => $conferenceSpaceId,
                'hyve_room_id' => $conferenceRoom->id,
                'booking_date' => '2026-06-27',
                'start_time' => '13:00',
                'end_time' => '15:00',
                'guests' => 3,
                'subtotal' => 1598,
                'status' => BookingDetail::STATUS_CONFIRMED,
            ]);

            $service = app(BookingWifiVoucherService::class);
            $service->ensureVoucherForBooking($header->fresh('details'));
            $payload = $service->payloadForBooking($header->fresh('wifiVoucher'));

            $this->assertNotNull($payload);
            $this->assertSame('Ready', $payload['status_label']);
            $this->assertSame('MIKROTIK', $payload['provider']);
            $this->assertSame('2 hours', $payload['access_window']);
            $this->assertSame('Waiting for MikroTik device', $payload['sync_status']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_revoke_marks_the_wifi_voucher_as_revoked(): void
    {
        Carbon::setTestNow('2026-06-26 09:00:00');

        try {
            $header = BookingHeader::query()->create([
                'reference_no' => 'HYVE-WIFI-000003',
                'customer_name' => 'Revoke Guest',
                'email' => 'revoke@example.com',
                'phone' => '+639181234569',
                'booking_type' => BookingHeader::TYPE_GUEST,
                'source' => BookingHeader::SOURCE_WEB,
                'payment_method' => 'gcash',
                'status' => 'confirmed',
            ]);

            $conferenceRoom = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
            $conferenceSpaceId = Space::query()->where('slug', 'zeal-room-8-seats')->value('id');

            BookingDetail::query()->create([
                'booking_header_id' => $header->id,
                'space_id' => $conferenceSpaceId,
                'hyve_room_id' => $conferenceRoom->id,
                'booking_date' => '2026-06-27',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'guests' => 2,
                'subtotal' => 1598,
                'status' => BookingDetail::STATUS_CONFIRMED,
            ]);

            $service = app(BookingWifiVoucherService::class);
            $service->ensureVoucherForBooking($header->fresh('details'));
            $service->revokeVoucherForBooking($header->fresh('wifiVoucher'));

            $voucher = $header->fresh('wifiVoucher')->wifiVoucher;

            $this->assertNotNull($voucher);
            $this->assertSame(BookingWifiVoucher::STATUS_REVOKED, (string) $voucher->status);
            $this->assertSame('pending_device', (string) $voucher->sync_status);
            $this->assertNotNull($voucher->revoked_at);
        } finally {
            Carbon::setTestNow();
        }
    }
}
