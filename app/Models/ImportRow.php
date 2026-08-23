<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $table = 'import_rows';
    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'row_number',
        'data',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
