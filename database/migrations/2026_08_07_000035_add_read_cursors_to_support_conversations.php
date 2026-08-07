<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_last_read_message_id')->nullable()->after('customer_last_read_at');
            $table->unsignedBigInteger('admin_last_read_message_id')->nullable()->after('admin_last_read_at');
            $table->index('customer_last_read_message_id');
            $table->index('admin_last_read_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropIndex(['customer_last_read_message_id']);
            $table->dropIndex(['admin_last_read_message_id']);
            $table->dropColumn(['customer_last_read_message_id', 'admin_last_read_message_id']);
        });
    }
};
