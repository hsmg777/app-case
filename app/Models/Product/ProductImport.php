<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    protected $table = 'product_imports';

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'failed_count',
        'error_log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
