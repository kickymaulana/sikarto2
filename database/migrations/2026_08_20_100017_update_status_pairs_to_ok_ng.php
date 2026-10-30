<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('calibration_tests')->where('status', 'PASS')->update(['status' => 'OK']);
        DB::table('calibration_tests')->where('status', 'FAIL')->update(['status' => 'NG']);
    }

    public function down(): void
    {
        DB::table('calibration_tests')->where('status', 'OK')->update(['status' => 'PASS']);
        DB::table('calibration_tests')->where('status', 'NG')->update(['status' => 'FAIL']);
    }
};
