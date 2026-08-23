<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_number',
        'merchant_id',
        'payment_date',
        'amount',
        'bank_id',
        'reference_number',
        'payment_method',
        'status',
        'notes',
        'received_by',
        'posted_at',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }
}
