<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_code',
        'business_name',
        'owner_name',
        'national_id',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'business_type',
        'registration_number',
        'tax_number',
        'status',
        'registration_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(PlaceAssignment::class);
    }

    public function obligations()
    {
        return $this->hasMany(RentObligation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
