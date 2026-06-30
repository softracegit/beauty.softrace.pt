<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use App\Support\ActivityLogContext;
use App\Support\DateTimeDisplay;
use App\Support\PhoneDisplay;
use Spatie\Activitylog\Contracts\Activity;
use DateTimeInterface;
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
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Cliente criado',
                'updated' => 'Cliente atualizado',
                'deleted' => 'Cliente eliminado',
                default => 'Cliente alterado',
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        ActivityLogContext::attachClient($activity, $this);

        $name = ActivityLogContext::clientName($this);
        $base = (string) ($activity->description ?? 'Cliente alterado');
        if (! str_contains($base, $name)) {
            $activity->description = $base.': '.$name;
        }
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
        'notify_email_booking_updates',
        'notify_email_booking_reminders',
        'notify_sms_booking_reminders',
        'terms_accepted_at',
        'privacy_policy_version',
        'wallet_balance_cents',
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
        'notify_email_booking_updates' => 'boolean',
        'notify_email_booking_reminders' => 'boolean',
        'notify_sms_booking_reminders' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'wallet_balance_cents' => 'integer',
    ];

    /**
     * @return HasMany<ClientWalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(ClientWalletTransaction::class)->orderByDesc('created_at');
    }

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

    public function isBirthdayOn(?DateTimeInterface $date, ?int $storeId = null): bool
    {
        if (! $this->birth_date || ! $date) {
            return false;
        }

        $appointmentDay = DateTimeDisplay::inBusiness($date, $storeId ?? $this->store_id);
        if (! $appointmentDay) {
            return false;
        }

        return (int) $this->birth_date->month === (int) $appointmentDay->month
            && (int) $this->birth_date->day === (int) $appointmentDay->day;
    }

    public function isBirthdayInMonth(?DateTimeInterface $date, ?int $storeId = null): bool
    {
        if (! $this->birth_date || ! $date) {
            return false;
        }

        $appointmentDay = DateTimeDisplay::inBusiness($date, $storeId ?? $this->store_id);
        if (! $appointmentDay) {
            return false;
        }

        return (int) $this->birth_date->month === (int) $appointmentDay->month;
    }

    public function ageTurningOn(?DateTimeInterface $date, ?int $storeId = null): ?int
    {
        if (! $this->birth_date || ! $date || ! $this->isBirthdayInMonth($date, $storeId)) {
            return null;
        }

        $appointmentDay = DateTimeDisplay::inBusiness($date, $storeId ?? $this->store_id);
        if (! $appointmentDay) {
            return null;
        }

        return (int) $appointmentDay->year - (int) $this->birth_date->year;
    }

    /**
     * Destaque de aniversário face a uma data de referência (hoje na ficha, data da marcação na agenda).
     *
     * @return array{scope: string, tense: string, age: int, day_label: string, message: string, message_html: string}|null
     */
    public function birthdayHighlight(?DateTimeInterface $referenceDate = null, ?int $storeId = null, bool $sameMonthOnly = false, ?string $subjectName = null): ?array
    {
        if (! $this->birth_date) {
            return null;
        }

        $ref = DateTimeDisplay::inBusiness($referenceDate ?? now(), $storeId ?? $this->store_id);
        if (! $ref) {
            return null;
        }

        $birthMonth = (int) $this->birth_date->month;
        $birthDay = (int) $this->birth_date->day;

        if ($sameMonthOnly && $birthMonth !== (int) $ref->month) {
            return null;
        }

        $age = (int) $ref->year - (int) $this->birth_date->year;
        if ($age < 1) {
            return null;
        }

        $dayLabel = mb_strtolower($this->birth_date->copy()->locale('pt')->translatedFormat('j \d\e F'));
        $birthdayThisYear = $this->birth_date->copy()->year($ref->year)->startOfDay();
        $refDay = $ref->copy()->startOfDay();

        if ($refDay->equalTo($birthdayThisYear)) {
            $scope = 'day';
            $tense = 'present';
        } elseif ($sameMonthOnly || $birthMonth === (int) $ref->month) {
            $scope = 'month';
            $tense = (int) $ref->day > $birthDay ? 'past' : 'present';
        } elseif ($refDay->greaterThan($birthdayThisYear)) {
            $scope = 'year';
            $tense = 'past';
        } else {
            $scope = 'year';
            $tense = 'present';
        }

        $verb = $tense === 'past' ? 'fez' : 'faz';
        $name = trim((string) ($subjectName ?? ''));
        $namePrefix = $name !== '' ? $name.' ' : '';
        $ageLabel = "{$age} anos";

        if ($scope === 'day') {
            $message = "{$namePrefix}faz {$ageLabel} neste dia.";
            $messageHtml = ($name !== '' ? e($name).' ' : '')
                .'faz <strong>'.e($ageLabel).'</strong> neste dia.';
        } else {
            $message = "{$namePrefix}{$verb} {$ageLabel} a {$dayLabel}.";
            if ($name === '') {
                $message = ucfirst($verb)." {$ageLabel} a {$dayLabel}.";
            }
            $messageHtml = ($name !== '' ? e($name).' ' : '')
                .e($verb).' <strong>'.e($ageLabel).'</strong> a <strong>'.e($dayLabel).'</strong>.';
        }

        return [
            'scope' => $scope,
            'tense' => $tense,
            'age' => $age,
            'day_label' => $dayLabel,
            'message' => $message,
            'message_html' => $messageHtml,
        ];
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
