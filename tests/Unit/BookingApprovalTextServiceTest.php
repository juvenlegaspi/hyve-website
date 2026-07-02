<?php

namespace Tests\Unit;

use App\Services\BookingApprovalTextService;
use ReflectionClass;
use Tests\TestCase;

class BookingApprovalTextServiceTest extends TestCase
{
    public function test_it_builds_a_clear_single_booking_approval_message(): void
    {
        $service = new BookingApprovalTextService();
        $message = $this->invokePrivateMethod($service, 'buildMessage', [[
            'reference_no' => 'HYVE-20260626-ABC123',
            'customer_name' => 'Maria',
            'balance_amount' => '1098.00',
            'single_line' => [
                'room_name' => 'Conference Room',
                'date' => 'June 27, 2026',
                'time' => '8:00 AM - 10:00 AM',
            ],
        ]]);

        $this->assertSame(
            'Hi Maria! Your HYVE booking is approved. Ref: HYVE-20260626-ABC123. Conference Room on June 27, 2026, 8:00 AM - 10:00 AM. Balance due: Php 1098.00.',
            $message
        );
    }

    public function test_it_builds_a_clear_multi_slot_approval_message(): void
    {
        $service = new BookingApprovalTextService();
        $message = $this->invokePrivateMethod($service, 'buildMessage', [[
            'reference_no' => 'HYVE-20260626-XYZ789',
            'customer_name' => 'Juan',
            'booking_count' => 3,
            'balance_amount' => '149.50',
        ]]);

        $this->assertSame(
            'Hi Juan! Your HYVE booking is approved. Ref: HYVE-20260626-XYZ789. 3 booking slot(s) are now confirmed. Balance due: Php 149.50.',
            $message
        );
    }

    public function test_it_normalizes_local_phone_numbers_for_semaphore(): void
    {
        $service = new BookingApprovalTextService();

        $this->assertSame(
            '639171234567',
            $this->invokePrivateMethod($service, 'normalizePhone', ['0917-123-4567'])
        );

        $this->assertSame(
            '639181112222',
            $this->invokePrivateMethod($service, 'normalizePhone', ['+63 918 111 2222'])
        );
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokePrivateMethod(object $instance, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($instance);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($instance, $arguments);
    }
}
