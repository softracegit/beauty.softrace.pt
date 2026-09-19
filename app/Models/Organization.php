<?php

namespace App\Models;

use App\Models\Agent;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'nif',
        'phone',
        'email',
        'status',
        'timezone',
        'weekly_schedule',
        'address_line',
        'city',
        'postal_code',
        'maps_url',
        'website_url',
        'instagram_url',
        'logo',
        'logo_email',
        'logo_favicon',
    ];

    protected $casts = [
        'weekly_schedule' => 'array',
    ];

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
     * @return HasMany<ExtraCategory, $this>
     */
    public function extraCategories(): HasMany
    {
        return $this->hasMany(ExtraCategory::class);
    }

    public function logoStorageDirectory(): string
    {
        return 'organization-logos/'.(int) $this->getKey();
    }

    /**
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public function normalizedWeeklySchedule(): array
    {
        $raw = $this->weekly_schedule;
        if (! is_array($raw) || $raw === []) {
            return Store::defaultWeeklySchedule();
        }

        $defaults = Store::defaultWeeklySchedule();
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

    public function logoGenericUrl(): string
    {
        $path = trim((string) ($this->logo ?? ''));
        if ($path !== '') {
            return asset('storage/'.ltrim($path, '/'));
        }

        $fallback = (string) config('booking.public_store.photo_fallback', 'booking-assets/img/icone.png');

        return asset(ltrim($fallback, '/'));
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

        return $this->logoGenericUrl();
    }

    public function logoEmailUrl(): string
    {
        $path = trim((string) ($this->logo_email ?? ''));
        if ($path !== '') {
            return asset('storage/'.ltrim($path, '/'));
        }

        return asset('template/img/logo-color-black.png');
    }

    public function hasOwnLogo(): bool
    {
        return trim((string) ($this->logo ?? '')) !== '';
    }

    public function hasOwnLogoEmail(): bool
    {
        return trim((string) ($this->logo_email ?? '')) !== '';
    }

    public function hasOwnLogoFavicon(): bool
    {
        return trim((string) ($this->logo_favicon ?? '')) !== '';
    }
}
