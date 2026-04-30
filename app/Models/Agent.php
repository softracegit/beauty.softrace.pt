<?php

namespace App\Models;

use App\Support\PhoneDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Agent extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'nif', 'birth_date', 'gender', 'nationality', 'marital_status', 'address', 'postal_code', 'locality', 'specialization', 'commission_rate', 'commission_unit', 'status', 'visible_in_agenda', 'visible_in_booking', 'agenda_order', 'weekly_schedule'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Membro criado',
                'updated' => 'Membro atualizado',
                'deleted' => 'Membro eliminado',
                default => 'Membro alterado',
            });
    }

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
        'commission_unit',
        'status',
        'visible_in_agenda',
        'visible_in_booking',
        'agenda_order',
        'color',
        'avatar',
        'weekly_schedule',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'commission_rate' => 'decimal:2',
        'visible_in_agenda' => 'boolean',
        'visible_in_booking' => 'boolean',
        'agenda_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'weekly_schedule' => 'array',
    ];

    /** Segunda a domingo (chaves alinhadas com a agenda em JS). */
    public const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const COMMISSION_UNIT_PERCENT = 'percent';

    public const COMMISSION_UNIT_EURO = 'euro';

    public static function weekdayLabels(): array
    {
        return [
            'mon' => 'Segunda-feira',
            'tue' => 'Terça-feira',
            'wed' => 'Quarta-feira',
            'thu' => 'Quinta-feira',
            'fri' => 'Sexta-feira',
            'sat' => 'Sábado',
            'sun' => 'Domingo',
        ];
    }

    public static function timeStringToMinutes(string $hhmm): int
    {
        [$h, $m] = explode(':', $hhmm);

        return (int) $h * 60 + (int) $m;
    }

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

    /** Chave (BD) => rótulo na UI */
    public static function specializations(): array
    {
        return [
            'manicure' => 'Manicure',
            'pedicure' => 'Pedicure',
            'nail_art' => 'Nail Art',
            'lash_designer' => 'Lash Designer',
            'estetica_rosto' => 'Estética Rosto',
            'depilacao' => 'Depilação',
        ];
    }

    public static function specializationLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::specializations()[$value] ?? $value;
    }

    /**
     * Converte texto livre antigo para chave de especialização (migração e tolerância).
     */
    public static function normalizeLegacySpecialization(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }

        $keys = array_keys(self::specializations());
        $lower = mb_strtolower($trim);
        if (in_array($lower, $keys, true)) {
            return $lower;
        }

        $norm = str_replace(['á', 'à', 'ã', 'â'], 'a', $lower);
        $norm = str_replace(['é', 'ê'], 'e', $norm);
        $norm = str_replace('í', 'i', $norm);
        $norm = str_replace(['ó', 'ô'], 'o', $norm);
        $norm = str_replace('ú', 'u', $norm);
        $norm = str_replace('ç', 'c', $norm);

        if (str_contains($norm, 'nail')) {
            return 'nail_art';
        }
        if (str_contains($norm, 'lash')) {
            return 'lash_designer';
        }
        if (str_contains($norm, 'manicure')) {
            return 'manicure';
        }
        if (str_contains($norm, 'pedicure')) {
            return 'pedicure';
        }
        if (str_contains($norm, 'depil')) {
            return 'depilacao';
        }
        if (str_contains($norm, 'estetica') && str_contains($norm, 'rosto')) {
            return 'estetica_rosto';
        }

        return null;
    }

    public function getAgentIdAttribute(): string
    {
        return '#AG'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Telefone para exibição (indicativo e número conforme o país).
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        $raw = $this->attributes['phone'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return PhoneDisplay::formatInternational($raw) ?? $raw;
    }

    /**
     * Texto da taxa de comissão para listagens e fichas (ex.: "12,50 %" ou "15,00 €").
     */
    public function formatCommissionDisplay(): ?string
    {
        if ($this->commission_rate === null) {
            return null;
        }

        $num = number_format((float) $this->commission_rate, 2, ',', ' ');
        $unit = $this->commission_unit ?? self::COMMISSION_UNIT_PERCENT;

        return $unit === self::COMMISSION_UNIT_EURO
            ? $num.' €'
            : $num.' %';
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
