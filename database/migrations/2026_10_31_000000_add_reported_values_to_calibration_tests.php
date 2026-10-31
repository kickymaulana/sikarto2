<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->text('reported_correction')->nullable()->after('avg_correction');
            $table->string('reported_acceptable_limit')->nullable()->after('reported_correction');
        });
    }

    public function down(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->dropColumn(['reported_correction', 'reported_acceptable_limit']);
        });
    }
};
