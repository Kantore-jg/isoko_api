<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentObligation extends Model
{
    protected $fillable = [
        'rent_period_id',
        'assignment_id',
        'merchant_id',
        'place_id',
        'amount_expected',
        'amount_paid',
        'balance',
        'status',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_expected' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(RentPeriod::class, 'rent_period_id');
    }

    public function assignment()
    {
        return $this->belongsTo(PlaceAssignment::class, 'assignment_id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
