<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    protected $table = 'market';

    protected $fillable = [
        'code',
        'name',
        'description',
        'address',
        'commune',
        'province',
        'phone',
        'email',
        'logo',
        'status',
    ];

}
