<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instrument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'factory_id', 'department_id', 'instrument_type_id',
        'brand_id', 'capacity_id', 'acceptable_limit_id', 'specification_id', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function type()
    {
        return $this->belongsTo(InstrumentType::class, 'instrument_type_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function capacity()
    {
        return $this->belongsTo(Capacity::class);
    }

    public function acceptableLimit()
    {
        return $this->belongsTo(AcceptableLimit::class);
    }

    public function specification()
    {
        return $this->belongsTo(Specification::class);
    }

    public function tests()
    {
        return $this->hasMany(CalibrationTest::class);
    }

    public function latestTest()
    {
        return $this->hasOne(CalibrationTest::class)->latestOfMany('test_date');
    }
}
