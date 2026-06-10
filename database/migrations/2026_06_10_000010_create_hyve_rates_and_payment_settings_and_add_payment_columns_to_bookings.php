<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hyve_rates')) {
            Schema::create('hyve_rates', function (Blueprint $table) {
                $table->id();
                $table->string('space_slug', 120)->unique();
                $table->string('title', 150);
                $table->json('day_use')->nullable();
                $table->json('night_use')->nullable();
                $table->json('memberships')->nullable();
                $table->unsignedTinyInteger('minimum_hours')->default(2);
                $table->decimal('day_minimum_rate', 10, 2)->default(0);
                $table->decimal('day_succeeding_hour_rate', 10, 2)->default(0);
                $table->decimal('night_minimum_rate', 10, 2)->default(0);
                $table->decimal('night_succeeding_hour_rate', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_settings')) {
            Schema::create('payment_settings', function (Blueprint $table) {
                $table->id();
                $table->string('gcash_account_name', 150)->nullable();
                $table->string('gcash_number', 50)->nullable();
                $table->string('bank_name', 150)->nullable();
                $table->string('bank_account_name', 150)->nullable();
                $table->string('bank_account_number', 80)->nullable();
                $table->decimal('downpayment_percentage', 5, 2)->default(50);
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('booking_headers', 'payment_method')) {
            Schema::table('booking_headers', function (Blueprint $table) {
                $table->string('payment_method', 40)->nullable()->after('source');
                $table->string('payment_status', 40)->nullable()->after('payment_method');
                $table->string('payment_proof_path', 255)->nullable()->after('payment_status');
                $table->string('payment_proof_name', 255)->nullable()->after('payment_proof_path');
                $table->decimal('total_amount', 10, 2)->nullable()->after('payment_proof_name');
                $table->decimal('downpayment_amount', 10, 2)->nullable()->after('total_amount');
                $table->decimal('balance_amount', 10, 2)->nullable()->after('downpayment_amount');
            });
        }

        if (! Schema::hasColumn('booking_details', 'charge_period')) {
            Schema::table('booking_details', function (Blueprint $table) {
                $table->string('charge_period', 40)->nullable()->after('end_time');
                $table->decimal('duration_hours', 6, 2)->nullable()->after('charge_period');
                $table->decimal('billed_hours', 6, 2)->nullable()->after('duration_hours');
            });
        }

        if (DB::table('hyve_rates')->count() === 0) {
            $timestamp = now();
            DB::table('hyve_rates')->insert([
                [
                    'space_slug' => 'common-area',
                    'title' => 'Common Area',
                    'day_use' => json_encode(['2 hrs min' => 'Php 160', 'Succeeding hr' => 'Php 60', 'Daily' => 'Php 355', 'Weekly' => 'Php 2,155']),
                    'night_use' => json_encode(['2 hrs min' => 'Php 190', 'Succeeding hr' => 'Php 70', 'Daily' => 'Php 400', 'Weekly' => 'Php 2,499']),
                    'memberships' => json_encode(['Student Monthly' => 'Php 3,749', 'Regular Monthly' => 'Php 5,749', 'Night Student' => 'Php 5,249', 'Night Regular' => 'Php 6,749']),
                    'minimum_hours' => 2,
                    'day_minimum_rate' => 160,
                    'day_succeeding_hour_rate' => 60,
                    'night_minimum_rate' => 190,
                    'night_succeeding_hour_rate' => 70,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'space_slug' => 'fortitude-office-2-seats',
                    'title' => 'Fortitude Office (2 Seats)',
                    'day_use' => json_encode(['2 hrs min' => 'Php 299', 'Succeeding hr' => 'Php 149', 'Daily' => 'Php 749', 'Weekly' => 'Php 4,495']),
                    'night_use' => json_encode(['2 hrs min' => 'Php 319', 'Succeeding hr' => 'Php 159', 'Daily' => 'Php 849', 'Weekly' => 'Php 5,099']),
                    'memberships' => json_encode(['Monthly Rental' => 'Php 15,599', 'Night Monthly Rental' => 'Php 15,599', 'Night Upgrade Daily' => 'Plus Php 380', 'Night Upgrade Weekly' => 'Plus Php 2,159']),
                    'minimum_hours' => 2,
                    'day_minimum_rate' => 299,
                    'day_succeeding_hour_rate' => 149,
                    'night_minimum_rate' => 319,
                    'night_succeeding_hour_rate' => 159,
                    'sort_order' => 2,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'space_slug' => 'tenacity-office-4-seats',
                    'title' => 'Tenacity Office (4 Seats)',
                    'day_use' => json_encode(['2 hrs min' => 'Php 719', 'Succeeding hr' => 'Php 359', 'Daily' => 'Php 2,159', 'Weekly' => 'Php 12,499']),
                    'night_use' => json_encode(['2 hrs min' => 'Php 789', 'Succeeding hr' => 'Php 389', 'Daily' => 'Php 2,399', 'Weekly' => 'Php 14,399']),
                    'memberships' => json_encode(['Monthly Rental' => 'Php 30,000', 'Night Monthly Rental' => 'Php 30,000', 'Night Upgrade Daily' => 'Plus Php 2,000', 'Night Upgrade Weekly' => 'Plus Php 10,795']),
                    'minimum_hours' => 2,
                    'day_minimum_rate' => 719,
                    'day_succeeding_hour_rate' => 359,
                    'night_minimum_rate' => 789,
                    'night_succeeding_hour_rate' => 389,
                    'sort_order' => 3,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                [
                    'space_slug' => 'zeal-room-8-seats',
                    'title' => 'Zeal Room (8 Seats)',
                    'day_use' => json_encode(['2 hrs min' => 'Php 1,598', 'Succeeding hr' => 'Php 799', 'Daily' => 'Php 4,799', 'Weekly' => 'N/A']),
                    'night_use' => json_encode(['2 hrs min' => 'Php 1,799', 'Succeeding hr' => 'Php 899', 'Daily' => 'Php 5,099', 'Weekly' => 'Php 25,495']),
                    'memberships' => json_encode(['Upgrade 1st hr' => 'Plus Php 799', 'Night Upgrade Daily' => 'Plus Php 4,595', 'TV Usage' => 'Included', 'Monthly Rental' => 'Php 45,000']),
                    'minimum_hours' => 2,
                    'day_minimum_rate' => 1598,
                    'day_succeeding_hour_rate' => 799,
                    'night_minimum_rate' => 1799,
                    'night_succeeding_hour_rate' => 899,
                    'sort_order' => 4,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ]);
        }

        if (DB::table('payment_settings')->count() === 0) {
            DB::table('payment_settings')->insert([
                'gcash_account_name' => 'HYVE Workspace',
                'gcash_number' => '0917 123 4567',
                'bank_name' => 'BDO Sample Account',
                'bank_account_name' => 'HYVE Workspace Inc.',
                'bank_account_number' => '012345678901',
                'downpayment_percentage' => 50,
                'instructions' => 'Send your downpayment first, then upload a clear screenshot of the payment confirmation for verification.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_details', 'billed_hours')) {
            Schema::table('booking_details', function (Blueprint $table) {
                $table->dropColumn(['charge_period', 'duration_hours', 'billed_hours']);
            });
        }

        if (Schema::hasColumn('booking_headers', 'payment_method')) {
            Schema::table('booking_headers', function (Blueprint $table) {
                $table->dropColumn([
                    'payment_method',
                    'payment_status',
                    'payment_proof_path',
                    'payment_proof_name',
                    'total_amount',
                    'downpayment_amount',
                    'balance_amount',
                ]);
            });
        }

        Schema::dropIfExists('payment_settings');
        Schema::dropIfExists('hyve_rates');
    }
};
