<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;
    use SoftDeletes;
    use HasRoles;
    use HasFactory;
    
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'nik',
        'email',
        'password',
        'tempat_lahir',
        'tanggal_lahir',
        'hp',
        'alamat',
        'kota',
        'provinsi',
        'foto_ktp',
        'foto_npwp',
        'selfi',
        'company_id',
        'industry_id',
        'title_id',
        'departement_id',
        'key_admin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'foto_ktp', 'time_first_login', 'isexpired',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at'
    ];

    protected $attributes = [ 
        'menuroles' => 'user',
    ];

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
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
}
