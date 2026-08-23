<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'place_id',
        'merchant_id',
        'assignment_id',
        'movement_type',
        'movement_date',
        'previous_merchant_id',
        'new_merchant_id',
        'reason',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'created_at' => 'datetime',
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

    public function assignment()
    {
        return $this->belongsTo(PlaceAssignment::class, 'assignment_id');
    }

    public function previousMerchant()
    {
        return $this->belongsTo(Merchant::class, 'previous_merchant_id');
    }

    public function newMerchant()
    {
        return $this->belongsTo(Merchant::class, 'new_merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
