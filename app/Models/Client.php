<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'nif',
        'birth_date',
        'gender',
        'nationality',
        'marital_status',
        'address',
        'door',
        'floor',
        'side',
        'postal_code',
        'locality',
        'id_district',
        'id_city',
        'id_parish',
        'status',
        'type',
        'avatar',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_ACTIVE = 'active';

    // Tipos de cliente
    public const TYPE_POTENCIAL_CLIENTE = 'potencial_cliente';
    // Outros tipos serão adicionados no futuro

    public static function statusLabels(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_UNAVAILABLE => 'Unavailable',
            self::STATUS_ACTIVE => 'Active',
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_POTENCIAL_CLIENTE => 'Potencial Cliente',
            // Outros tipos serão adicionados no futuro
        ];
    }

    public static function genders(): array
    {
        return [
            'M' => 'Masculino',
            'F' => 'Feminino',
            'O' => 'Outro',
        ];
    }

    public static function maritalStatuses(): array
    {
        return [
            'single' => 'Solteiro(a)',
            'married' => 'Casado(a)',
            'divorced' => 'Divorciado(a)',
            'widowed' => 'Viúvo(a)',
            'separated' => 'Separado(a)',
            'cohabiting' => 'União de Facto',
        ];
    }

    public function getClientIdAttribute(): string
    {
        return '#CL' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get all notes for this client
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderBy('created_at', 'desc');
    }

    /**
     * Get all deals for this client
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Get all leads for this client (via opportunities)
     */
    public function leads(): HasManyThrough
    {
        return $this->hasManyThrough(Lead::class, Opportunity::class, 'client_id', 'id', 'id', 'lead_id');
    }

    /**
     * Get all opportunities for this client
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
