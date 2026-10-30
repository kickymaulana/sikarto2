<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Capacity extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'value', 'unit'];

    public function groups()
    {
        return $this->hasMany(StandardGroup::class)->orderBy('sort_order')->orderBy('id');
    }
}
