<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use BelongsToStore, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category_id', 'name', 'description', 'duration', 'price', 'online_price', 'sort_order', 'hidden_from_booking'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Serviço criado',
                'updated' => 'Serviço atualizado',
                'deleted' => 'Serviço eliminado',
                default => 'Serviço alterado',
            });
    }

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'duration',
        'price',
        'online_price',
        'sort_order',
        'hidden_from_booking',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'online_price' => 'decimal:2',
        'sort_order' => 'integer',
        'hidden_from_booking' => 'boolean',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the category that owns the service
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isBookableOnline(): bool
    {
        if ($this->hidden_from_booking) {
            return false;
        }

        $this->loadMissing('category');

        if (! $this->category) {
            return true;
        }

        return ! $this->category->hidden_from_booking;
    }

    public function scopeVisibleInBooking(Builder $query): Builder
    {
        return $query->where('hidden_from_booking', false);
    }

    /**
     * Get the agents associated with this service
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }

    /**
     * Get the extras that can be added to this service
     */
    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(Extra::class, 'service_extra');
    }

    public function fees(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class, 'service_fee');
    }

    /**
     * Variantes do serviço (ex.: com/sem lavagem). Baseline espelha o serviço pai.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ServiceOption::class)->orderBy('sort_order');
    }

    /**
     * Get calendar events that include this service
     */
    public function calendarEvents(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\CalendarEvent::class, 'calendar_event_services')
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
            ->withTimestamps();
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.').' €';
    }

    /**
     * Get formatted online price (preço online, habitualmente mais baixo)
     */
    public function getFormattedOnlinePriceAttribute(): ?string
    {
        return $this->online_price ? number_format($this->online_price, 2, ',', '.').' €' : null;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'min';
        } elseif ($hours > 0) {
            return $hours.'h';
        }

        return $minutes.'min';
    }
}
