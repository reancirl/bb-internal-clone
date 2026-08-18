<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'takeoff_line_id',
        'description',
        'qty',
        'unit',
        'unit_price_cents',
        'total_cents',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<TakeoffLine, $this>
     */
    public function takeoffLine(): BelongsTo
    {
        return $this->belongsTo(TakeoffLine::class);
    }
}
