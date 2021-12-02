<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapApprovers extends Model
{
    use HasFactory;

    protected $table = 'map_approver';

    public function profiles()
    {
        return $this->belongsTo('App\Models\ProfileApprover', 'profile_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id');
    }
}
