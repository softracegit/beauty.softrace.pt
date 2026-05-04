<?php

namespace App\Models;

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
        'phone',
        'email',
        'address_line',
        'city',
        'postal_code',
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
}
