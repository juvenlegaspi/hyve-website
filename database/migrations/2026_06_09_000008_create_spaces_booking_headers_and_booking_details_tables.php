<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->string('tag', 100);
            $table->text('description');
            $table->string('image')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('booking_type', 30)->default('guest');
            $table->string('source', 30)->default('web');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
            $table->index(['status', 'booking_type']);
        });

        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_header_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('guests')->default(1);
            $table->string('rate_name', 120)->nullable();
            $table->decimal('rate_amount', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
            $table->index(['booking_date', 'space_id', 'status']);
        });

        DB::table('spaces')->insert([
            [
                'slug' => 'common-area',
                'name' => 'Common Area',
                'tag' => 'Open Workspace',
                'description' => 'A welcoming shared workspace for independent professionals, remote workers, and students who want energy around them without losing focus.',
                'image' => 'images/about1.jpg',
                'note' => 'Best for productive solo work with a social atmosphere nearby.',
                'capacity' => 12,
                'features' => json_encode(['Fast Wi-Fi', 'Drink inclusions', 'Flexible daily use'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'fortitude-office-2-seats',
                'name' => 'Fortitude Office (2 Seats)',
                'tag' => 'Quiet Collaboration',
                'description' => 'A private room for interviews, one-on-one meetings, focused partner work, and online calls that need a quieter setting.',
                'image' => 'images/about2.jpg',
                'note' => 'A strong fit when privacy and clear conversation matter.',
                'capacity' => 2,
                'features' => json_encode(['Private setup', 'Low-distraction environment', 'Ideal for two'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'tenacity-office-4-seats',
                'name' => 'Tenacity Office (4 Seats)',
                'tag' => 'Small Team Office',
                'description' => 'A dedicated office for small teams that need room for planning, collaboration, and client conversations in a more composed environment.',
                'image' => 'images/about3.jpg',
                'note' => 'Designed for teams that want structure without losing flexibility.',
                'capacity' => 4,
                'features' => json_encode(['4-seat setup', 'Daily and monthly options', 'Team-ready atmosphere'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'zeal-room-8-seats',
                'name' => 'Zeal Room (8 Seats)',
                'tag' => 'Conference Ready',
                'description' => 'A larger room for presentations, strategy sessions, workshops, and meetings where the right setting helps people engage with confidence.',
                'image' => 'images/office.png',
                'note' => 'Built for collaborative sessions that need presence and clarity.',
                'capacity' => 8,
                'features' => json_encode(['8-seat capacity', 'TV usage included', 'Presentation-ready setup'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_details');
        Schema::dropIfExists('booking_headers');
        Schema::dropIfExists('spaces');
    }
};
