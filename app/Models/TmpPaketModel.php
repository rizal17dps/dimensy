<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpPaketModel extends Model
{
    use HasFactory;
    protected $table = 'tmp_paket';

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
    }
}
