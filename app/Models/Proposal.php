<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_ENVIADA = 'enviada';
    public const STATUS_APROVADA = 'aprovada';
    public const STATUS_REJEITADA = 'rejeitada';

    protected $fillable = [
        'opportunity_id',
        'property_id',
        'parent_proposal_id',
        'proposed_value',
        'conditions',
        'status',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejected_by',
        'created_by',
    ];

    protected $casts = [
        'proposed_value' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADA => 'Enviada',
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_REJEITADA => 'Rejeitada',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'parent_proposal_id');
    }

    /**
     * Contrapropostas desta proposta
     */
    public function counterProposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'parent_proposal_id')->orderBy('created_at', 'desc');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_RASCUNHO => 'secondary',
            self::STATUS_ENVIADA => 'info',
            self::STATUS_APROVADA => 'success',
            self::STATUS_REJEITADA => 'danger',
            default => 'secondary',
        };
    }

    public function getFormattedValueAttribute(): string
    {
        return number_format($this->proposed_value, 0, ',', '.') . ' €';
    }

    public function isCounterProposal(): bool
    {
        return $this->parent_proposal_id !== null;
    }
}
