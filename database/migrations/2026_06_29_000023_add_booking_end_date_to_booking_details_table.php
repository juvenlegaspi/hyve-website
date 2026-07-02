<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_details') || Schema::hasColumn('booking_details', 'booking_end_date')) {
            return;
        }

        Schema::table('booking_details', function (Blueprint $table) {
            $table->date('booking_end_date')->nullable()->after('booking_date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_details') || ! Schema::hasColumn('booking_details', 'booking_end_date')) {
            return;
        }

        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropColumn('booking_end_date');
        });
    }
};
