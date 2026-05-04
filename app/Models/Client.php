<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Support\PhoneDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use BelongsToStore, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'nif', 'birth_date', 'gender', 'nationality', 'marital_status', 'address', 'door', 'floor', 'side', 'postal_code', 'locality', 'type', 'preferred_schedule', 'preferences_notes'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Cliente criado',
                'updated' => 'Cliente atualizado',
                'deleted' => 'Cliente eliminado',
                default => 'Cliente alterado',
            });
    }

    protected $fillable = [
        'store_id',
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
        'type',
        'avatar',
        'preferred_schedule',
        'preferences_notes',
        'stripe_customer_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Store, $this>
     */
    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected $casts = [
        'birth_date' => 'date',
        'phone_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Telefone para exibição (indicativo e número separados por regras do país).
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        $raw = $this->attributes['phone'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return PhoneDisplay::formatInternational($raw) ?? $raw;
    }

    // Tipos de cliente
    public const TYPE_POTENCIAL_CLIENTE = 'potencial_cliente';
    // Outros tipos serão adicionados no futuro

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
        return '#CL'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public static function preferredSchedules(): array
    {
        return [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'flexivel' => 'Flexível',
        ];
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getDaysUntilBirthdayAttribute(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }
        $today = now()->startOfDay();
        $nextBday = $this->birth_date->copy()->year($today->year);
        if ($nextBday->lt($today)) {
            $nextBday->addYear();
        }

        return (int) $today->diffInDays($nextBday, false);
    }

    /**
     * Marcações (agendamentos de serviços) deste cliente.
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * Utilizador de marcação online (role cliente), se existir.
     */
    public function bookingUser(): HasOne
    {
        return $this->hasOne(User::class, 'client_id', 'id')
            ->where('role', User::ROLE_CLIENTE);
    }

    public function bookingSavedCards(): HasMany
    {
        return $this->hasMany(BookingSavedCard::class);
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

    /**
     * Verifica se já existe cliente com o mesmo número (E.164 quando analisável; senão comparação literal).
     */
    public static function existsWithSamePhoneAs(string $phone, ?int $storeId = null): bool
    {
        $phone = trim($phone);
        if ($phone === '') {
            return false;
        }
        if ($storeId === null) {
            $storeId = current_store_id();
        }
        $inputE164 = PhoneDisplay::toE164($phone);

        return static::query()
            ->forStore($storeId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone')
            ->contains(function (string $existing) use ($phone, $inputE164) {
                $existingE164 = PhoneDisplay::toE164($existing);
                if ($inputE164 !== null && $existingE164 !== null) {
                    return $inputE164 === $existingE164;
                }

                return trim($existing) === $phone;
            });
    }
}
