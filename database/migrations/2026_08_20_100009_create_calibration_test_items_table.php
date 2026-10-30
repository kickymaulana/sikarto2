<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_test_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_test_id')->constrained()->cascadeOnDelete();
            $table->decimal('standard_value', 12, 4);
            $table->decimal('reading_value', 12, 4);
            $table->decimal('correction', 12, 4);
            $table->boolean('is_within_limit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_test_items');
    }
};
