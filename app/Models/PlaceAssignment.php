<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceAssignment extends Model
{
    protected $fillable = [
        'place_id',
        'merchant_id',
        'start_date',
        'end_date',
        'rent_rate_id',
        'rent_amount',
        'status',
        'assignment_reason',
        'notes',
        'assigned_by',
        'ended_by',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'ended_at' => 'datetime',
        ];
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function rentRate()
    {
        return $this->belongsTo(RentRate::class);
    }

    public function obligations()
    {
        return $this->hasMany(RentObligation::class, 'assignment_id');
    }
}
