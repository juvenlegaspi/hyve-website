<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('booking_users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('priority', 20)->default('info');
            $table->timestamp('published_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'published_at', 'expires_at'], 'member_announcements_publish_index');
        });

        Schema::create('member_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_announcement_id')->constrained('member_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('booking_users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['member_announcement_id', 'user_id'], 'member_announcement_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_announcement_reads');
        Schema::dropIfExists('member_announcements');
    }
};
