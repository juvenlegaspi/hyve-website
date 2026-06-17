<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_users')) {
            return;
        }

        Schema::create('booking_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 30)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('number', 20)->nullable();
            $table->string('password', 255);
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_users');
    }
};
