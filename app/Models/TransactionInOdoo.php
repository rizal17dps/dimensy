<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionInOdoo extends Authenticatable
{
    use Notifiable;
    use HasRoles;
    use HasFactory;
    
    protected $table = 'transaction_in_odoo';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'users_id',
        'so_id',
        'so_number',
        'account_move_id',
        'invoice_id',
        'payment_id',
        'payment_no',
        'payment_attach',
        'invoice_no',
        'payment_date',
        'invoice_attach',
    ];

    public function company()
    {
        return $this->belongsTo('App\Models\Company', 'company_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id')->withTrashed();
    }
}