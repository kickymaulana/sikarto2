<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'length_min', 'length_max', 'width_min', 'width_max',
        'diameter_min', 'diameter_max', 'dimension_unit',
    ];
}
