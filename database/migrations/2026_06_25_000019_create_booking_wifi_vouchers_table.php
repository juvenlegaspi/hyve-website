<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_wifi_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_header_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('mikrotik');
            $table->string('code', 80)->unique();
            $table->string('username', 80)->nullable();
            $table->string('password', 80)->nullable();
            $table->string('status', 30)->default('ready');
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->unsignedInteger('access_minutes')->default(0);
            $table->string('sync_status', 40)->default('pending_device');
            $table->string('mikrotik_user_ref')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('booking_header_id');
            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_wifi_vouchers');
    }
};
