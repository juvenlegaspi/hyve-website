<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_booking_notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_activity_id')->constrained('booking_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('booking_users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['booking_activity_id', 'user_id'], 'member_booking_notification_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_booking_notification_reads');
    }
};
