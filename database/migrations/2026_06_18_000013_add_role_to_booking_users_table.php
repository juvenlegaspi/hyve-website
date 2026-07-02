<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_users', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_users', 'role')) {
                $table->string('role', 20)->default('member')->after('status');
            }
        });

        DB::table('booking_users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'member']);

        $superAdminEmail = 'superadmin@hyve.local';

        if (! DB::table('booking_users')->where('email', $superAdminEmail)->exists()) {
            DB::table('booking_users')->insert([
                'username' => 'superadmin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => $superAdminEmail,
                'number' => '09171234567',
                'password' => Hash::make('Admin@12345'),
                'status' => 0,
                'role' => 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('booking_users')->where('email', 'superadmin@hyve.local')->delete();

        Schema::table('booking_users', function (Blueprint $table) {
            if (Schema::hasColumn('booking_users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
