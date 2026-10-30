<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('factories', 'code')) {
            Schema::table('factories', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }

        if (Schema::hasColumn('departments', 'code')) {
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            }
            Schema::table('departments', function (Blueprint $table) {
                $table->dropUnique(['factory_id', 'code']);
                $table->dropColumn('code');
            });
            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->string('code')->after('id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('code')->after('factory_id');
        });
    }
};
