<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationTest extends Model
{
    protected $fillable = [
        'instrument_id', 'test_date', 'next_test_date', 'tester_id', 'status', 'avg_correction', 'notes',
        'computed_status', 'selected_status', 'min_correction_snapshot', 'max_correction_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'test_date' => 'date',
            'next_test_date' => 'date',
            'avg_correction' => 'float',
        ];
    }

    public function instrument()
    {
        return $this->belongsTo(Instrument::class)->withTrashed();
    }

    public function tester()
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function items()
    {
        return $this->hasMany(CalibrationTestItem::class);
    }

    public function dimensionChecks()
    {
        return $this->hasMany(CalibrationTestDimensionCheck::class)->orderBy('sort_order');
    }
}
