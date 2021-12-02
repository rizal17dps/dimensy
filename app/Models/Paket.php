<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paket extends Model
{
    use HasFactory;

    protected $table = 'paket';

    public function maps()
    {
        return $this->hasMany('App\Models\MapPaket', 'paket_id');
    }

    public function mapsCompany()
    {
        return $this->hasMany('App\Models\MapCompany', 'paket_id');
    }

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
    }
}
