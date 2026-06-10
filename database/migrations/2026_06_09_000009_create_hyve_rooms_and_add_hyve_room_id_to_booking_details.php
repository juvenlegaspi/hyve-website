<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hyve_rooms')) {
            Schema::create('hyve_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('room_name', 120)->unique();
                $table->string('description', 255)->nullable();
                $table->unsignedTinyInteger('room_status')->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('booking_details', 'hyve_room_id')) {
            Schema::table('booking_details', function (Blueprint $table) {
                $table->foreignId('hyve_room_id')->nullable()->after('space_id')->constrained('hyve_rooms')->nullOnDelete();
                $table->index(['booking_date', 'hyve_room_id', 'status'], 'booking_details_room_date_status_index');
            });
        }

        if (DB::table('hyve_rooms')->count() === 0) {
            $timestamp = now();
            $rows = collect([
                ['Conference Room', 'Main conference and meeting room'],
                ['Room 1', 'Standard private room'],
                ['Room 2', 'Standard private room'],
                ['Room 3', 'Standard private room'],
                ['Room 4', 'Standard private room'],
                ['Room 5', 'Standard private room'],
                ['Room 6', 'Standard private room'],
                ['Room 7', 'Standard private room'],
                ['Table 1-A', 'Shared table area'],
                ['Table 1-B', 'Shared table area'],
                ['Table 1-C', 'Shared table area'],
                ['Table 1-D', 'Shared table area'],
                ['Table 2-A', 'Shared table area'],
                ['Table 2-B', 'Shared table area'],
                ['Table 2-C', 'Shared table area'],
                ['Table 2-D', 'Shared table area'],
                ['Table 2-E', 'Shared table area'],
                ['Table 2-F', 'Shared table area'],
                ['Table 3-A', 'Shared table area'],
                ['Table 3-B', 'Shared table area'],
                ['Table 3-C', 'Shared table area'],
                ['Table 3-D', 'Shared table area'],
                ['Table 3-E', 'Shared table area'],
                ['Table 3-F', 'Shared table area'],
                ['Table 4-A', 'Shared table area'],
                ['Table 4-B', 'Shared table area'],
                ['Table 4-C', 'Shared table area'],
                ['Table 5-A', 'Shared table area'],
                ['Table 5-B', 'Shared table area'],
                ['Table 5-C', 'Shared table area'],
                ['Table 5-D', 'Shared table area'],
                ['Table 5-E', 'Shared table area'],
                ['Table 5-F', 'Shared table area'],
                ['Table 6-A', 'Shared table area'],
                ['Table 6-B', 'Shared table area'],
                ['Table 6-C', 'Shared table area'],
                ['Table 6-D', 'Shared table area'],
                ['Table 7-A', 'Shared table area'],
                ['Table 7-B', 'Shared table area'],
                ['Table 7-C', 'Shared table area'],
                ['Table 7-D', 'Shared table area'],
                ['Table 8-A', 'Shared table area'],
                ['Table 8-B', 'Shared table area'],
                ['Table 8-C', 'Shared table area'],
                ['Table 8-D', 'Shared table area'],
            ])->map(fn (array $row) => [
                'room_name' => $row[0],
                'description' => $row[1],
                'room_status' => 0,
                'status' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all();

            DB::table('hyve_rooms')->insert($rows);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_details', 'hyve_room_id')) {
            Schema::table('booking_details', function (Blueprint $table) {
                $table->dropIndex('booking_details_room_date_status_index');
                $table->dropConstrainedForeignId('hyve_room_id');
            });
        }

        Schema::dropIfExists('hyve_rooms');
    }
};
