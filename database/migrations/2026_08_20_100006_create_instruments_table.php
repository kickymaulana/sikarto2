<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('factory_id')->constrained();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('instrument_type_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('capacity_id')->constrained();
            $table->foreignId('acceptable_limit_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
