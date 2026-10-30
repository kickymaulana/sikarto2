<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardTemplate extends Model
{
    protected $fillable = ['standard_group_id', 'standard_value', 'sort_order'];

    public function group()
    {
        return $this->belongsTo(StandardGroup::class, 'standard_group_id');
    }
}
