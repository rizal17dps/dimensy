<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapPaket extends Model
{
    use HasFactory;

    protected $table = 'map_paket';

    public function paket()
    {
        return $this->belongsTo('App\Models\Paket', 'paket_id');
    }

    public function detail()
    {
        return $this->belongsTo('App\Models\PaketDetail', 'paket_detial_id');
    }

}
