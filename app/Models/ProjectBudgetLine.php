<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetLine extends Model
{
    public const MONEY_FIELDS = [
        'bid_sub_cents', 'actual_sub_cents',
        'estimated_material_cents', 'actual_material_cents',
        'estimated_labor_cents', 'actual_labor_cents',
    ];

    protected $fillable = [
        'project_id',
        'budget_section_id',
        'budget_line_definition_id',
        'name',
        'notes',
        'bid_sub_cents',
        'actual_sub_cents',
        'estimated_material_cents',
        'actual_material_cents',
        'estimated_labor_cents',
        'actual_labor_cents',
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::MONEY_FIELDS, 'integer');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<BudgetSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(BudgetSection::class, 'budget_section_id');
    }

    /**
     * Everything budgeted for this line (sub bid + material & labor estimates).
     */
    public function budgetedCents(): int
    {
        return ($this->bid_sub_cents ?? 0)
            + ($this->estimated_material_cents ?? 0)
            + ($this->estimated_labor_cents ?? 0);
    }

    /**
     * Everything actually spent on this line so far.
     */
    public function actualCents(): int
    {
        return ($this->actual_sub_cents ?? 0)
            + ($this->actual_material_cents ?? 0)
            + ($this->actual_labor_cents ?? 0);
    }

    /**
     * Positive = under budget (money left / profit); negative = over budget.
     * Matches the sheet's "Difference" columns (estimated − actual).
     */
    public function varianceCents(): int
    {
        return $this->budgetedCents() - $this->actualCents();
    }
}
