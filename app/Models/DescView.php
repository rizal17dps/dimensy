<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DescView extends Model
{
    use HasFactory;

    protected $table = 'dok_base64';
    protected $visible = ['desc'];

}
