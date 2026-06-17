<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_headers') || ! Schema::hasColumn('booking_headers', 'user_id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = DB::connection()->getDatabaseName();
        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'booking_headers')
            ->where('COLUMN_NAME', 'user_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if (! $foreignKey) {
            return;
        }

        Schema::table('booking_headers', function (Blueprint $table) use ($foreignKey) {
            $table->dropForeign($foreignKey);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_headers') || ! Schema::hasTable('booking_users')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = DB::connection()->getDatabaseName();
        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'booking_headers')
            ->where('COLUMN_NAME', 'user_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($foreignKey) {
            return;
        }

        Schema::table('booking_headers', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('booking_users')->nullOnDelete();
        });
    }
};
