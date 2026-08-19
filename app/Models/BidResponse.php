<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidResponse extends Model
{
    public const STATUS_INVITED = 'invited';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [self::STATUS_INVITED, self::STATUS_RECEIVED, self::STATUS_DECLINED];

    protected $fillable = [
        'bid_request_id',
        'trade_partner_id',
        'trade_partner_name',
        'status',
        'amount_cents',
        'notes',
        'invited_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'invited_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BidRequest, $this>
     */
    public function bidRequest(): BelongsTo
    {
        return $this->belongsTo(BidRequest::class);
    }

    /**
     * @return BelongsTo<TradePartner, $this>
     */
    public function tradePartner(): BelongsTo
    {
        return $this->belongsTo(TradePartner::class);
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }
}
