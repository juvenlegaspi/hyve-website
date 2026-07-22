<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->after('booking_header_id');
            $table->unsignedSmallInteger('submission_item_index')->nullable()->after('submission_token');
            $table->unique(['submission_token', 'submission_item_index'], 'booking_details_submission_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->dropUnique('booking_details_submission_item_unique');
            $table->dropColumn(['submission_token', 'submission_item_index']);
        });
    }
};
