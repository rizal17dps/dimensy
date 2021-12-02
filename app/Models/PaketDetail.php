<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaketDetail extends Model
{
    use HasFactory;

    protected $table = 'paket_detail';

    public function maps()
    {
        return $this->hasMany('App\Models\MapPaket', 'paket_detail_id');
    }

    public function detailName()
    {
        return $this->belongsTo('App\Models\NameDetail', 'detail_name_id');
    }

}
