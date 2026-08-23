<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'block_id',
        'code',
        'name',
        'description',
        'surface',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'surface' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function assignments()
    {
        return $this->hasMany(PlaceAssignment::class);
    }

    public function movements()
    {
        return $this->hasMany(PlaceMovement::class);
    }

    public function rentRates()
    {
        return $this->hasMany(RentRate::class);
    }

    public function obligations()
    {
        return $this->hasMany(RentObligation::class);
    }
}
