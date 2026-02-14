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

    // Roles
    public const ROLE_CONSULTOR = 'consultor';
    public const ROLE_DIRETOR = 'diretor';
    public const ROLE_ADMIN = 'admin';

    public static function roles(): array
    {
        return [
            self::ROLE_CONSULTOR => 'Consultor',
            self::ROLE_DIRETOR => 'Diretor',
            self::ROLE_ADMIN => 'Administrador',
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
     * Check if user is diretor
     */
    public function isDiretor(): bool
    {
        return $this->hasRole(self::ROLE_DIRETOR);
    }

    /**
     * Check if user can manage agents
     */
    public function canManageAgents(): bool
    {
        return $this->isAdmin() || $this->isDiretor();
    }
}
