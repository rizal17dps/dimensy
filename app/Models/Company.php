<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'company';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'alamat',
        'email',
        'company_type',
    ];

    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    public function users_admin()
    {
        return $this->hasOne('App\Models\User')->where('key_admin', TRUE);
    }

    public function mapsCompany()
    {
        return $this->hasOne('App\Models\MapCompany');
    }

    public function quota()
    {
        return $this->hasMany('App\Models\Quota');
    }
}
