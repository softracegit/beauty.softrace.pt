<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Support\ActivityLogContext;
use App\Support\PhoneDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Agent extends Model
{
    use BelongsToStore, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'nif', 'birth_date', 'gender', 'nationality', 'marital_status', 'address', 'postal_code', 'locality', 'specialization', 'commission_rate', 'commission_unit', 'status', 'visible_in_agenda', 'visible_in_booking', 'booking_slug', 'agenda_order', 'weekly_schedule'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Membro criado',
                'updated' => 'Membro atualizado',
                'deleted' => 'Membro eliminado',
                default => 'Membro alterado',
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            ActivityLogContext::attachSubjectLabel($activity, $name);
            $base = (string) ($activity->description ?? 'Membro alterado');
            if (! str_contains($base, $name)) {
                $activity->description = $base.': '.$name;
            }
        }
    }

    protected $fillable = [
        'user_id',
        'store_id',
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
        'booking_slug',
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
     * Segmentos reservados no prefixo /booking/{loja}/ — não podem ser usados como booking_slug.
     *
     * @return list<string>
     */
    public static function reservedBookingSlugs(): array
    {
        return [
            'auth',
            'checkout',
            'confirmacao',
            'conta',
            'disponibilidade',
            'disponiblidade',
            'login',
            'marcacao',
            'pagamento',
            'politica-cancelamento',
            'sair',
            'servico',
            'session',
            'slot-hold',
            'staff',
        ];
    }

    public static function normalizeBookingSlug(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $slug = Str::slug(trim($raw));
        if ($slug === '') {
            return null;
        }

        return $slug;
    }

    public static function bookingSlugExists(int $storeId, string $slug, ?int $ignoreAgentId = null): bool
    {
        $query = self::query()
            ->where('store_id', $storeId)
            ->where('booking_slug', $slug);

        if ($ignoreAgentId !== null) {
            $query->whereKeyNot($ignoreAgentId);
        }

        return $query->exists();
    }

    public static function generateUniqueBookingSlug(int $storeId, string $name, ?int $ignoreAgentId = null): string
    {
        $base = self::normalizeBookingSlug($name) ?? 'membro';
        if (in_array($base, self::reservedBookingSlugs(), true)) {
            $base .= '-2';
        }

        $slug = $base;
        $suffix = 2;
        while (self::bookingSlugExists($storeId, $slug, $ignoreAgentId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function publicBookingPath(?Store $store = null): ?string
    {
        $slug = self::normalizeBookingSlug($this->booking_slug);
        if ($slug === null) {
            return null;
        }

        $storeSlug = $store instanceof Store
            ? trim((string) $store->slug)
            : trim((string) ($this->relationLoaded('store') ? $this->store?->slug : $this->store()->value('slug')));

        if ($storeSlug === '') {
            return null;
        }

        return '/booking/'.$storeSlug.'/'.$slug;
    }

    public function publicBookingUrl(?Store $store = null): ?string
    {
        $path = $this->publicBookingPath($store);

        return $path !== null ? url($path) : null;
    }

    public function isEligibleForPublicBookingPage(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || ! $this->visible_in_booking) {
            return false;
        }

        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
        if (! $user instanceof User) {
            return false;
        }

        return User::query()
            ->whereKey($user->getKey())
            ->eligibleForPublicBooking()
            ->exists();
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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

    /**
     * Eager load serviços da mesma loja (evita pivots cruzados e problemas com `services:id` no many-to-many).
     *
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeWithServicesForStore(Builder $query, int $storeId): Builder
    {
        return $query->with([
            'services' => function (BelongsToMany $q) use ($storeId): void {
                $q->where('services.store_id', $storeId);
            },
        ]);
    }

    /**
     * Prestadores activos (ficha de agente + papel de prestador de serviços).
     *
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeActiveServiceProviders(Builder $query, ?int $storeId = null): Builder
    {
        if ($storeId !== null) {
            $query->forStore($storeId);
        }

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereHas('user', fn (Builder $userQuery) => $userQuery->whereIn('role', User::serviceProviderRoles()));
    }

    /**
     * Membros de equipa activos (todos os papéis de staff, excepto cliente).
     *
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeActiveTeamMembers(Builder $query, ?int $storeId = null): Builder
    {
        if ($storeId !== null) {
            $query->forStore($storeId);
        }

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('role', '!=', User::ROLE_CLIENTE));
    }
}
