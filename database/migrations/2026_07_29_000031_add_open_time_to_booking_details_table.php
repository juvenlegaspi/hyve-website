<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->boolean('is_open_time')->default(false)->after('progress_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->dropIndex(['is_open_time']);
            $table->dropColumn('is_open_time');
        });
    }
};
