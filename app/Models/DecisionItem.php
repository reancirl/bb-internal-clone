<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionItem extends Model
{
    protected $fillable = ['decision_category_id', 'label', 'recommended', 'guidance', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<DecisionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DecisionCategory::class, 'decision_category_id');
    }

    /**
     * @return HasMany<ProjectSelection, $this>
     */
    public function projectSelections(): HasMany
    {
        return $this->hasMany(ProjectSelection::class);
    }
}
