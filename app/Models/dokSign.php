<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class dokSign extends Model
{
    use HasFactory;

    protected $table = 'dok_sign';

    public function doks()
    {
        return $this->hasMany('App\Models\Sign');
    }
}
