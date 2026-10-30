<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->decimal('avg_correction', 12, 4)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->dropColumn('avg_correction');
        });
    }
};
