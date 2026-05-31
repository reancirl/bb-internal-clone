<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradePartnerTrade extends Model
{
    protected $fillable = [
        'trade_partner_id',
        'trade',
    ];

    /**
     * @return BelongsTo<TradePartner, $this>
     */
    public function tradePartner(): BelongsTo
    {
        return $this->belongsTo(TradePartner::class);
    }
}
