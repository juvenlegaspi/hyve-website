<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_headers', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_headers', 'discount_code')) {
                $table->string('discount_code', 60)->nullable()->after('total_amount');
            }

            if (! Schema::hasColumn('booking_headers', 'discount_label')) {
                $table->string('discount_label', 120)->nullable()->after('discount_code');
            }

            if (! Schema::hasColumn('booking_headers', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->nullable()->after('discount_label');
            }

            if (! Schema::hasColumn('booking_headers', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_rate');
            }

            if (! Schema::hasColumn('booking_headers', 'discounted_total_amount')) {
                $table->decimal('discounted_total_amount', 10, 2)->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_headers', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('booking_headers', 'discounted_total_amount') ? 'discounted_total_amount' : null,
                Schema::hasColumn('booking_headers', 'discount_amount') ? 'discount_amount' : null,
                Schema::hasColumn('booking_headers', 'discount_rate') ? 'discount_rate' : null,
                Schema::hasColumn('booking_headers', 'discount_label') ? 'discount_label' : null,
                Schema::hasColumn('booking_headers', 'discount_code') ? 'discount_code' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
