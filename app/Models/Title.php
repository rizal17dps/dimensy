<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    use HasFactory;

    protected $table = 'title';

    public function users()
    {
        return $this->hasMany('App\Models\User')->withTrashed();
    }
}
