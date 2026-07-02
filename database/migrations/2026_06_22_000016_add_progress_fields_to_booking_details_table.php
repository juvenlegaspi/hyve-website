<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->string('progress_status', 30)
                ->default('scheduled')
                ->after('status');
            $table->timestamp('actual_start_at')->nullable()->after('progress_status');
            $table->timestamp('actual_end_at')->nullable()->after('actual_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropColumn([
                'progress_status',
                'actual_start_at',
                'actual_end_at',
            ]);
        });
    }
};
