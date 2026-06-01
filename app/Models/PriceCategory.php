<?php

namespace App\Models;

use Database\Factories\PriceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceCategory extends Model
{
    /** @use HasFactory<PriceCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'sort',
    ];

    /**
     * @return HasMany<PriceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceItem::class);
    }
}
