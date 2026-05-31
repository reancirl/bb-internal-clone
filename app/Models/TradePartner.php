<?php

namespace App\Models;

use Database\Factories\TradePartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradePartner extends Model
{
    /** @use HasFactory<TradePartnerFactory> */
    use HasFactory;

    /**
     * Canonical trade list, sourced from the Material Master List trade-partner sheet.
     *
     * @var list<string>
     */
    public const TRADES = [
        'Cabinet Installer',
        'Finish Carpentry',
        'Framing/General Construction',
        'Concrete',
        'Excavation',
        'Electrician',
        'Plumber',
        'HVAC',
        'Roofing',
        'Siding',
        'Gutters',
        'Garage Door Install',
        'Flooring',
        'Tile Installer',
        'Drywall/Painting',
        'Painting',
        'Masonry',
        'Insulation',
        'Spray Foam',
        'Landscaping',
        'Fencing',
        'Countertops',
        'Windows',
        'Engineer',
        'Paving',
    ];

    protected $fillable = [
        'name',
        'location',
        'phone',
        'email',
        'used_before',
        'negotiated_price',
        'referral_source',
        'notes',
        'do_not_use',
    ];

    protected function casts(): array
    {
        return [
            'used_before' => 'boolean',
            'do_not_use' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TradePartnerTrade, $this>
     */
    public function trades(): HasMany
    {
        return $this->hasMany(TradePartnerTrade::class);
    }
}
