<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentReceiptMail;
use App\Models\BookingDetail;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPaymentReceiptEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_the_final_payment_sends_a_fully_paid_receipt_with_payment_breakdown(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-16 10:30:00');

        try {
            [$admin, $header, $balancePayment] = $this->createBookingWithPayments(400, 600);

            $this->actingAs($admin)
                ->from(route('admin.sections.payments'))
                ->post(route('admin.payments.approve', $balancePayment))
                ->assertRedirect(route('admin.sections.payments'));

            $header->refresh();
            $this->assertSame('paid', (string) $header->payment_status);
            $this->assertSame(0.0, (float) $header->balance_amount);

            Mail::assertSent(BookingPaymentReceiptMail::class, function (BookingPaymentReceiptMail $mail): bool {
                $renderedReceipt = $mail->render();

                return $mail->hasTo('fully-paid@example.com')
                    && $mail->context['reference_no'] === 'HYVE-RECEIPT-0001'
                    && $mail->context['total_paid_amount'] === 1000.0
                    && $mail->context['balance_amount'] === 0.0
                    && str_contains($renderedReceipt, 'Payment breakdown')
                    && str_contains($renderedReceipt, 'Downpayment')
                    && str_contains($renderedReceipt, 'Final payment')
                    && $mail->context['payment_lines'] === [
                        [
                            'label' => 'Downpayment',
                            'method' => 'Gcash',
                            'paid_at' => 'July 16, 2026 9:00 AM',
                            'amount' => 400.0,
                        ],
                        [
                            'label' => 'Final payment',
                            'method' => 'Bank transfer',
                            'paid_at' => 'July 16, 2026 10:30 AM',
                            'amount' => 600.0,
                        ],
                    ];
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_verifying_a_partial_payment_does_not_send_a_fully_paid_receipt(): void
    {
        Mail::fake();

        [$admin, $header, $balancePayment] = $this->createBookingWithPayments(200, 300);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $balancePayment))
            ->assertRedirect();

        $this->assertSame('partially_paid', (string) $header->fresh()->payment_status);
        $this->assertSame(500.0, (float) $header->fresh()->balance_amount);
        Mail::assertNothingSent();
    }

    /**
     * @return array{User, BookingHeader, BookingPayment}
     */
    private function createBookingWithPayments(float $downpaymentAmount, float $balancePaymentAmount): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $header = BookingHeader::query()->create([
            'reference_no' => 'HYVE-RECEIPT-0001',
            'customer_name' => 'Fully Paid Customer',
            'email' => 'fully-paid@example.com',
            'phone' => '+639171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'gcash',
            'payment_status' => 'partially_paid',
            'total_amount' => 1000,
            'discounted_total_amount' => 1000,
            'downpayment_amount' => $downpaymentAmount,
            'balance_amount' => 1000 - $downpaymentAmount,
            'status' => 'confirmed',
        ]);

        $room = HyveRoom::query()->where('room_name', 'Conference Room')->firstOrFail();
        $space = Space::query()->where('slug', 'zeal-room-8-seats')->firstOrFail();

        BookingDetail::query()->create([
            'booking_header_id' => $header->getKey(),
            'space_id' => $space->getKey(),
            'hyve_room_id' => $room->getKey(),
            'booking_date' => '2026-07-20',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'guests' => 2,
            'subtotal' => 1000,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);

        BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
            'amount' => $downpaymentAmount,
            'payment_method' => 'gcash',
            'status' => BookingPayment::STATUS_APPROVED,
            'paid_at' => '2026-07-16 08:55:00',
            'verified_at' => '2026-07-16 09:00:00',
        ]);

        $balancePayment = BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'payment_type' => BookingPayment::TYPE_BALANCE,
            'amount' => $balancePaymentAmount,
            'payment_method' => 'bank_transfer',
            'status' => BookingPayment::STATUS_PENDING,
            'paid_at' => '2026-07-16 10:15:00',
        ]);

        return [$admin, $header, $balancePayment];
    }
}
