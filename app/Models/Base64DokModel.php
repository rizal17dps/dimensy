<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Base64DokModel extends Model
{
    use HasFactory;

    protected $table = 'dok_base64';

    public function dokumen()
    {
        return $this->belongsTo('App\Models\Sign', 'dokumen_id');
    }
}
