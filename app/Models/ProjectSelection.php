<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSelection extends Model
{
    protected $fillable = [
        'project_id',
        'decision_item_id',
        'allowance_cents',
        'deadline_date',
        'notes',
        'approved_choice_id',
        'approved_at',
        'approved_by_user_id',
        'approval_comment',
    ];

    protected function casts(): array
    {
        return [
            'allowance_cents' => 'integer',
            'deadline_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<DecisionItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(DecisionItem::class, 'decision_item_id');
    }

    /**
     * @return HasMany<SelectionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(SelectionChoice::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<SelectionChoice, $this>
     */
    public function approvedChoice(): BelongsTo
    {
        return $this->belongsTo(SelectionChoice::class, 'approved_choice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Overage (positive) or saving (negative) vs the allowance, in cents.
     * Null until a priced choice is approved against a set allowance.
     */
    public function varianceCents(): ?int
    {
        if ($this->approved_choice_id === null || $this->allowance_cents === null) {
            return null;
        }

        $price = $this->approvedChoice?->price_cents;

        return $price === null ? null : $price - $this->allowance_cents;
    }
}
