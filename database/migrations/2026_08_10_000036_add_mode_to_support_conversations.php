<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            // Existing conversations remain assigned to the Front Desk after deployment.
            $table->string('mode', 24)->default('front_desk')->after('status');
            $table->index(['mode', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropIndex(['mode', 'status', 'last_message_at']);
            $table->dropColumn('mode');
        });
    }
};
