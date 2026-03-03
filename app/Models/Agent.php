<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
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
        'specialization',
        'commission_rate',
        'status',
        'color',
        'avatar',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'commission_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ON_LEAVE = 'on_leave';

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Ativo',
            self::STATUS_INACTIVE => 'Inativo',
            self::STATUS_ON_LEAVE => 'Em Licença',
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

    public function getAgentIdAttribute(): string
    {
        return '#AG' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get all notes for this agent
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderBy('created_at', 'desc');
    }

    /**
     * Get deal commissions for this agent
     */
    public function dealCommissions(): HasMany
    {
        return $this->hasMany(DealAgentCommission::class);
    }

    /**
     * Get total commissions earned
     */
    public function getTotalCommissionsEarnedAttribute(): float
    {
        return $this->dealCommissions()
            ->whereHas('deal', function ($query) {
                $query->where('status', Deal::STATUS_FECHADO);
            })
            ->sum('commission_value');
    }

    /**
     * Get the user associated with this agent (1-1 relationship)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the email from the associated user
     */
    public function getEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    /**
     * Get the role from the associated user
     */
    public function getRoleAttribute(): ?string
    {
        return $this->user?->role;
    }

    /**
     * Get the services associated with this agent
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }
}