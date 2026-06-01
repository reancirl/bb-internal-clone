<?php

namespace App\Models;

use Database\Factories\EquipmentRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentRate extends Model
{
    /** @use HasFactory<EquipmentRateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'day_rate',
        'week_rate',
        'month_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_rate' => 'decimal:2',
            'week_rate' => 'decimal:2',
            'month_rate' => 'decimal:2',
        ];
    }
}
