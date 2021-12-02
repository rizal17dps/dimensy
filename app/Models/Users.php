<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Users extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $hidden = [
        'password', 'remember_token', 'foto_ktp', 'time_first_login', 'isexpired',
    ];

    /**
     * Get the notes for the users.
     */
    public function notes()
    {
        return $this->hasMany('App\Models\Notes');
    }

    public function maps()
    {
        return $this->hasMany('App\Models\MapApprovers');
    }

    public function listSigner()
    {
        return $this->hasMany('App\Models\ListSigner');
    }    

    public function departement()
    {
        return $this->belongsTo('App\Models\Departement', 'departement_id')->withTrashed();
    }

    public function title()
    {
        return $this->belongsTo('App\Models\Title', 'title_id');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry', 'industry_id')->withTrashed();
    }

    protected $dates = [
        'deleted_at'
    ];
}