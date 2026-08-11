<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('sender_user_id')
                ->constrained('support_messages')
                ->nullOnDelete();
        });

        Schema::create('support_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();
            $table->string('actor_key', 80);
            $table->string('actor_type', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('booking_users')->nullOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['support_message_id', 'actor_key'], 'support_reaction_actor_unique');
            $table->index(['support_message_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_message_reactions');

        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_message_id');
        });
    }
};
