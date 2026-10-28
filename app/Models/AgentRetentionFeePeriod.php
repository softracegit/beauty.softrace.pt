<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRetentionFeePeriod extends Model
{
    protected $fillable = [
        'agent_id',
        'rate',
        'unit',
        'effective_from',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function hasRetention(): bool
    {
        return $this->rate !== null && $this->rate !== '';
    }

    public function formatDisplay(): ?string
    {
        if (! $this->hasRetention()) {
            return null;
        }

        $num = number_format((float) $this->rate, 2, ',', ' ');
        $unit = $this->unit ?? Agent::COMMISSION_UNIT_PERCENT;

        return $unit === Agent::COMMISSION_UNIT_EURO
            ? $num.' €'
            : $num.' %';
    }
}
