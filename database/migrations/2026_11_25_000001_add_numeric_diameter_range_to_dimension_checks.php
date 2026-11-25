<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_test_dimension_checks', function (Blueprint $table) {
            $table->decimal('measured_value_min', 10, 2)->nullable();
            $table->decimal('measured_value_max', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calibration_test_dimension_checks', function (Blueprint $table) {
            $table->dropColumn(['measured_value_min', 'measured_value_max']);
        });
    }
};
