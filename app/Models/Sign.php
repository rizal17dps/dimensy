<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sign extends Model
{
    use HasFactory;

    protected $table = 'dokumen';
    Protected $primaryKey = "id";

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'users_id')->withTrashed();
    }

    /**
     * Get the Status that owns the Notes.
     */
    public function stat()
    {
        return $this->belongsTo('App\Models\Status', 'status_id');
    }

    public function approver()
    {
        return $this->hasMany('App\Models\ListSigner', 'dokumen_id');
    }

    public function dokSign()
    {
        return $this->hasMany('App\Models\dokSign', 'dokumen_id');
    }
}
