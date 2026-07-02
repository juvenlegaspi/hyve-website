<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hyve_schedule_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('hyve_schedule_overrides', 'booking_date')) {
                $table->dropUnique('hyve_schedule_overrides_booking_date_unique');
            }

            if (! Schema::hasColumn('hyve_schedule_overrides', 'hyve_room_id')) {
                $table->foreignId('hyve_room_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('hyve_rooms')
                    ->nullOnDelete();
            }

            $table->index(['hyve_room_id', 'booking_date'], 'hyve_schedule_overrides_room_date_index');
            $table->unique(['hyve_room_id', 'booking_date'], 'hyve_schedule_overrides_room_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hyve_schedule_overrides', function (Blueprint $table) {
            $table->dropUnique('hyve_schedule_overrides_room_date_unique');
            $table->dropIndex('hyve_schedule_overrides_room_date_index');

            if (Schema::hasColumn('hyve_schedule_overrides', 'hyve_room_id')) {
                $table->dropConstrainedForeignId('hyve_room_id');
            }

            $table->unique('booking_date', 'hyve_schedule_overrides_booking_date_unique');
        });
    }
};
