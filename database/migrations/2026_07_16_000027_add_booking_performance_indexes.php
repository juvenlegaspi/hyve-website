<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->index(
                ['status', 'actual_end_at', 'booking_date'],
                'booking_details_status_end_date_index',
            );
            $table->index(
                ['hyve_room_id', 'status', 'booking_date', 'booking_end_date'],
                'booking_details_room_status_dates_index',
            );
        });

        Schema::table('booking_headers', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'booking_headers_status_created_index');
            $table->index(['payment_status', 'created_at'], 'booking_headers_payment_created_index');
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->index(['status', 'verified_at'], 'booking_payments_status_verified_index');
            $table->index(['status', 'created_at'], 'booking_payments_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropIndex('booking_payments_status_verified_index');
            $table->dropIndex('booking_payments_status_created_index');
        });

        Schema::table('booking_headers', function (Blueprint $table): void {
            $table->dropIndex('booking_headers_status_created_index');
            $table->dropIndex('booking_headers_payment_created_index');
        });

        Schema::table('booking_details', function (Blueprint $table): void {
            $table->dropIndex('booking_details_status_end_date_index');
            $table->dropIndex('booking_details_room_status_dates_index');
        });
    }
};
