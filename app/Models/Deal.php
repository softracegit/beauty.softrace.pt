<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'opportunity_id',
        'proposal_id',
        'property_id',
        'client_id',
        'property_reference',
        'property_title',
        'property_address',
        'transaction_type',
        'final_price',
        'property_commission_value',
        'property_commission_percentage',
        'status',
        'closed_at',
        'closed_by',
        'reverted_at',
        'reverted_by',
        'reversion_reason',
        'notes',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'property_commission_value' => 'decimal:2',
        'property_commission_percentage' => 'decimal:2',
        'closed_at' => 'datetime',
        'reverted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Estados do deal
    public const STATUS_FECHADO = 'fechado';
    public const STATUS_REVERTIDO = 'revertido';

    // Tipos de participação dos agentes
    public const ROLE_ANGARIADOR = 'angariador';
    public const ROLE_VENDEDOR = 'vendedor';

    /**
     * Get all status options
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_FECHADO => 'Fechado',
            self::STATUS_REVERTIDO => 'Revertido',
        ];
    }

    /**
     * Get all agent role options
     */
    public static function agentRoles(): array
    {
        return [
            self::ROLE_ANGARIADOR => 'Angariador',
            self::ROLE_VENDEDOR => 'Vendedor',
        ];
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_FECHADO => 'success',
            self::STATUS_REVERTIDO => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get formatted final price
     */
    public function getFormattedFinalPriceAttribute(): string
    {
        return number_format($this->final_price, 2, ',', '.') . ' €';
    }

    /**
     * Get formatted property commission
     */
    public function getFormattedPropertyCommissionAttribute(): string
    {
        if ($this->property_commission_value) {
            return number_format($this->property_commission_value, 2, ',', '.') . ' €';
        }
        if ($this->property_commission_percentage) {
            return $this->property_commission_percentage . '%';
        }
        return '—';
    }

    /**
     * Calculate total agent commissions
     */
    public function getTotalAgentCommissionsAttribute(): float
    {
        return $this->agentCommissions->sum('commission_value');
    }

    /**
     * Get formatted total agent commissions
     */
    public function getFormattedTotalAgentCommissionsAttribute(): string
    {
        return number_format($this->total_agent_commissions, 2, ',', '.') . ' €';
    }

    /**
     * Check if deal can be reverted
     */
    public function canBeReverted(): bool
    {
        return $this->status === self::STATUS_FECHADO;
    }

    /**
     * Get deal ID attribute
     */
    public function getDealIdAttribute(): string
    {
        return '#DEAL' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    /**
     * Get opportunity
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * Get proposal
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * Get property
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get user who closed the deal
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get user who reverted the deal
     */
    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    /**
     * Get agent commissions
     */
    public function agentCommissions(): HasMany
    {
        return $this->hasMany(DealAgentCommission::class);
    }

    /**
     * Generate unique reference
     */
    public static function generateReference(): string
    {
        $year = date('Y');
        $lastDeal = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $nextNumber = $lastDeal ? intval(substr($lastDeal->reference, -4)) + 1 : 1;
        
        return 'DEAL-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
