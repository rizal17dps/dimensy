<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $table = 'history';

    public function doks()
    {
        return $this->hasMany('App\Models\Sign');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id')->withTrashed();
    }
}
