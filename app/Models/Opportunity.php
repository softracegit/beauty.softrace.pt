<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'status',
        'priority',
        'type',
        'client_id',
        'lead_id',
        'agent_id',
        'notes',
        'status_changed_at',
        'archived_at',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Estados da oportunidade
    public const STATUS_POR_TRATAR = 'por_tratar';
    public const STATUS_EM_ANALISE = 'em_analise';
    public const STATUS_IMOVEIS_SUGERIDOS = 'imoveis_sugeridos';
    public const STATUS_VISITAS_AGENDADAS = 'visitas_agendadas';
    public const STATUS_PROPOSTA_NEGOCIACAO = 'proposta_negociacao';
    public const STATUS_PROPOSTA_ACEITE = 'proposta_aceite';
    public const STATUS_GANHA = 'ganha';
    public const STATUS_PERDIDA = 'perdida';
    public const STATUS_CANCELADA = 'cancelada';

    // Tipos de oportunidade
    public const TYPE_COMPRA = 'compra';
    public const TYPE_ARRENDAMENTO = 'arrendamento';
    public const TYPE_ANGARIACAO = 'angariacao';

    // Prioridades
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Get all status options
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_POR_TRATAR => 'Por Tratar',
            self::STATUS_EM_ANALISE => 'Em Análise',
            self::STATUS_IMOVEIS_SUGERIDOS => 'Imóveis Sugeridos',
            self::STATUS_VISITAS_AGENDADAS => 'Visitas Agendadas',
            self::STATUS_PROPOSTA_NEGOCIACAO => 'Proposta em Negociação',
            self::STATUS_PROPOSTA_ACEITE => 'Proposta Aceite',
            self::STATUS_GANHA => 'Ganha',
            self::STATUS_PERDIDA => 'Perdida',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    /**
     * Get status order for kanban
     */
    public static function getStatusOrder(): array
    {
        return [
            self::STATUS_POR_TRATAR,
            self::STATUS_EM_ANALISE,
            self::STATUS_IMOVEIS_SUGERIDOS,
            self::STATUS_VISITAS_AGENDADAS,
            self::STATUS_PROPOSTA_NEGOCIACAO,
            self::STATUS_PROPOSTA_ACEITE,
            self::STATUS_GANHA,
            self::STATUS_PERDIDA,
            self::STATUS_CANCELADA,
        ];
    }

    /**
     * Get active statuses for kanban (exclude final states)
     */
    public static function getActiveStatuses(): array
    {
        return [
            self::STATUS_POR_TRATAR,
            self::STATUS_EM_ANALISE,
            self::STATUS_IMOVEIS_SUGERIDOS,
            self::STATUS_VISITAS_AGENDADAS,
            self::STATUS_PROPOSTA_NEGOCIACAO,
            self::STATUS_PROPOSTA_ACEITE,
        ];
    }

    /**
     * Get transaction types from database
     */
    public static function transactionTypes(): array
    {
        return TransactionType::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get all type options
     */
    public static function types(): array
    {
        return [
            self::TYPE_COMPRA => 'Compra',
            self::TYPE_ARRENDAMENTO => 'Arrendamento',
            self::TYPE_ANGARIACAO => 'Angariação',
        ];
    }

    /**
     * Get all priority options
     */
    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Baixa',
            self::PRIORITY_MEDIUM => 'Média',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_URGENT => 'Urgente',
        ];
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_POR_TRATAR => 'secondary',
            self::STATUS_EM_ANALISE => 'info',
            self::STATUS_IMOVEIS_SUGERIDOS => 'primary',
            self::STATUS_VISITAS_AGENDADAS => 'warning',
            self::STATUS_PROPOSTA_NEGOCIACAO => 'info',
            self::STATUS_PROPOSTA_ACEITE => 'success',
            self::STATUS_GANHA => 'success',
            self::STATUS_PERDIDA => 'danger',
            self::STATUS_CANCELADA => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get priority color for badges
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_URGENT => 'danger',
            self::PRIORITY_HIGH => 'warning',
            self::PRIORITY_MEDIUM => 'info',
            self::PRIORITY_LOW => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get opportunity ID attribute
     */
    public function getOpportunityIdAttribute(): string
    {
        return '#OPP' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get lead
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get agent
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }


    /**
     * Get associated properties (pivot table)
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'opportunity_properties')
            ->withPivot('attached_at', 'notes')
            ->withTimestamps()
            ->using(OpportunityProperty::class)
            ->orderByPivot('attached_at', 'desc');
    }

    /**
     * Get property preferences
     */
    public function propertyPreferences(): HasMany
    {
        return $this->hasMany(OpportunityPropertyPreference::class);
    }

    /**
     * Get proposals
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get visits
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('scheduled_at', 'desc');
    }

    /**
     * Get status logs
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OpportunityStatusLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Update status and log the change
     */
    public function updateStatus(string $newStatus, ?int $userId = null, ?string $notes = null): void
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $newStatus,
            'status_changed_at' => now(),
        ]);

        // Log the status change
        OpportunityStatusLog::create([
            'opportunity_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $userId ?? auth()->id(),
            'notes' => $notes,
        ]);
    }

    /**
     * Get crossed properties (suggested based on preferences)
     */
    public function getCrossedProperties()
    {
        $query = Property::query()
            ->where('status', Property::STATUS_DISPONIVEL);
        
        // Buscar preferências ativas
        $preference = $this->propertyPreferences()->where('is_active', true)->first();
        
        if (!$preference) {
            // Se não houver preferências, retornar vazio ou todos os imóveis (depende da lógica de negócio)
            return collect();
        }

        // Filtro de tipo de transação
        if ($preference->transaction_type_id) {
            $query->where('transaction_type_id', $preference->transaction_type_id);
        }

        // Filtro de tipo de imóvel
        if ($preference->property_type_id) {
            $query->where('property_type_id', $preference->property_type_id);
        }

        // Filtro de preço
        if ($preference->min_price) {
            $query->where('price', '>=', $preference->min_price);
        }
        if ($preference->max_price) {
            $query->where('price', '<=', $preference->max_price);
        }

        // Filtro de tipologias (múltiplas)
        $typologyIds = $preference->typologies->pluck('id')->toArray();
        if (!empty($typologyIds)) {
            $query->whereIn('property_typology_id', $typologyIds);
        }

        // Filtro de localizações (múltiplas)
        $locations = $preference->preferenceLocations;
        if ($locations->isNotEmpty()) {
            $query->where(function ($q) use ($locations) {
                foreach ($locations as $location) {
                    $q->orWhere(function ($subQ) use ($location) {
                        if ($location->id_parish) {
                            $subQ->where('id_parish', $location->id_parish);
                        } elseif ($location->id_city) {
                            $subQ->where('id_city', $location->id_city);
                        } elseif ($location->id_district) {
                            $subQ->where('id_district', $location->id_district);
                        }
                    });
                }
            });
        }

        // Filtro de características (features)
        $featureIds = $preference->features->pluck('id')->toArray();
        if (!empty($featureIds)) {
            $query->whereHas('features', function ($q) use ($featureIds) {
                $q->whereIn('property_features.id', $featureIds);
            });
        }

        // Excluir imóveis já associados
        $associatedPropertyIds = $this->properties()->pluck('properties.id');
        if ($associatedPropertyIds->isNotEmpty()) {
            $query->whereNotIn('id', $associatedPropertyIds);
        }

        return $query->with('mainImage', 'transactionType', 'propertyType', 'propertyTypology')->get();
    }

    /**
     * Attach a property to the opportunity
     */
    public function attachProperty(Property $property, ?string $notes = null): void
    {
        $this->properties()->syncWithoutDetaching([
            $property->id => [
                'attached_at' => now(),
                'notes' => $notes,
            ]
        ]);
    }

    /**
     * Detach a property from the opportunity
     */
    public function detachProperty(Property $property): void
    {
        $this->properties()->detach($property->id);
    }

    /**
     * Generate unique reference
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'OPP-' . str_pad((string) (self::max('id') + 1), 6, '0', STR_PAD_LEFT);
        } while (self::where('reference', $reference)->exists());
        
        return $reference;
    }

    /**
     * Scope: Active opportunities (exclude final states)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::getActiveStatuses());
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if opportunity is archived
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archive the opportunity
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Restore the opportunity (unarchive)
     * Note: This is different from SoftDeletes restore() which restores deleted records
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    /**
     * Get all notes for this opportunity
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderBy('created_at', 'desc');
    }

    /**
     * Get deal (closed transaction)
     */
    public function deal()
    {
        return $this->hasOne(Deal::class);
    }

    /**
     * Get approved proposal
     */
    public function getApprovedProposalAttribute()
    {
        return $this->proposals()->where('status', Proposal::STATUS_APROVADA)->first();
    }

    /**
     * Check if opportunity can be finalized (marked as won)
     */
    public function canBeFinalized(): bool
    {
        // Must be in "proposta_aceite" status
        if ($this->status !== self::STATUS_PROPOSTA_ACEITE) {
            return false;
        }

        // Must have exactly one approved proposal
        $approvedCount = $this->proposals()->where('status', Proposal::STATUS_APROVADA)->count();
        if ($approvedCount !== 1) {
            return false;
        }

        // Must not already have a deal
        if ($this->deal()->exists()) {
            return false;
        }

        return true;
    }
}
