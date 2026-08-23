<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $table = 'imports';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'import_type',
        'file_name',
        'file_path',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
