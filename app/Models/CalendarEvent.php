<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Support\ActivityLogContext;
use App\Support\ActivityLogMarcacaoOrigin;
use App\Support\StoreBusinessTime;
use Carbon\CarbonInterface;
use Spatie\Activitylog\Contracts\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CalendarEvent extends Model
{
    use BelongsToStore, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'start_at',
                'end_at',
                'description',
                'user_id',
                'client_id',
                'service_id',
                'event_type',
                'personal_time_type_id',
                'status',
                'cancellation_reason',
                'cancellation_notes',
                'cancellation_type',
                'refund_reserva',
                'avisou_dentro_prazo',
                'cancellation_evaluated_at',
                'cancellation_notice_hours_applied',
                'wallet_credit_amount_cents',
                'eventable_type',
                'eventable_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Evento criado na agenda',
                'updated' => 'Marcação atualizada',
                'deleted' => 'Evento eliminado da agenda',
                default => 'Evento da agenda alterado',
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        ActivityLogContext::attachMarcacao($activity, $this);

        if ($eventName === 'created') {
            if (($this->event_type ?? '') === self::TYPE_MARCACAO) {
                $origem = ActivityLogMarcacaoOrigin::resolveForCreation($this);
                ActivityLogMarcacaoOrigin::attach($activity, $origem);
                $origemLabel = ActivityLogMarcacaoOrigin::label($origem);
                $activity->description = $origemLabel !== null
                    ? 'Marcação criada ('.$origemLabel.')'
                    : 'Marcação criada';
            } else {
                $activity->description = match ($this->event_type) {
                    self::TYPE_TEMPO_PESSOAL => 'Tempo pessoal criado',
                    self::TYPE_MANUAL, self::TYPE_OUTRO => 'Evento manual criado',
                    self::TYPE_VISITA => 'Visita agendada',
                    self::TYPE_LEAD => 'Evento de lead criado',
                    default => 'Evento criado na agenda',
                };
            }
        } elseif ($eventName === 'updated') {
            // Logs manuais (pagamentos, serviços, etc.) não têm attributes/old — não sobrescrever a descrição.
            $props = $activity->properties;
            $attrs = is_object($props) ? $props->get('attributes') : ($props['attributes'] ?? null);
            $old = is_object($props) ? $props->get('old') : ($props['old'] ?? null);
            if ($attrs !== null || $old !== null) {
                $activity->description = $this->describeActivityUpdate();
            }
        } elseif ($eventName === 'deleted') {
            $activity->description = match ($this->event_type) {
                self::TYPE_MARCACAO => 'Marcação eliminada',
                self::TYPE_TEMPO_PESSOAL => 'Tempo pessoal eliminado',
                default => 'Evento eliminado da agenda',
            };
        }
    }

    protected function describeActivityUpdate(): string
    {
        $changed = array_values(array_diff(array_keys($this->getChanges()), ['updated_at']));
        if ($changed === []) {
            return 'Marcação atualizada';
        }
        sort($changed);

        $cancelFields = ['cancellation_reason', 'cancellation_notes', 'cancellation_type', 'refund_reserva', 'avisou_dentro_prazo'];
        $hasCancel = count(array_intersect($changed, $cancelFields)) > 0;
        $hasStatus = in_array('status', $changed, true);
        $oldStatus = (string) ($this->getOriginal('status') ?? '');
        $newStatus = (string) ($this->status ?? '');

        if (
            $hasStatus
            && in_array($oldStatus, [self::STATUS_CANCELADO, self::STATUS_FALTOU], true)
            && $newStatus === self::STATUS_AGENDADO
        ) {
            return 'Marcação reativada';
        }

        if ($hasCancel && $hasStatus) {
            return 'Estado e dados de cancelamento/falta atualizados';
        }
        if ($hasCancel) {
            return 'Dados de cancelamento/falta atualizados';
        }
        if ($hasStatus && count($changed) === 1) {
            return 'Estado da marcação alterado';
        }

        if ($changed === ['end_at', 'start_at']) {
            return 'Data e hora da marcação alteradas';
        }
        if (count($changed) === 1) {
            return match ($changed[0]) {
                'start_at' => 'Horário de início alterado',
                'end_at' => 'Horário de fim alterado',
                'user_id' => 'Marcação transferida para outro técnico',
                'status' => 'Estado da marcação alterado',
                'client_id' => 'Cliente da marcação alterado',
                'description' => 'Observações alteradas',
                'event_type' => 'Tipo de evento alterado',
                'service_id' => 'Serviço principal alterado',
                'personal_time_type_id' => 'Tipo de tempo pessoal alterado',
                'eventable_type' => 'Origem do evento alterada',
                'eventable_id' => 'Origem do evento alterada',
                default => 'Marcação atualizada',
            };
        }

        if (! array_diff($changed, ['start_at', 'end_at']) && count($changed) <= 2) {
            return 'Data e hora da marcação alteradas';
        }

        return 'Marcação atualizada';
    }

    public const TYPE_MANUAL = 'manual';

    public const TYPE_OUTRO = 'outro';

    public const TYPE_MARCACAO = 'marcacao';

    public const TYPE_TEMPO_PESSOAL = 'tempo_pessoal';

    public const TYPE_VISITA = 'visita';

    public const TYPE_LEAD = 'lead';

    public const STATUS_AGENDADO = 'agendado';

    public const STATUS_NOTIFICADO = 'notificado';

    public const STATUS_CONFIRMADO = 'confirmado';

    public const STATUS_CHEGOU = 'chegou';

    public const STATUS_INICIADO = 'iniciado';

    /** Serviço concluído, ainda sem pagamento (antes de «Concluído» com fatura). */
    public const STATUS_TERMINADO = 'terminado';

    public const STATUS_FALTOU = 'faltou';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_ANULADO = 'anulado';

    public const STATUS_COMPLETO = 'completo';

    /**
     * Marcações reais da agenda (exclui tempo pessoal e outros tipos de evento).
     *
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeOnlyMarcacoes(Builder $query): Builder
    {
        return $query
            ->where('event_type', self::TYPE_MARCACAO)
            ->whereNull('personal_time_type_id');
    }

    /**
     * Marcações que entram nas métricas do dashboard (sem canceladas/anuladas).
     *
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeCountableForDashboard(Builder $query): Builder
    {
        return $query
            ->onlyMarcacoes()
            ->whereNotIn('status', self::dashboardExcludedStatuses());
    }

    /** @return list<string> */
    public static function dashboardExcludedStatuses(): array
    {
        return [
            self::STATUS_CANCELADO,
            self::STATUS_ANULADO,
        ];
    }

    /**
     * Marcações cujo horário já passou (fim ≤ agora, ou início < agora se não há fim).
     *
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeAlreadyPassed(Builder $query, CarbonInterface $nowUtc): Builder
    {
        return $query->where(function (Builder $q) use ($nowUtc) {
            $q->where(function (Builder $q2) use ($nowUtc) {
                $q2->whereNotNull('end_at')->where('end_at', '<=', $nowUtc);
            })->orWhere(function (Builder $q2) use ($nowUtc) {
                $q2->whereNull('end_at')->where('start_at', '<', $nowUtc);
            });
        });
    }

    protected $fillable = [
        'store_id',
        'title',
        'start_at',
        'end_at',
        'description',
        'user_id',
        'client_id',
        'service_id',
        'event_type',
        'personal_time_type_id',
        'status',
        'marcacao_source',
        'booking_sms_reminder_sent_at',
        'booking_sms_reminder_failed_at',
        'cancellation_reason',
        'cancellation_notes',
        'cancellation_type',
        'refund_reserva',
        'avisou_dentro_prazo',
        'cancellation_evaluated_at',
        'cancellation_notice_hours_applied',
        'wallet_credit_amount_cents',
        'eventable_type',
        'eventable_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'booking_sms_reminder_sent_at' => 'datetime',
        'booking_sms_reminder_failed_at' => 'datetime',
        'cancellation_evaluated_at' => 'datetime',
        'refund_reserva' => 'boolean',
        'avisou_dentro_prazo' => 'boolean',
        'cancellation_notice_hours_applied' => 'integer',
        'wallet_credit_amount_cents' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_AGENDADO,
    ];

    public static function eventTypes(): array
    {
        return [
            self::TYPE_MARCACAO => 'Marcação',
            self::TYPE_TEMPO_PESSOAL => 'Tempo pessoal',
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_VISITA => 'Visita',
            self::TYPE_LEAD => 'Lead',
            self::TYPE_OUTRO => 'Outro',
        ];
    }

    public static function marcacaoSourceLabels(): array
    {
        return [
            ActivityLogMarcacaoOrigin::ONLINE => 'Booking',
            ActivityLogMarcacaoOrigin::AGENDA => 'Agenda',
            ActivityLogMarcacaoOrigin::SISTEMA => 'Sistema',
        ];
    }

    public static function normalizeMarcacaoSource(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        if ($source === ActivityLogMarcacaoOrigin::IMPORT) {
            return ActivityLogMarcacaoOrigin::AGENDA;
        }

        return array_key_exists($source, self::marcacaoSourceLabels()) ? $source : null;
    }

    public function resolvedMarcacaoSource(): ?string
    {
        if (($this->event_type ?? '') !== self::TYPE_MARCACAO) {
            return null;
        }

        $stored = self::normalizeMarcacaoSource($this->marcacao_source);
        if ($stored !== null) {
            return $stored;
        }

        if ($this->relationLoaded('onlineBooking')) {
            if ($this->onlineBooking) {
                return ActivityLogMarcacaoOrigin::ONLINE;
            }
        } elseif ($this->exists) {
            $this->loadMissing('onlineBooking');
            if ($this->onlineBooking) {
                return ActivityLogMarcacaoOrigin::ONLINE;
            }
        }

        return ActivityLogMarcacaoOrigin::AGENDA;
    }

    public function marcacaoSourceLabel(): ?string
    {
        $source = $this->resolvedMarcacaoSource();

        return $source !== null ? (self::marcacaoSourceLabels()[$source] ?? null) : null;
    }

    public function isOnlineMarcacao(): bool
    {
        return $this->resolvedMarcacaoSource() === ActivityLogMarcacaoOrigin::ONLINE;
    }

    public function isAgendaMarcacao(): bool
    {
        return $this->resolvedMarcacaoSource() === ActivityLogMarcacaoOrigin::AGENDA;
    }

    /**
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeMarcacaoFromBooking(Builder $query): Builder
    {
        return $query
            ->where('event_type', self::TYPE_MARCACAO)
            ->where('marcacao_source', ActivityLogMarcacaoOrigin::ONLINE);
    }

    /**
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeMarcacaoFromAgenda(Builder $query): Builder
    {
        return $query
            ->where('event_type', self::TYPE_MARCACAO)
            ->where('marcacao_source', ActivityLogMarcacaoOrigin::AGENDA);
    }

    public static function typeClassMap(): array
    {
        return [
            self::TYPE_MARCACAO => 'bg-primary',
            self::TYPE_TEMPO_PESSOAL => 'agenda-event-tempo-pessoal',
            self::TYPE_MANUAL => 'bg-primary',
            self::TYPE_VISITA => 'bg-success',
            self::TYPE_LEAD => 'bg-info',
            self::TYPE_OUTRO => 'bg-secondary',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function personalTimeType(): BelongsTo
    {
        return $this->belongsTo(PersonalTimeType::class);
    }

    /**
     * Serviços associados à marcação (muitos-para-muitos com preço/duração customizados).
     */
    public function eventServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'calendar_event_services')
            ->using(\App\Models\CalendarEventService::class)
            ->withPivot(
                'id',
                'service_option_id',
                'option_name',
                'option_duration',
                'option_price',
                'option_online_price',
                'duration',
                'price',
                'original_price',
                'sort_order',
            )
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Itens de serviço (pivot) com service e extras - para eager loading.
     */
    public function eventServiceItems(): HasMany
    {
        return $this->hasMany(CalendarEventService::class, 'calendar_event_id')->orderBy('sort_order');
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sale(): HasOne
    {
        // Usar sempre a venda mais recente associada a esta marcação
        return $this->hasOne(Sale::class)->latest('id');
    }

    /**
     * Todas as vendas/faturas desta marcação (reserva + loja, etc.), por ordem cronológica.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'calendar_event_id')->orderBy('id');
    }

    /**
     * Vendas em que esta marcação foi liquidada (inclui consolidados).
     */
    public function consolidatedSales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, 'sale_calendar_events')
            ->withPivot(['amount_settled_cents', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Registo de marcação online (depósito Stripe), quando existir.
     */
    public function onlineBooking(): HasOne
    {
        return $this->hasOne(Booking::class, 'calendar_event_id');
    }

    public function isSourceEditable(): bool
    {
        return in_array($this->event_type, [
            self::TYPE_MANUAL,
            self::TYPE_OUTRO,
            self::TYPE_MARCACAO,
            self::TYPE_TEMPO_PESSOAL,
        ], true);
    }

    public function isDeletableFromCalendar(): bool
    {
        return $this->isSourceEditable();
    }

    public function isTimeEditable(): bool
    {
        return ! $this->isMarcacaoStatusLocked();
    }

    /**
     * Marcações finalizadas por falta ou cancelamento não podem ser editadas nem pagas.
     */
    public function isMarcacaoStatusLocked(): bool
    {
        if (($this->event_type ?? '') !== self::TYPE_MARCACAO) {
            return false;
        }

        return in_array($this->status ?? '', [self::STATUS_FALTOU, self::STATUS_CANCELADO, self::STATUS_ANULADO], true);
    }

    /**
     * Margem (minutos) para ainda criar/editar no “quase passado” (ex.: às 17:00 marcar 16:50).
     */
    public const PAST_CREATE_GRACE_MINUTES = 20;

    /**
     * Horário de início já passou (ou é agora) no fuso da loja — necessário para registar falta.
     */
    public function hasStartedForStore(): bool
    {
        if (! $this->start_at) {
            return false;
        }

        $storeId = (int) ($this->store_id ?? 0);
        if ($storeId <= 0) {
            return $this->start_at->copy()->utc()->lte(now()->utc());
        }

        return $this->start_at->copy()->utc()->lte(StoreBusinessTime::nowUtcForStore($storeId));
    }

    /**
     * Início já passou no fuso da loja (relógio de parede), com margem opcional.
     */
    public function startsInPastForStore(int $graceMinutes = 0): bool
    {
        if (! $this->start_at) {
            return false;
        }

        $storeId = (int) ($this->store_id ?? 0);
        if ($storeId <= 0) {
            $cutoff = now()->subMinutes(max(0, $graceMinutes));

            return $this->start_at->lt($cutoff);
        }

        return self::startIsTooFarInPastForStore(
            $this->start_at,
            $storeId,
            max(0, $graceMinutes),
        );
    }

    /**
     * O início está antes de (agora na loja − margem). Usado ao criar/mover marcações e tempos pessoais.
     */
    public static function startIsTooFarInPastForStore(
        CarbonInterface $start,
        int $storeId,
        ?int $graceMinutes = null,
    ): bool {
        $grace = $graceMinutes ?? self::PAST_CREATE_GRACE_MINUTES;
        $cutoff = StoreBusinessTime::nowUtcForStore($storeId)->subMinutes(max(0, $grace));

        return $start->copy()->utc()->lt($cutoff);
    }

    /**
     * Registo retroativo na agenda (início antes de agora na loja): sem emails/sininho de criação/alteração.
     */
    public function shouldSendBookingNotifications(): bool
    {
        if (! $this->start_at) {
            return true;
        }

        $storeId = (int) ($this->store_id ?? 0);
        if ($storeId <= 0) {
            return ! $this->start_at->isPast();
        }

        $tz = StoreBusinessTime::timezoneForStore($storeId);
        $startLocal = $this->start_at->copy()->timezone($tz);

        return $startLocal->gt(StoreBusinessTime::nowForStore($storeId));
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_AGENDADO => 'Agendado',
            self::STATUS_NOTIFICADO => 'Notificado',
            self::STATUS_CONFIRMADO => 'Confirmado',
            self::STATUS_CHEGOU => 'Chegou',
            self::STATUS_INICIADO => 'Iniciado',
            self::STATUS_TERMINADO => 'Terminado',
            self::STATUS_FALTOU => 'Faltou',
            self::STATUS_CANCELADO => 'Cancelado',
            self::STATUS_ANULADO => 'Anulado',
            self::STATUS_COMPLETO => 'Pago',
        ];
    }

    /**
     * Get the icon class for the current status (Remix Icon fill + classes de cor em agenda.css).
     */
    public function getStatusIconAttribute(): ?string
    {
        $status = $this->status ?? self::STATUS_AGENDADO;

        return match ($status) {
            self::STATUS_AGENDADO => 'ri-time-fill agenda-status-icon-agendado',
            self::STATUS_NOTIFICADO => 'ri-notification-3-fill agenda-status-icon-notificado',
            self::STATUS_CONFIRMADO => 'ri-notification-3-fill agenda-status-icon-confirmado',
            self::STATUS_CHEGOU => 'ri-map-pin-fill agenda-status-icon-chegou',
            self::STATUS_INICIADO => 'ri-play-fill agenda-status-icon-iniciado',
            self::STATUS_TERMINADO => 'ri-checkbox-circle-fill agenda-status-icon-confirmado',
            self::STATUS_FALTOU => 'ri-forbid-fill',
            self::STATUS_CANCELADO => 'ri-close-circle-fill',
            self::STATUS_ANULADO => 'ri-close-circle-fill',
            self::STATUS_COMPLETO => 'ri-checkbox-circle-fill agenda-status-icon-confirmado',
            default => null,
        };
    }

    /**
     * Check if a status transition is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->status ?? self::STATUS_AGENDADO;

        if ($currentStatus === $newStatus) {
            return true;
        }

        if (in_array($currentStatus, [self::STATUS_FALTOU, self::STATUS_CANCELADO, self::STATUS_ANULADO], true)) {
            return false;
        }

        if ($newStatus === self::STATUS_TERMINADO) {
            return in_array($currentStatus, [self::STATUS_INICIADO, self::STATUS_CHEGOU], true);
        }

        if ($newStatus === self::STATUS_COMPLETO) {
            return ! in_array($currentStatus, [self::STATUS_FALTOU, self::STATUS_CANCELADO, self::STATUS_ANULADO], true);
        }

        // Estados bloqueados não podem transitar diretamente para estados ativos
        $blockedStates = [self::STATUS_FALTOU, self::STATUS_CANCELADO, self::STATUS_ANULADO];
        $activeStates = [self::STATUS_INICIADO, self::STATUS_CHEGOU];

        if (in_array($currentStatus, $blockedStates) && in_array($newStatus, $activeStates)) {
            return false;
        }

        return true;
    }
}
