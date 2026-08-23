<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
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

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }
}
