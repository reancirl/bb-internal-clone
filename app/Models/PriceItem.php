<?php

namespace App\Models;

use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    /**
     * Common units of measure used across the price book.
     *
     * @var list<string>
     */
    public const UNITS = ['SF', 'LF', 'LFT', 'SY', 'YD3', 'EA', 'HR', 'SET', 'sheet', 'TON', 'DAY', 'month'];

    protected $fillable = [
        'price_category_id',
        'name',
        'unit',
        'fast_price',
        'material_cost',
        'bb_install_rate',
        'sub_install_rate',
        'preferred_vendor_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fast_price' => 'decimal:2',
            'material_cost' => 'decimal:2',
            'bb_install_rate' => 'decimal:2',
            'sub_install_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PriceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class, 'price_category_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }
}
