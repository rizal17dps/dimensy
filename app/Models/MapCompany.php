<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapCompany extends Model
{
    use HasFactory;

    protected $table = 'map_company';

    public function paket()
    {
        return $this->belongsTo('App\Models\Paket', 'paket_id');
    }

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
    }
}
