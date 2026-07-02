<?php

use App\Models\BookingPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('booking_headers')
            ->leftJoin('booking_payments', 'booking_payments.booking_header_id', '=', 'booking_headers.id')
            ->whereNull('booking_payments.id')
            ->whereNotNull('booking_headers.downpayment_amount')
            ->where('booking_headers.downpayment_amount', '>', 0)
            ->select([
                'booking_headers.id',
                'booking_headers.user_id',
                'booking_headers.payment_method',
                'booking_headers.payment_status',
                'booking_headers.payment_proof_path',
                'booking_headers.payment_proof_name',
                'booking_headers.downpayment_amount',
                'booking_headers.created_at',
                'booking_headers.updated_at',
            ])
            ->get();

        foreach ($rows as $row) {
            $status = match ((string) ($row->payment_status ?? 'pending_verification')) {
                'paid', 'partially_paid' => BookingPayment::STATUS_APPROVED,
                'rejected' => BookingPayment::STATUS_REJECTED,
                default => BookingPayment::STATUS_PENDING,
            };

            DB::table('booking_payments')->insert([
                'booking_header_id' => $row->id,
                'booking_detail_id' => null,
                'user_id' => $row->user_id,
                'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
                'amount' => $row->downpayment_amount,
                'payment_method' => $row->payment_method ?: 'gcash',
                'status' => $status,
                'payment_proof_path' => $row->payment_proof_path,
                'payment_proof_name' => $row->payment_proof_name,
                'notes' => 'Backfilled from existing booking downpayment record.',
                'review_notes' => null,
                'paid_at' => $row->created_at,
                'verified_at' => in_array($status, [BookingPayment::STATUS_APPROVED, BookingPayment::STATUS_REJECTED], true)
                    ? $row->updated_at
                    : null,
                'verified_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('booking_payments')
            ->where('notes', 'Backfilled from existing booking downpayment record.')
            ->delete();
    }
};
