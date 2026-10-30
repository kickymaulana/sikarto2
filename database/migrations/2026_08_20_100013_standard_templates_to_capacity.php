<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->foreignId('capacity_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('standard_templates', function (Blueprint $table) {
            $table->dropForeign(['instrument_type_id']);
            $table->dropColumn('instrument_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('standard_templates', function (Blueprint $table) {
            $table->foreignId('instrument_type_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('standard_templates', function (Blueprint $table) {
            $table->dropForeign(['capacity_id']);
            $table->dropColumn('capacity_id');
        });
    }
};
