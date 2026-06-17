<?php

namespace App\Models;

use Database\Factories\TakeoffLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TakeoffLine extends Model
{
    /** @use HasFactory<TakeoffLineFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'price_item_id',
        'supplier_id',
        'category',
        'item',
        'formula',
        'unit',
        'waste_pct',
        'ordered',
        'on_site',
        'notes',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'waste_pct' => 'decimal:2',
            'ordered' => 'boolean',
            'on_site' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<PriceItem, $this>
     */
    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }
}
