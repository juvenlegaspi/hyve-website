<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_header_id')->constrained('booking_headers')->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->nullable()->constrained('booking_details')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('booking_users')->nullOnDelete();
            $table->string('event_key', 60);
            $table->string('event_label', 120);
            $table->string('reference_no', 60)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('room_name')->nullable();
            $table->date('booking_date')->nullable();
            $table->string('time_range', 80)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'created_at']);
            $table->index(['booking_header_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_activities');
    }
};
