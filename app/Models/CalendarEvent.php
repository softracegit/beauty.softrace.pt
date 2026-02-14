<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarEvent extends Model
{
    public const TYPE_MANUAL = 'manual';
    public const TYPE_VISITA = 'visita';
    public const TYPE_LEAD = 'lead';
    public const TYPE_OUTRO = 'outro';

    public const STATUS_AGENDADO = 'agendado';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_CHEGOU = 'chegou';
    public const STATUS_INICIADO = 'iniciado';
    public const STATUS_FALTOU = 'faltou';
    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'title',
        'start_at',
        'end_at',
        'description',
        'user_id',
        'event_type',
        'status',
        'eventable_type',
        'eventable_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_AGENDADO,
    ];

    public static function eventTypes(): array
    {
        return [
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_VISITA => 'Visita',
            self::TYPE_LEAD => 'Lead',
            self::TYPE_OUTRO => 'Outro',
        ];
    }

    public static function typeClassMap(): array
    {
        return [
            self::TYPE_MANUAL => 'bg-primary',
            self::TYPE_VISITA => 'bg-success',
            self::TYPE_LEAD => 'bg-info',
            self::TYPE_OUTRO => 'bg-secondary',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSourceEditable(): bool
    {
        return $this->event_type === self::TYPE_MANUAL || $this->event_type === self::TYPE_OUTRO;
    }

    public function isDeletableFromCalendar(): bool
    {
        return $this->isSourceEditable();
    }

    public function isTimeEditable(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_AGENDADO => 'Agendado',
            self::STATUS_CONFIRMADO => 'Confirmado',
            self::STATUS_CHEGOU => 'Chegou',
            self::STATUS_INICIADO => 'Iniciado',
            self::STATUS_FALTOU => 'Faltou',
            self::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * Get the icon class for the current status.
     */
    public function getStatusIconAttribute(): ?string
    {
        $status = $this->status ?? self::STATUS_AGENDADO;
        return match ($status) {
            self::STATUS_AGENDADO => null, // Sem ícone
            self::STATUS_CONFIRMADO => 'ri-check-line',
            self::STATUS_CHEGOU => 'ri-map-pin-line',
            self::STATUS_INICIADO => 'ri-play-line',
            self::STATUS_FALTOU => 'ri-close-circle-line',
            self::STATUS_CANCELADO => 'ri-forbid-line',
            default => null,
        };
    }

    /**
     * Check if a status transition is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->status ?? self::STATUS_AGENDADO;
        
        // Estados bloqueados não podem transitar diretamente para estados ativos
        $blockedStates = [self::STATUS_FALTOU, self::STATUS_CANCELADO];
        $activeStates = [self::STATUS_INICIADO, self::STATUS_CHEGOU];

        if (in_array($currentStatus, $blockedStates) && in_array($newStatus, $activeStates)) {
            return false;
        }

        return true;
    }
}
