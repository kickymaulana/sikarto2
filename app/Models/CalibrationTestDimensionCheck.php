<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationTestDimensionCheck extends Model
{
    protected $fillable = [
        'calibration_test_id', 'dimension', 'label', 'min_value', 'max_value',
        'measured_value', 'unit', 'is_within_range', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_within_range' => 'boolean',
        ];
    }

    public function test()
    {
        return $this->belongsTo(CalibrationTest::class, 'calibration_test_id');
    }
}
