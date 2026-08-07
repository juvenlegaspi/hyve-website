<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('action_type', 40)->nullable()->after('body');
            $table->string('action_label', 100)->nullable()->after('action_type');
            $table->string('action_url', 500)->nullable()->after('action_label');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['action_type', 'action_label', 'action_url']);
        });
    }
};
