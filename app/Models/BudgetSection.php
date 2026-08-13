<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetSection extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<BudgetLineDefinition, $this>
     */
    public function lineDefinitions(): HasMany
    {
        return $this->hasMany(BudgetLineDefinition::class)->orderBy('sort_order')->orderBy('id');
    }
}
