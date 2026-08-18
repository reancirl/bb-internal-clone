<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const STATUS_LEAD = 'lead';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETE = 'complete';

    public const STATUSES = [self::STATUS_LEAD, self::STATUS_ACTIVE, self::STATUS_COMPLETE];

    /**
     * Dimension variables available to takeoff formulas, with display labels.
     *
     * @var array<string, string>
     */
    public const DIMENSIONS = [
        'house_sqft' => 'House floor area (sq ft)',
        'garage_sqft' => 'Garage floor area (sq ft)',
        'roof_sqft' => 'Roof area (sq ft)',
        'valley_lft' => 'Roof valley length (ft)',
        'eve_lft' => 'Eave length (ft)',
        'rake_lft' => 'Rake length (ft)',
        'ext_wall_lft' => 'Exterior wall length (ft)',
        'int_wall_lft' => 'Interior wall length (ft)',
        'ext_wall_sqft' => 'Exterior wall area (sq ft)',
        'int_wall_sqft' => 'Interior wall area (sq ft)',
        'ceiling_sqft' => 'Ceiling area (sq ft)',
        'wall_height' => 'Wall height (ft)',
        'footer_height' => 'Footer height (ft)',
        'footer_width' => 'Footer width (ft)',
        'slab_depth' => 'Slab thickness (ft)',
    ];

    /**
     * @var list<string>
     */
    public const DIMENSION_KEYS = [
        'house_sqft', 'garage_sqft', 'roof_sqft', 'valley_lft', 'eve_lft', 'rake_lft',
        'ext_wall_lft', 'int_wall_lft', 'ext_wall_sqft', 'int_wall_sqft', 'ceiling_sqft',
        'wall_height', 'footer_height', 'footer_width', 'slab_depth',
    ];

    protected $fillable = [
        'name',
        'client_name',
        'address',
        'status',
        'contract_price_cents',
        'house_sqft', 'garage_sqft', 'roof_sqft', 'valley_lft', 'eve_lft', 'rake_lft',
        'ext_wall_lft', 'int_wall_lft', 'ext_wall_sqft', 'int_wall_sqft', 'ceiling_sqft',
        'wall_height', 'footer_height', 'footer_width', 'slab_depth',
    ];

    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::DIMENSION_KEYS, 'decimal:2'),
            'contract_price_cents' => 'integer',
        ];
    }

    /**
     * Dimension variables as a name => float map for the formula evaluator.
     *
     * @return array<string, float>
     */
    public function dimensionValues(): array
    {
        $values = [];
        foreach (self::DIMENSION_KEYS as $key) {
            $values[$key] = (float) $this->{$key};
        }

        return $values;
    }

    /**
     * @return HasMany<TakeoffLine, $this>
     */
    public function takeoffLines(): HasMany
    {
        return $this->hasMany(TakeoffLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ProjectJob::class)->orderBy('scheduled_date');
    }

    /**
     * @return HasMany<ProjectSelection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(ProjectSelection::class);
    }

    /**
     * @return HasMany<ProjectBudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(ProjectBudgetLine::class);
    }

    /**
     * @return HasMany<ChangeOrder, $this>
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class)->orderBy('number');
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<ProjectProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ProjectProposal::class)->orderByDesc('id');
    }
}
