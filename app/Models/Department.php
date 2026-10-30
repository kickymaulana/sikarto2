<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = ['factory_id', 'name'];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }
}
