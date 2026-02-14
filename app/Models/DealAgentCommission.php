<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealAgentCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'agent_id',
        'role',
        'agent_name',
        'agent_email',
        'commission_value',
        'commission_percentage',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get formatted commission value
     */
    public function getFormattedCommissionValueAttribute(): string
    {
        if ($this->commission_value) {
            return number_format($this->commission_value, 2, ',', '.') . ' €';
        }
        return '—';
    }

    /**
     * Get formatted commission percentage
     */
    public function getFormattedCommissionPercentageAttribute(): string
    {
        if ($this->commission_percentage) {
            return number_format($this->commission_percentage, 2, ',', '.') . '%';
        }
        return '—';
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        return Deal::agentRoles()[$this->role] ?? $this->role;
    }

    // Relationships

    /**
     * Get deal
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get agent
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
