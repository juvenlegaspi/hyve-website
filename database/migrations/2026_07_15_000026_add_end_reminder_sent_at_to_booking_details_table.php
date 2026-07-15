<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_details') || Schema::hasColumn('booking_details', 'end_reminder_sent_at')) {
            return;
        }

        Schema::table('booking_details', function (Blueprint $table): void {
            $table->timestamp('end_reminder_sent_at')->nullable()->after('actual_end_at')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_details') || ! Schema::hasColumn('booking_details', 'end_reminder_sent_at')) {
            return;
        }

        Schema::table('booking_details', function (Blueprint $table): void {
            $table->dropColumn('end_reminder_sent_at');
        });
    }
};
