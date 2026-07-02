<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_settings', 'gcash_qr_path')) {
            Schema::table('payment_settings', function (Blueprint $table): void {
                $table->string('gcash_qr_path', 255)->nullable()->after('gcash_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_settings', 'gcash_qr_path')) {
            Schema::table('payment_settings', function (Blueprint $table): void {
                $table->dropColumn('gcash_qr_path');
            });
        }
    }
};
