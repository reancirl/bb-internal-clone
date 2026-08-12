<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectionChoice extends Model
{
    protected $fillable = [
        'project_selection_id',
        'label',
        'description',
        'price_cents',
        'vendor_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['price_cents' => 'integer'];
    }

    /**
     * @return BelongsTo<ProjectSelection, $this>
     */
    public function selection(): BelongsTo
    {
        return $this->belongsTo(ProjectSelection::class, 'project_selection_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
