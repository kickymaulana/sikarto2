<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('standard_templates')->leftJoin('capacities', 'capacities.id', '=', 'standard_templates.capacity_id')->whereNull('capacities.id')->exists()) {
            throw new RuntimeException('Standard templates contain null or orphan capacity_id; assign valid capacities before migrating.');
        }

        Schema::create('standard_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capacity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('reference_media');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->foreignId('standard_group_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::transaction(function () {
            foreach (DB::table('standard_templates')->distinct()->pluck('capacity_id') as $capacityId) {
                $groupId = DB::table('standard_groups')->insertGetId([
                    'capacity_id' => $capacityId,
                    'name' => 'Penimbangan',
                    'reference_media' => 'Anak Timbangan',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('standard_templates')->where('capacity_id', $capacityId)->update(['standard_group_id' => $groupId]);
            }
        });

        Schema::table('standard_templates', function (Blueprint $table) {
            $table->dropForeign(['standard_group_id']);
        });
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('standard_group_id')->nullable(false)->change();
            $table->foreign('standard_group_id')->references('id')->on('standard_groups')->cascadeOnDelete();
            $table->dropForeign(['capacity_id']);
            $table->dropColumn('capacity_id');
        });
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->string('computed_status')->nullable();
            $table->string('selected_status')->nullable();
            $table->decimal('min_correction_snapshot', 12, 4)->nullable();
            $table->decimal('max_correction_snapshot', 12, 4)->nullable();
        });
        Schema::table('calibration_test_items', function (Blueprint $table) {
            $table->unsignedInteger('group_order')->nullable();
            $table->string('group_name')->nullable();
            $table->string('reference_media')->nullable();
            $table->string('unit', 20)->nullable();
            $table->unsignedInteger('point_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->foreignId('capacity_id')->nullable()->constrained()->cascadeOnDelete();
        });
        foreach (DB::table('standard_groups')->get() as $group) {
            DB::table('standard_templates')->where('standard_group_id', $group->id)->update(['capacity_id' => $group->capacity_id]);
        }
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->dropForeign(['standard_group_id']);
            $table->dropColumn('standard_group_id');
        });
        Schema::dropIfExists('standard_groups');
        Schema::table('calibration_test_items', function (Blueprint $table) {
            $table->dropColumn(['group_order', 'group_name', 'reference_media', 'unit', 'point_order']);
        });
        Schema::table('calibration_tests', function (Blueprint $table) {
            $table->dropColumn(['computed_status', 'selected_status', 'min_correction_snapshot', 'max_correction_snapshot']);
        });
    }
};
