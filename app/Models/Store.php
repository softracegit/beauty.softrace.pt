<?php

namespace App\Models;

use App\Support\PhoneDisplay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Store extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'timezone',
        'weekly_schedule',
        'phone',
        'email',
        'address_line',
        'city',
        'postal_code',
        'logo',
        'logo_email',
        'logo_favicon',
        'maps_url',
        'website_url',
        'instagram_url',
    ];

    protected $casts = [
        'weekly_schedule' => 'array',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Staff com acesso explícito a esta loja (além da regra de admin/gestor por organização).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<Fee, $this>
     */
    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    /**
     * @return HasMany<CrmSetting, $this>
     */
    public function crmSettings(): HasMany
    {
        return $this->hasMany(CrmSetting::class);
    }

    /**
     * @return HasMany<ExtraCategory, $this>
     */
    public function extraCategories(): HasMany
    {
        return $this->hasMany(ExtraCategory::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<PersonalTimeType, $this>
     */
    public function personalTimeTypes(): HasMany
    {
        return $this->hasMany(PersonalTimeType::class);
    }

    /**
     * @return HasMany<ClientTag, $this>
     */
    public function clientTags(): HasMany
    {
        return $this->hasMany(ClientTag::class);
    }

    /**
     * @return HasMany<BookingSlotHold, $this>
     */
    public function bookingSlotHolds(): HasMany
    {
        return $this->hasMany(BookingSlotHold::class);
    }

    /**
     * @return HasMany<BookingAuthCode, $this>
     */
    public function bookingAuthCodes(): HasMany
    {
        return $this->hasMany(BookingAuthCode::class);
    }

    /**
     * Loja usada quando não há {@see \App\Support\CurrentStore} (ex.: rotas de marcação pública sem prefixo de loja).
     */
    public static function defaultPublicBookingStoreId(): int
    {
        $id = static::query()->where('slug', 'default')->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $fallback = static::query()->orderBy('id')->value('id');
        if ($fallback === null) {
            throw new RuntimeException('No store configured.');
        }

        return (int) $fallback;
    }

    /**
     * Horário habitual da loja (Seg–Sáb 09:00–20:00, domingo encerrado).
     *
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public static function defaultWeeklySchedule(): array
    {
        $out = [];
        foreach (Agent::WEEKDAY_KEYS as $dayKey) {
            $out[$dayKey] = [
                'enabled' => $dayKey !== 'sun',
                'start' => '09:00',
                'end' => '20:00',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public function normalizedWeeklySchedule(): array
    {
        $raw = $this->weekly_schedule;
        if (! is_array($raw) || $raw === []) {
            $org = $this->relationLoaded('organization') ? $this->organization : $this->organization()->first();
            if ($org instanceof Organization) {
                return $org->normalizedWeeklySchedule();
            }

            return self::defaultWeeklySchedule();
        }

        $defaults = self::defaultWeeklySchedule();
        $out = [];
        foreach (Agent::WEEKDAY_KEYS as $dayKey) {
            $v = $raw[$dayKey] ?? null;
            if (! is_array($v)) {
                $out[$dayKey] = $defaults[$dayKey];

                continue;
            }
            $enabled = array_key_exists('enabled', $v)
                ? filter_var($v['enabled'], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($defaults[$dayKey]['enabled'] ?? true);
            $out[$dayKey] = [
                'enabled' => $enabled,
                'start' => is_string($v['start'] ?? null) ? $v['start'] : $defaults[$dayKey]['start'],
                'end' => is_string($v['end'] ?? null) ? $v['end'] : $defaults[$dayKey]['end'],
            ];
        }

        return $out;
    }

    public function usesOrganizationSchedule(): bool
    {
        return ! is_array($this->weekly_schedule) || $this->weekly_schedule === [];
    }

    public function organizationOrNull(): ?Organization
    {
        if ($this->relationLoaded('organization')) {
            return $this->organization;
        }

        return $this->organization()->first();
    }

    public function resolvedPhone(): string
    {
        $local = trim((string) ($this->phone ?? ''));
        if ($local !== '') {
            return $local;
        }

        return trim((string) ($this->organizationOrNull()?->phone ?? ''));
    }

    public function resolvedEmail(): string
    {
        $local = trim((string) ($this->email ?? ''));
        if ($local !== '') {
            return $local;
        }

        return trim((string) ($this->organizationOrNull()?->email ?? ''));
    }

    public function resolvedWebsiteUrl(): string
    {
        $local = trim((string) ($this->website_url ?? ''));
        if ($local !== '') {
            return $local;
        }

        return trim((string) ($this->organizationOrNull()?->website_url ?? ''));
    }

    public function resolvedInstagramUrl(): string
    {
        $local = trim((string) ($this->instagram_url ?? ''));
        if ($local !== '') {
            return $local;
        }

        return trim((string) ($this->organizationOrNull()?->instagram_url ?? ''));
    }

    public function usesOrganizationPhone(): bool
    {
        return trim((string) ($this->phone ?? '')) === '';
    }

    public function usesOrganizationEmail(): bool
    {
        return trim((string) ($this->email ?? '')) === '';
    }

    public function usesOrganizationWebsite(): bool
    {
        return trim((string) ($this->website_url ?? '')) === '';
    }

    public function usesOrganizationInstagram(): bool
    {
        return trim((string) ($this->instagram_url ?? '')) === '';
    }

    public function usesOrganizationLogo(): bool
    {
        return trim((string) ($this->logo ?? '')) === '';
    }

    public function usesOrganizationLogoEmail(): bool
    {
        return trim((string) ($this->logo_email ?? '')) === '';
    }

    public function usesOrganizationLogoFavicon(): bool
    {
        return trim((string) ($this->logo_favicon ?? '')) === '';
    }

    public function usesOrganizationTimezone(): bool
    {
        return trim((string) ($this->timezone ?? '')) === '';
    }

    /** Etiqueta curta para avisos na agenda (ex.: "09:00–20:00" ou "varia por dia"). */
    public function hoursDisplayLabel(): string
    {
        $ranges = [];
        foreach ($this->normalizedWeeklySchedule() as $day) {
            if (! ($day['enabled'] ?? false)) {
                continue;
            }
            $ranges[] = ($day['start'] ?? '09:00').'–'.($day['end'] ?? '20:00');
        }
        $unique = array_values(array_unique($ranges));
        if ($unique === []) {
            return 'encerrado';
        }
        if (count($unique) === 1) {
            return $unique[0];
        }

        return 'varia por dia';
    }

    /** Margem extra (minutos) após o fecho habitual na grelha da agenda (marcações que ultrapassam o horário). */
    public const AGENDA_SLOT_END_MARGIN_MINUTES = 60;

    /**
     * Intervalo [min, max] em HH:MM para a grelha da agenda (todos os dias ativos).
     * O máximo inclui {@see AGENDA_SLOT_END_MARGIN_MINUTES} após o fecho da loja.
     *
     * @return array{0: string, 1: string}
     */
    public function agendaSlotRange(): array
    {
        $minM = 24 * 60;
        $maxM = 0;
        $found = false;
        foreach ($this->normalizedWeeklySchedule() as $day) {
            if (! ($day['enabled'] ?? false)) {
                continue;
            }
            $found = true;
            $s = Agent::timeStringToMinutes($day['start'] ?? '09:00');
            $e = Agent::timeStringToMinutes($day['end'] ?? '20:00');
            $minM = min($minM, $s);
            $maxM = max($maxM, $e);
        }
        if (! $found) {
            $minM = Agent::timeStringToMinutes('09:00');
            $maxM = Agent::timeStringToMinutes('20:00');
        }

        $maxM = min(24 * 60, $maxM + self::AGENDA_SLOT_END_MARGIN_MINUTES);

        return [
            sprintf('%02d:%02d', intdiv($minM, 60), $minM % 60),
            sprintf('%02d:%02d', intdiv($maxM, 60), $maxM % 60),
        ];
    }

    /** Chave do dia (sun…sat) alinhada com a agenda JS. */
    public static function weekKeyFromDate(CarbonInterface $date): string
    {
        $keys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

        return $keys[(int) $date->dayOfWeek] ?? 'mon';
    }

    /**
     * Horário de abertura/fecho da loja num dia civil (fuso da loja).
     *
     * @return array{start: string, end: string}|null null se a loja estiver fechada
     */
    public function storeHoursWindowForDate(CarbonInterface $date): ?array
    {
        $dayKey = self::weekKeyFromDate($date);
        $day = $this->normalizedWeeklySchedule()[$dayKey] ?? null;
        if (! is_array($day) || ! ($day['enabled'] ?? false)) {
            return null;
        }

        $start = is_string($day['start'] ?? null) ? $day['start'] : '09:00';
        $end = is_string($day['end'] ?? null) ? $day['end'] : '20:00';

        return ['start' => $start, 'end' => $end];
    }

    /** Hora (HH:MM no fuso da loja) dentro do horário da loja nesse dia? */
    public function isInstantWithinStoreHours(CarbonInterface $instant): bool
    {
        $window = $this->storeHoursWindowForDate($instant);
        if ($window === null) {
            return false;
        }

        $mins = Agent::timeStringToMinutes($instant->format('H:i'));
        $startM = Agent::timeStringToMinutes($window['start']);
        $endM = Agent::timeStringToMinutes($window['end']);

        return $mins >= $startM && $mins <= $endM;
    }

    public static function defaultPublicBookingStoreSlug(): string
    {
        $slug = static::query()->where('slug', 'default')->value('slug');
        if ($slug !== null) {
            return (string) $slug;
        }

        $fallback = static::query()->orderBy('id')->value('slug');
        if ($fallback === null) {
            throw new RuntimeException('No store configured.');
        }

        return (string) $fallback;
    }

    /** Morada numa linha para a marcação pública e mapas. */
    public function formattedAddress(): string
    {
        $line2 = trim(implode(' ', array_filter([
            trim((string) ($this->postal_code ?? '')),
            trim((string) ($this->city ?? '')),
        ])));

        return implode(', ', array_filter([
            trim((string) ($this->address_line ?? '')),
            $line2,
        ]));
    }

    public function mapsUrl(): string
    {
        $custom = trim((string) ($this->maps_url ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $address = $this->formattedAddress();
        if ($address === '') {
            return '#';
        }

        return 'https://maps.google.com/?q='.rawurlencode($address);
    }

    /** Fuso fixo: Portugal / Lisboa (config booking.business_timezone). */
    public function bookingTimezone(): string
    {
        return (string) config('booking.business_timezone', 'Europe/Lisbon');
    }

    /** Pasta no disco `public`: `storage/app/public/store-logos/{id}/`. */
    public function logoStorageDirectory(): string
    {
        return 'store-logos/'.(int) $this->getKey();
    }

    /** URL absoluta do logotipo genérico (upload ou ícone por defeito). */
    public function logoUrl(): string
    {
        return $this->logoGenericUrl();
    }

    public function logoGenericUrl(): string
    {
        $path = trim((string) ($this->logo ?? ''));
        if ($path !== '') {
            return asset('storage/'.ltrim($path, '/'));
        }

        $org = $this->organizationOrNull();
        if ($org instanceof Organization && $org->hasOwnLogo()) {
            return $org->logoGenericUrl();
        }

        return $this->defaultLogoFallbackUrl();
    }

    public function logoFaviconUrl(): string
    {
        $path = trim((string) ($this->logo_favicon ?? ''));
        if ($path !== '') {
            return asset('storage/'.ltrim($path, '/'));
        }

        $generic = trim((string) ($this->logo ?? ''));
        if ($generic !== '') {
            return asset('storage/'.ltrim($generic, '/'));
        }

        $org = $this->organizationOrNull();
        if ($org instanceof Organization) {
            return $org->logoFaviconUrl();
        }

        return $this->defaultLogoFallbackUrl();
    }

    public function logoEmailUrl(): string
    {
        $path = trim((string) ($this->logo_email ?? ''));
        if ($path !== '') {
            return asset('storage/'.ltrim($path, '/'));
        }

        $org = $this->organizationOrNull();
        if ($org instanceof Organization && $org->hasOwnLogoEmail()) {
            return $org->logoEmailUrl();
        }

        return asset('template/img/logo-color-black.png');
    }

    private function defaultLogoFallbackUrl(): string
    {
        $fallback = (string) config('booking.public_store.photo_fallback', 'booking-assets/img/icone.png');

        return asset(ltrim($fallback, '/'));
    }

    /**
     * Dados da loja para o resumo e menu da marcação pública.
     *
     * @return array{
     *     name: string,
     *     address: string,
     *     phone: string,
     *     phone_tel_href: string,
     *     email: string,
     *     photo: string,
     *     favicon: string,
     *     maps_url: string,
     *     website_url: string,
     *     instagram_url: string
     * }
     */
    public function publicBookingProfile(): array
    {
        $website = $this->resolvedWebsiteUrl();
        $instagram = $this->resolvedInstagramUrl();
        $phone = $this->resolvedPhone();

        return [
            'name' => (string) $this->name,
            'address' => $this->formattedAddress(),
            'phone' => $phone,
            'phone_tel_href' => PhoneDisplay::telHref($phone !== '' ? $phone : null),
            'email' => $this->resolvedEmail(),
            'photo' => $this->logoGenericUrl(),
            'favicon' => $this->logoFaviconUrl(),
            'maps_url' => $this->mapsUrl(),
            'website_url' => $website !== '' ? $website : '#',
            'instagram_url' => $instagram !== '' ? $instagram : '#',
        ];
    }
}
