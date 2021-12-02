<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListSigner extends Model
{
    use HasFactory;

    protected $table = 'list_signer';

    public function doks()
    {
        return $this->hasMany('App\Models\Sign');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\Users', 'users_id');
    }
}
