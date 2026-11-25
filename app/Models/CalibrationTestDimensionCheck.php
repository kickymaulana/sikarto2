<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationTestDimensionCheck extends Model
{
    protected $fillable = [
        'calibration_test_id', 'dimension', 'label', 'min_value', 'max_value',
        'measured_value', 'measured_value_text', 'measured_value_min', 'measured_value_max', 'unit', 'is_within_range', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_within_range' => 'boolean',
            'measured_value_min' => 'decimal:2',
            'measured_value_max' => 'decimal:2',
        ];
    }

    public function diameterDisplay(): ?string
    {
        if ($this->measured_value_min !== null && $this->measured_value_max !== null) {
            return $this->measured_value_min.'-'.$this->measured_value_max;
        }

        return $this->measured_value_text ?? ($this->measured_value === null ? null : number_format((float) $this->measured_value, 2, '.', ''));
    }

    public function test()
    {
        return $this->belongsTo(CalibrationTest::class, 'calibration_test_id');
    }
}
