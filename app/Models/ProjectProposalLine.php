<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProposalLine extends Model
{
    protected $fillable = [
        'project_proposal_id',
        'category',
        'item',
        'qty',
        'unit',
        'unit_price_cents',
        'total_cents',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProjectProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProjectProposal::class, 'project_proposal_id');
    }
}
