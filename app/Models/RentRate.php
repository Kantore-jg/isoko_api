<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentRate extends Model
{
    protected $fillable = [
        'block_id',
        'place_id',
        'amount',
        'effective_from',
        'effective_to',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }
}
