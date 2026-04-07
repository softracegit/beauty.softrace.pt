<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    // Roles (Tipo de Membro - focado em serviços: nails, barbeiros, pets, etc.)
    public const ROLE_ADMIN = 'admin';

    public const ROLE_GERENTE = 'gerente';

    public const ROLE_RECECAO = 'rececao';

    public const ROLE_PRESTADOR = 'prestador';

    public const ROLE_TECNICO = 'tecnico';

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_GERENTE => 'Gerente',
            self::ROLE_RECECAO => 'Receção',
            self::ROLE_PRESTADOR => 'Prestador(a) de Serviços',
            self::ROLE_TECNICO => 'Técnico(a)',
        ];
    }

    /** Tipos de membro em que o campo Especialização se aplica. */
    public static function rolesWithSpecialization(): array
    {
        return [
            self::ROLE_PRESTADOR,
            self::ROLE_TECNICO,
        ];
    }

    /**
     * Get the agent associated with this user (1-1 relationship)
     */
    public function agent()
    {
        return $this->hasOne(Agent::class);
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
     * Check if user is gerente (manager)
     */
    public function isGerente(): bool
    {
        return $this->hasRole(self::ROLE_GERENTE);
    }

    /**
     * Check if user is diretor (legacy alias: gerente)
     */
    public function isDiretor(): bool
    {
        return $this->isGerente();
    }

    /**
     * Check if user can manage agents/members
     */
    public function canManageAgents(): bool
    {
        return $this->isAdmin() || $this->isGerente();
    }
}
