<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_test_dimension_checks', function (Blueprint $table) {
            $table->string('measured_value_text', 255)->nullable();
            $table->decimal('measured_value', 12, 4)->nullable()->change();
            $table->boolean('is_within_range')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('calibration_test_dimension_checks')->whereNotNull('measured_value_text')->exists()) {
            throw new RuntimeException('Cannot rollback: textual diameter values would be lost.');
        }

        Schema::table('calibration_test_dimension_checks', function (Blueprint $table) {
            $table->dropColumn('measured_value_text');
            $table->decimal('measured_value', 12, 4)->nullable(false)->change();
            $table->boolean('is_within_range')->nullable(false)->change();
        });
    }
};
