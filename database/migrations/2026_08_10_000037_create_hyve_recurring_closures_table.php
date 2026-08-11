<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hyve_recurring_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        DB::table('hyve_recurring_closures')->insert([
            'weekday' => 0,
            'is_active' => true,
            'reason' => 'HYVE is temporarily closed every Sunday.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hyve_recurring_closures');
    }
};
