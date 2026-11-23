<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->json('straightness_checks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->dropColumn('straightness_checks');
        });
    }
};
