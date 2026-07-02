<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hyve_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('type', 20);
            $table->string('scope', 20)->default('all_rooms');
            $table->string('source', 20)->default('admin');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->boolean('all_day')->default(true);
            $table->boolean('affects_booking')->default(false);
            $table->boolean('status')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('hyve_calendar_event_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hyve_calendar_event_id')->constrained('hyve_calendar_events')->cascadeOnDelete();
            $table->foreignId('hyve_room_id')->constrained('hyve_rooms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hyve_calendar_event_id', 'hyve_room_id'], 'hyve_calendar_event_room_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hyve_calendar_event_room');
        Schema::dropIfExists('hyve_calendar_events');
    }
};
