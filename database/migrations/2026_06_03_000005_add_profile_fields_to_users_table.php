<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('users');

        Schema::table('users', function (Blueprint $table) use ($columns) {
            if (! in_array('username', $columns, true)) {
                $table->string('username')->nullable()->after('id');
            }

            if (! in_array('first_name', $columns, true)) {
                $table->string('first_name')->nullable()->after(in_array('username', $columns, true) ? 'username' : 'id');
            }

            if (! in_array('last_name', $columns, true)) {
                $table->string('last_name')->nullable()->after(in_array('first_name', $columns, true) ? 'first_name' : 'id');
            }

            if (! in_array('phone', $columns, true)) {
                $table->string('phone', 30)->nullable()->after('email');
            }

            if (! in_array('status', $columns, true)) {
                $table->unsignedTinyInteger('status')->default(0)->after(in_array('phone', $columns, true) ? 'phone' : 'email');
            }
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                $existingRow = DB::table('users')->where('id', $user->id)->first();
                $firstName = $existingRow->first_name ?? $parts[0] ?? 'User';
                $legacyLastName = $existingRow->lastname ?? null;
                $lastName = $existingRow->last_name ?? $legacyLastName ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Account');
                $legacyPhone = $existingRow->cell_number ?? null;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'username' => $existingRow->username ?? 'user'.$user->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $existingRow->phone ?? $legacyPhone,
                        'status' => 0,
                    ]);
            });

        $indexes = collect(Schema::getIndexes('users'));
        $hasUsernameUnique = $indexes->contains(function (array $index): bool {
            return ($index['name'] ?? null) === 'users_username_unique';
        });

        if (! $hasUsernameUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'first_name', 'last_name', 'phone', 'status']);
        });
    }
};
