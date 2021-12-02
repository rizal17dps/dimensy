<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryPemakaian extends Model
{
    use HasFactory;

    protected $table = 'history_pemakaian';

    public function detail()
    {
        return $this->belongsTo('App\Models\PaketDetail', 'paket_detail_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\Users', 'users_id');
    }
}
