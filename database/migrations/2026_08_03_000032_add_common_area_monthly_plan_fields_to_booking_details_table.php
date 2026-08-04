<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->string('long_stay_plan_code')->nullable()->after('charge_period');
            $table->string('long_stay_plan_label')->nullable()->after('long_stay_plan_code');
            $table->string('student_id_reference', 120)->nullable()->after('long_stay_plan_label');
            $table->string('student_id_proof_path')->nullable()->after('student_id_reference');
            $table->string('student_id_proof_name')->nullable()->after('student_id_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table): void {
            $table->dropColumn([
                'long_stay_plan_code',
                'long_stay_plan_label',
                'student_id_reference',
                'student_id_proof_path',
                'student_id_proof_name',
            ]);
        });
    }
};
