<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\ActivityLogContext;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'updated' => 'Conta de utilizador atualizada',
                'created' => 'Conta de utilizador criada',
                default => 'Conta de utilizador alterada',
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            ActivityLogContext::attachSubjectLabel($activity, $name);
            $base = (string) ($activity->description ?? 'Conta de utilizador alterada');
            if (! str_contains($base, $name)) {
                $activity->description = $base.': '.$name;
            }
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organization_id',
        'client_id',
        'must_set_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_set_password' => 'boolean',
        ];
    }

    // Roles (Tipo de Membro - focado em serviços: nails, barbeiros, pets, etc.)
    public const ROLE_ADMIN = 'admin';

    /** @deprecated Migrado para {@see ROLE_ADMIN} */
    public const ROLE_GERENTE = 'gerente';

    public const ROLE_RECECAO = 'rececao';

    public const ROLE_PRESTADOR = 'prestador';

    /** @deprecated Migrado para {@see ROLE_PRESTADOR} */
    public const ROLE_TECNICO = 'tecnico';

    /** Conta de cliente para marcação online (magic link / password opcional). */
    public const ROLE_CLIENTE = 'cliente';

    /** Operador da plataforma (área Super Admin). Não consta em {@see roles()} — não atribuir como papel de equipa. */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public static function roles(): array
    {
        return self::staffAssignableRoles() + [
            self::ROLE_CLIENTE => 'Cliente (marcação online)',
        ];
    }

    /** Tipos de membro atribuíveis manualmente na ficha de equipa. */
    public static function staffAssignableRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_RECECAO => 'Receção',
            self::ROLE_PRESTADOR => 'Prestador(a) de Serviços',
        ];
    }

    public static function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return '—';
        }

        return self::roles()[$role]
            ?? match ($role) {
                self::ROLE_GERENTE => 'Administrador',
                self::ROLE_TECNICO => 'Prestador(a) de Serviços',
                default => ucfirst(str_replace('_', ' ', $role)),
            };
    }

    /** Tipos de membro em que o campo Especialização se aplica. */
    public static function rolesWithSpecialization(): array
    {
        return [
            self::ROLE_PRESTADOR,
        ];
    }

    /** Papéis de prestador de serviços (inclui legado técnico). */
    public static function serviceProviderRoles(): array
    {
        return [
            self::ROLE_PRESTADOR,
            self::ROLE_TECNICO,
        ];
    }

    /**
     * Marcação online: qualquer membro com ficha de agente pode ser técnica, excepto cliente e admin
     * (admin continua bloqueado no finalize do checkout por regra de negócio).
     */
    public function scopeEligibleForPublicBooking(Builder $query): Builder
    {
        return $query->whereNotIn($query->getModel()->getTable().'.role', [
            self::ROLE_CLIENTE,
            self::ROLE_ADMIN,
        ]);
    }

    /** Membros de equipa com ficha de agente activa (opcionalmente filtrados por loja). */
    public function scopeActiveStaff(Builder $query, ?int $storeId = null): Builder
    {
        return $query
            ->where('role', '!=', self::ROLE_CLIENTE)
            ->whereHas('agent', function (Builder $agentQuery) use ($storeId) {
                $agentQuery->where('status', Agent::STATUS_ACTIVE);
                if ($storeId !== null) {
                    $agentQuery->where('store_id', $storeId);
                }
            });
    }

    /** Prestadores de serviços activos (filtros de técnico em relatórios e catálogo). */
    public function scopeActiveServiceProviders(Builder $query, ?int $storeId = null): Builder
    {
        return $query
            ->whereIn('role', self::serviceProviderRoles())
            ->whereHas('agent', function (Builder $agentQuery) use ($storeId) {
                $agentQuery->where('status', Agent::STATUS_ACTIVE);
                if ($storeId !== null) {
                    $agentQuery->where('store_id', $storeId);
                }
            });
    }

    /**
     * Get the agent associated with this user (1-1 relationship)
     */
    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Lojas às quais o utilizador tem acesso explícito (técnicos/recepção; admin/gestor vê todas via organização).
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class)->withTimestamps();
    }

    /**
     * Ficha de CRM associada a contas de marcação online (role cliente).
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isBookingClient(): bool
    {
        return $this->role === self::ROLE_CLIENTE && $this->client_id !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Slug da loja pública de marcação (conta cliente ligada ao CRM por loja).
     */
    public function bookingPublicHomeStoreSlug(): string
    {
        if ($this->isBookingClient()) {
            $this->loadMissing('client.store');
            $slug = $this->client?->store?->slug;
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        return Store::defaultPublicBookingStoreSlug();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<UserNotificationPreference, $this>
     */
    public function notificationPreferences()
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Excepção temporária: só o administrador principal (id 1) pode eliminar serviços/categorias.
     */
    public function canDeleteCatalogServicesAndCategories(): bool
    {
        return (int) $this->id === 1 && $this->isAdmin();
    }

    /** Receção e admin podem eliminar tempo pessoal de qualquer prestador na agenda. */
    public function canDeleteAnyPersonalTimeOnAgenda(): bool
    {
        return $this->isAdmin() || $this->isRececao();
    }

    public function isRececao(): bool
    {
        return $this->hasRole(self::ROLE_RECECAO);
    }

    public function isPrestador(): bool
    {
        return in_array($this->role, self::serviceProviderRoles(), true);
    }

    /**
     * @deprecated Gerente foi removido; mantido para compatibilidade com dados antigos.
     */
    public function isGerente(): bool
    {
        return $this->hasRole(self::ROLE_GERENTE);
    }

    /**
     * @deprecated Alias legado de gerente.
     */
    public function isDiretor(): bool
    {
        return $this->isGerente();
    }

    public function canManageAgents(): bool
    {
        return $this->isAdmin();
    }

    public function canViewAllAgenda(): bool
    {
        return $this->isAdmin() || $this->isRececao();
    }

    public function canManageCashRegister(): bool
    {
        return $this->isAdmin() || $this->isRececao();
    }

    public function canSwitchStore(): bool
    {
        return ! $this->isPrestador() && ! $this->isRececao();
    }

    public function canProcessPayments(): bool
    {
        return ! $this->isPrestador();
    }

    public function canCreateMarcacao(): bool
    {
        return $this->isAdmin() || $this->isRececao() || $this->isPrestador();
    }

    public function canViewClientContactDetails(): bool
    {
        return ! $this->isPrestador();
    }

    public function canViewClientProfile(): bool
    {
        return ! $this->isPrestador();
    }

    public function canViewInvoices(): bool
    {
        return ! $this->isPrestador();
    }

    public function canReassignMarcacao(): bool
    {
        return ! $this->isPrestador();
    }

    /** Prestador não pode trocar o cliente numa marcação já existente. */
    public function canChangeMarcacaoClient(): bool
    {
        return ! $this->isPrestador();
    }

    /** Estados que o prestador pode definir numa marcação. */
    public function prestadorAllowedMarcacaoStatuses(): array
    {
        return ['chegou', 'iniciado'];
    }

    /** Estados em que o prestador pode editar o conteúdo da marcação (serviços, extras, etc.). */
    public function prestadorEditableMarcacaoStatuses(): array
    {
        return ['agendado', 'notificado', 'confirmado', 'chegou', 'iniciado'];
    }

    public function canAccessDashboard(): bool
    {
        return $this->isAdmin() || $this->isRececao() || $this->isPrestador();
    }

    public function canAccessClientes(): bool
    {
        return ! $this->isPrestador();
    }

    public function canAccessCatalog(): bool
    {
        return $this->isAdmin();
    }

    public function canAccessMarketing(): bool
    {
        return ! $this->isPrestador();
    }

    public function canAccessEquipa(): bool
    {
        return $this->canManageAgents();
    }

    public function canAccessRelatorios(): bool
    {
        return $this->isAdmin();
    }

    public function canAccessDefinicoes(): bool
    {
        return $this->isAdmin();
    }

    public function backofficeHomeRoute(): string
    {
        return 'dashboard';
    }

    /**
     * Rotas de página do backoffice acessíveis (recepção tem restrições; prestador só agenda).
     */
    public function canAccessRoute(string $routeName): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isPrestador()) {
            return str_starts_with($routeName, 'agenda.')
                || str_starts_with($routeName, 'notifications.')
                || $routeName === 'dashboard';
        }

        if ($this->isRececao()) {
            $deniedPrefixes = [
                'definicoes.',
                'relatorios.',
                'activity.',
                'equipa.',
                'services.',
                'categories.',
                'extras.',
                'fees.',
            ];

            foreach ($deniedPrefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return false;
                }
            }

            if ($routeName !== 'dashboard' && str_starts_with($routeName, 'dashboard.')) {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Lojas que o utilizador pode seleccionar no backoffice (mesma organização para admins/gestores; pivot + loja do agente para os restantes).
     *
     * @return Collection<int, Store>
     */
    public function accessibleStores(): Collection
    {
        $organizationId = $this->organization_id;
        if ($organizationId === null) {
            $this->loadMissing('agent.store');
            $organizationId = $this->agent?->store?->organization_id;
        }
        if ($organizationId === null) {
            return new Collection;
        }

        if ($this->canManageAgents()) {
            return Store::query()
                ->where('organization_id', $organizationId)
                ->orderBy('name')
                ->get();
        }

        $this->loadMissing('stores');
        $fromPivot = $this->stores;
        if ($fromPivot->isNotEmpty()) {
            return $fromPivot->sortBy('name')->values();
        }

        $this->loadMissing('agent.store');
        $home = $this->agent?->store;
        if ($home && (int) $home->organization_id === (int) $organizationId) {
            return new Collection([$home]);
        }

        return new Collection;
    }
}
