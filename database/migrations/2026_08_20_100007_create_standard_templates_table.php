<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('standard_value', 12, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_templates');
    }
};
