<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Identificação
        'reference',
        'title',
        'description',
        'status',
        'property_condition_id',
        
        // Negócio
        'transaction_type_id',
        'property_type_id',
        'price',
        'condominium_fee',
        'imi_value',
        'commission_percentage',
        'commission_value',
        
        // Localização
        'country',
        'id_district',
        'id_city',
        'id_parish',
        'address',
        'door',
        'floor_address',
        'side',
        'postal_code',
        'locality',
        'latitude',
        'longitude',
        
        // Características
        'property_typology_id',
        'area_total',
        'area_private',
        'bathrooms',
        'garages',
        'parking_spaces',
        'floor',
        'year_built',
        'energy_certificate',
        
        // Detalhes
        'orientation',

        // Angariador
        'agent_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'condominium_fee' => 'decimal:2',
        'imi_value' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'area_total' => 'decimal:2',
        'area_private' => 'decimal:2',
        'bathrooms' => 'integer',
        'garages' => 'integer',
        'parking_spaces' => 'integer',
        'floor' => 'integer',
        'year_built' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Estados do imóvel
    public const STATUS_DISPONIVEL = 'disponivel';
    public const STATUS_RESERVADO = 'reservado';
    public const STATUS_EM_NEGOCIACAO = 'em_negociacao';
    public const STATUS_VENDIDO = 'vendido';
    public const STATUS_ARRENDADO = 'arrendado';
    public const STATUS_INATIVO = 'inativo';
    public const STATUS_POR_VALIDAR = 'por_validar';
    public const STATUS_EM_VALIDACAO = 'em_validacao';


    // Orientação
    public const ORIENTATION_N = 'N';
    public const ORIENTATION_S = 'S';
    public const ORIENTATION_E = 'E';
    public const ORIENTATION_W = 'W';
    public const ORIENTATION_NE = 'NE';
    public const ORIENTATION_NW = 'NW';
    public const ORIENTATION_SE = 'SE';
    public const ORIENTATION_SW = 'SW';

    // Certificado energético
    public const ENERGY_A = 'A';
    public const ENERGY_B = 'B';
    public const ENERGY_C = 'C';
    public const ENERGY_D = 'D';
    public const ENERGY_E = 'E';
    public const ENERGY_F = 'F';
    public const ENERGY_G = 'G';

    /**
     * Get all status options
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DISPONIVEL => 'Disponível',
            self::STATUS_RESERVADO => 'Reservado',
            self::STATUS_EM_NEGOCIACAO => 'Em Negociação',
            self::STATUS_VENDIDO => 'Vendido',
            self::STATUS_ARRENDADO => 'Arrendado',
            self::STATUS_INATIVO => 'Inativo',
            self::STATUS_POR_VALIDAR => 'Por Validar',
            self::STATUS_EM_VALIDACAO => 'Em Validação',
        ];
    }

    /**
     * Get all transaction types from database
     */
    public static function transactionTypes(): array
    {
        return TransactionType::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get all orientation options
     */
    public static function orientations(): array
    {
        return [
            self::ORIENTATION_N => 'Norte',
            self::ORIENTATION_S => 'Sul',
            self::ORIENTATION_E => 'Este',
            self::ORIENTATION_W => 'Oeste',
            self::ORIENTATION_NE => 'Nordeste',
            self::ORIENTATION_NW => 'Noroeste',
            self::ORIENTATION_SE => 'Sudeste',
            self::ORIENTATION_SW => 'Sudoeste',
        ];
    }

    /**
     * Get all energy certificate options
     */
    public static function energyCertificates(): array
    {
        return [
            self::ENERGY_A => 'A',
            self::ENERGY_B => 'B',
            self::ENERGY_C => 'C',
            self::ENERGY_D => 'D',
            self::ENERGY_E => 'E',
            self::ENERGY_F => 'F',
            self::ENERGY_G => 'G',
        ];
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DISPONIVEL => 'success',
            self::STATUS_RESERVADO => 'warning',
            self::STATUS_EM_NEGOCIACAO => 'info',
            self::STATUS_VENDIDO => 'primary',
            self::STATUS_ARRENDADO => 'primary',
            self::STATUS_INATIVO => 'secondary',
            self::STATUS_POR_VALIDAR => 'warning',
            self::STATUS_EM_VALIDACAO => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price) {
            return '—';
        }
        return number_format($this->price, 2, ',', '.') . ' €';
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->parish_name,
            $this->city_name,
            $this->district_name,
            $this->postal_code,
        ]);
        
        return implode(', ', $parts) ?: '—';
    }

    /**
     * Get district name
     */
    public function getDistrictNameAttribute(): ?string
    {
        if (!$this->id_district) {
            return null;
        }
        return Local::getDistrictNameById($this->id_district);
    }

    /**
     * Get city name
     */
    public function getCityNameAttribute(): ?string
    {
        if (!$this->id_city) {
            return null;
        }
        return Local::getCityNameById($this->id_city);
    }

    /**
     * Get parish name
     */
    public function getParishNameAttribute(): ?string
    {
        if (!$this->id_parish) {
            return null;
        }
        return Local::getParishNameById($this->id_parish);
    }

    /**
     * Get transaction type
     */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * Get property type
     */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * Get property typology
     */
    public function propertyTypology(): BelongsTo
    {
        return $this->belongsTo(PropertyTypology::class);
    }

    /**
     * Get property condition
     */
    public function propertyCondition(): BelongsTo
    {
        return $this->belongsTo(PropertyCondition::class);
    }

    /**
     * Get angariador (agent who listed the property)
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get property features (many-to-many)
     */
    public function features()
    {
        return $this->belongsToMany(PropertyFeature::class, 'property_property_feature')
                    ->withTimestamps();
    }

    /**
     * Get property images
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order');
    }

    /**
     * Get proposals for this property
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get visits for this property
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('scheduled_at', 'desc');
    }

    /**
     * Get main image
     */
    public function mainImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_main', true);
    }

    /**
     * Scope: Active properties (available status)
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_DISPONIVEL);
    }

    /**
     * Scope: Available properties
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_DISPONIVEL);
    }

    /**
     * Scope: By transaction type
     */
    public function scopeByTransactionType($query, $typeId)
    {
        return $query->where('transaction_type_id', $typeId);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Generate unique reference
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'PRP-' . str_pad((string) (self::max('id') + 1), 6, '0', STR_PAD_LEFT);
        } while (self::where('reference', $reference)->exists());
        
        return $reference;
    }

    /**
     * Get all notes for this property
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderBy('created_at', 'desc');
    }

    /**
     * Get deals for this property
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
