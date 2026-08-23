<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function obligations()
    {
        return $this->hasMany(RentObligation::class);
    }
}
