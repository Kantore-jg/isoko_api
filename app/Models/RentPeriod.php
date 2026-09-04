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

    protected $appends = ['label'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function getLabelAttribute(): string
    {
        $months = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];

        $monthName = $months[(int) $this->month] ?? (string) $this->month;

        return $monthName.' '.$this->year;
    }

    public function obligations()
    {
        return $this->hasMany(RentObligation::class);
    }
}
