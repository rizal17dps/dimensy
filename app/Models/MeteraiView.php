<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeteraiView extends Model
{
    use HasFactory;

    protected $table = 'meterai';
    protected $visible = ['serial_number'];

    public function doks()
    {
        return $this->hasMany('App\Models\Sign', 'id');
    }
}
