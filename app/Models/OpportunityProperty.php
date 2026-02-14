<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OpportunityProperty extends Pivot
{
    protected $table = 'opportunity_properties';

    protected $fillable = [
        'opportunity_id',
        'property_id',
        'attached_at',
        'notes',
    ];

    protected $casts = [
        'attached_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the opportunity
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * Get the property
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
