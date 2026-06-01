<?php

namespace App\Models;

use Database\Factories\LaborRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaborRate extends Model
{
    /** @use HasFactory<LaborRateFactory> */
    use HasFactory;

    protected $fillable = [
        'class_name',
        'base_rate',
        'burden_rate',
        'bill_rate',
        'total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_rate' => 'decimal:2',
            'burden_rate' => 'decimal:2',
            'bill_rate' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
