<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained();
            $table->date('test_date');
            $table->date('next_test_date');
            $table->foreignId('tester_id')->constrained('users');
            $table->string('status'); // PASS, FAIL
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('next_test_date');
            $table->index('instrument_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_tests');
    }
};
