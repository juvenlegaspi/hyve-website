<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_header_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('booking_users')->nullOnDelete();
            $table->string('payment_type', 40)->default('balance');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 40);
            $table->string('status', 40)->default('pending');
            $table->string('payment_proof_path', 255)->nullable();
            $table->string('payment_proof_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('booking_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_header_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['payment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
