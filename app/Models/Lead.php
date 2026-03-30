<?php

namespace App\Models;

use App\Support\PhoneDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Models\Opportunity;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'origin',
        'email',
        'phone',
        'address',
        'door',
        'floor',
        'side',
        'postal_code',
        'locality',
        'id_district',
        'id_city',
        'id_parish',
        'priority',
        'property_reference',
        'status',
        'agent_id',
        'notes',
        'status_changed_at',
        'scheduled_at',
        'archived_at',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    // Tipos de lead
    public const TYPE_COMPRA = 'compra';
    public const TYPE_ARRENDAMENTO = 'arrendamento';
    public const TYPE_ANGARIACAO = 'angariacao';

    // Estados da lead
    public const STATUS_POR_TRATAR = 'por_tratar';
    public const STATUS_EM_CONTACTO = 'em_contacto';
    public const STATUS_AGENDADO = 'agendado';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_GANHO = 'ganho';
    public const STATUS_PERDIDO = 'perdido';

    // Prioridades
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public static function types(): array
    {
        return [
            self::TYPE_COMPRA => 'Compra',
            self::TYPE_ARRENDAMENTO => 'Arrendamento',
            self::TYPE_ANGARIACAO => 'Angariação',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_POR_TRATAR => 'Por Tratar',
            self::STATUS_EM_CONTACTO => 'Em Contacto',
            self::STATUS_AGENDADO => 'Agendado',
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_GANHO => 'Ganho',
            self::STATUS_PERDIDO => 'Perdido',
        ];
    }

    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Baixa',
            self::PRIORITY_MEDIUM => 'Média',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_URGENT => 'Urgente',
        ];
    }

    public static function origins(): array
    {
        return [
            'portal' => 'Portal',
            'site' => 'Site',
            'presencial' => 'Presencial',
            'telefone' => 'Telefone',
            'email' => 'Email',
            'referencia' => 'Referência',
            'redes_sociais' => 'Redes Sociais',
            'outro' => 'Outro',
        ];
    }

    public static function getStatusOrder(): array
    {
        return [
            self::STATUS_POR_TRATAR,
            self::STATUS_EM_CONTACTO,
            self::STATUS_AGENDADO,
            self::STATUS_PENDENTE,
            self::STATUS_GANHO,
            self::STATUS_PERDIDO,
        ];
    }

    /**
     * Get status order for progress bar (excludes "Perdido")
     */
    public static function getProgressStatusOrder(): array
    {
        return [
            self::STATUS_POR_TRATAR,
            self::STATUS_EM_CONTACTO,
            self::STATUS_AGENDADO,
            self::STATUS_PENDENTE,
            self::STATUS_GANHO,
        ];
    }

    public function getStatusIndex(): int
    {
        $order = self::getProgressStatusOrder();
        return array_search($this->status, $order) !== false ? array_search($this->status, $order) : 0;
    }

    public function getNextStatuses(): array
    {
        $order = self::getProgressStatusOrder();
        $currentIndex = $this->getStatusIndex();
        return array_slice($order, $currentIndex + 1);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get all notes for this lead
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderBy('created_at', 'desc');
    }
    
    /**
     * Legacy method for backward compatibility
     * @deprecated Use notes() instead
     */
    public function leadNotes(): MorphMany
    {
        return $this->notes();
    }

    public function propertyPreferences(): HasMany
    {
        return $this->hasMany(LeadPropertyPreference::class);
    }

    /**
     * Get the opportunity associated with this lead
     */
    public function opportunity(): HasOne
    {
        return $this->hasOne(Opportunity::class, 'lead_id');
    }

    public function calendarEvent(): MorphOne
    {
        return $this->morphOne(CalendarEvent::class, 'eventable');
    }

    public function getLeadIdAttribute(): string
    {
        return '#LD' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

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

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_GANHO => 'success',
            self::STATUS_PERDIDO => 'danger',
            self::STATUS_AGENDADO => 'primary',
            self::STATUS_EM_CONTACTO => 'info',
            self::STATUS_PENDENTE => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Check if lead is archived
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archive the lead
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Restore the lead (unarchive)
     */
    public function restore(): void
    {
        $this->update(['archived_at' => null]);
    }

    protected static function booted(): void
    {
        static::deleting(function (Lead $lead) {
            $lead->calendarEvent?->delete();
        });
    }
}
