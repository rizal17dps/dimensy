<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileApprover extends Model
{
    use HasFactory;

    protected $table = 'profile_approver';

    public function maps()
    {
        return $this->hasMany('App\Models\MapApprovers', 'profile_id');
    }

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
    }
}
