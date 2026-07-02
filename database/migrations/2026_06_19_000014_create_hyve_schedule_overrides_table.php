<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hyve_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('booking_date')->unique();
            $table->string('mode', 20)->default('default');
            $table->string('opening_time', 5)->nullable();
            $table->string('closing_time', 5)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hyve_schedule_overrides');
    }
};
