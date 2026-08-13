<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLineDefinition extends Model
{
    protected $fillable = ['budget_section_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<BudgetSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(BudgetSection::class, 'budget_section_id');
    }
}
