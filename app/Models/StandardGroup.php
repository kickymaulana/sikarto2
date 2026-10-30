<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardGroup extends Model
{
    protected $fillable = ['capacity_id', 'name', 'reference_media', 'sort_order'];

    public function capacity()
    {
        return $this->belongsTo(Capacity::class);
    }

    public function standards()
    {
        return $this->hasMany(StandardTemplate::class)->orderBy('sort_order')->orderBy('id');
    }
}
