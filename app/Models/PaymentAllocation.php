<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'rent_obligation_id',
        'amount_allocated',
    ];

    protected function casts(): array
    {
        return [
            'amount_allocated' => 'decimal:2',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function obligation()
    {
        return $this->belongsTo(RentObligation::class, 'rent_obligation_id');
    }
}
