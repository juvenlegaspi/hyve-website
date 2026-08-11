<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hyve_calendar_events', function (Blueprint $table): void {
            $table->boolean('show_to_members')->default(false)->after('affects_booking')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hyve_calendar_events', function (Blueprint $table): void {
            $table->dropIndex(['show_to_members']);
            $table->dropColumn('show_to_members');
        });
    }
};
