<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpModel extends Model
{
    use HasFactory;
    protected $table = 'tmp_download';

    public function dok()
    {
        return $this->belongsTo('App\Models\Sign', 'dokumen_id');
    }
}
