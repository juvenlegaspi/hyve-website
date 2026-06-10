<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 30)->nullable()->after('id');
            });

            DB::table('users')
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('id')
                ->get()
                ->each(function (object $user): void {
                    $base = strtolower(trim(($user->first_name ?? 'user').($user->last_name ? '.'.$user->last_name : '')));
                    $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'username' => $base.$user->id,
                        ]);
                });

            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
