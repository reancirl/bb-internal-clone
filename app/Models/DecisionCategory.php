<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionCategory extends Model
{
    public const SCOPES = ['living', 'garage', 'shared'];

    protected $fillable = ['name', 'scope', 'sort_order', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<DecisionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DecisionItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
