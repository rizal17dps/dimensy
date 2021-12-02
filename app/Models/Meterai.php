<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meterai extends Model
{
    use HasFactory;

    protected $table = 'meterai';

    public function doks()
    {
        return $this->belongsTo('App\Models\Sign', 'dokumen_id');
    }
}
