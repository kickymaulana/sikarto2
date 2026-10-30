<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcceptableLimit extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'min_correction', 'max_correction', 'unit'];

    public function isWithin(float $correction): bool
    {
        return $correction >= (float) $this->min_correction
            && $correction <= (float) $this->max_correction;
    }
}
