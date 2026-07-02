<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->boolean('mikrotik_enabled')->default(false)->after('instructions');
            $table->string('mikrotik_host')->nullable()->after('mikrotik_enabled');
            $table->unsignedSmallInteger('mikrotik_port')->default(8728)->after('mikrotik_host');
            $table->string('mikrotik_username')->nullable()->after('mikrotik_port');
            $table->text('mikrotik_password')->nullable()->after('mikrotik_username');
            $table->string('mikrotik_hotspot_server')->nullable()->after('mikrotik_password');
            $table->string('mikrotik_dns_name')->nullable()->after('mikrotik_hotspot_server');
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mikrotik_enabled',
                'mikrotik_host',
                'mikrotik_port',
                'mikrotik_username',
                'mikrotik_password',
                'mikrotik_hotspot_server',
                'mikrotik_dns_name',
            ]);
        });
    }
};
