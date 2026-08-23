<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_rent_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'default_rent_amount' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function rentRates()
    {
        return $this->hasMany(RentRate::class);
    }
}
