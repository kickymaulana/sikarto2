<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_group_id')->nullable()->after('max_correction_snapshot');
            $table->string('selected_group_name')->nullable()->after('selected_group_id');
            $table->string('selected_group_reference_media')->nullable()->after('selected_group_name');
            $table->unsignedInteger('selected_group_order')->nullable()->after('selected_group_reference_media');
        });
    }

    public function down(): void
    {
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->dropColumn([
                'selected_group_id',
                'selected_group_name',
                'selected_group_reference_media',
                'selected_group_order',
            ]);
        });
    }
};
