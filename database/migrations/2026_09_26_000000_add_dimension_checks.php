<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specifications', function (Blueprint $table) {
            $table->decimal('length_min', 12, 4)->nullable();
            $table->decimal('length_max', 12, 4)->nullable();
            $table->decimal('width_min', 12, 4)->nullable();
            $table->decimal('width_max', 12, 4)->nullable();
            $table->decimal('diameter_min', 12, 4)->nullable();
            $table->decimal('diameter_max', 12, 4)->nullable();
            $table->string('dimension_unit', 20)->nullable();
        });

        Schema::create('calibration_test_dimension_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_test_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 20);
            $table->string('label');
            $table->decimal('min_value', 12, 4);
            $table->decimal('max_value', 12, 4);
            $table->decimal('measured_value', 12, 4);
            $table->string('unit', 20);
            $table->boolean('is_within_range');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_test_dimension_checks');

        Schema::table('specifications', function (Blueprint $table) {
            $table->dropColumn([
                'length_min', 'length_max', 'width_min', 'width_max',
                'diameter_min', 'diameter_max', 'dimension_unit',
            ]);
        });
    }
};
