<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptable_limits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('min_correction', 12, 4);
            $table->decimal('max_correction', 12, 4);
            $table->string('unit');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptable_limits');
    }
};
