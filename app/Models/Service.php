<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'duration',
        'price',
        'promo_price',
        'sort_order',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the category that owns the service
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    /**
     * Get calendar events that include this service
     */
    public function calendarEvents(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\CalendarEvent::class, 'calendar_event_services')
            ->withPivot('duration', 'price', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.') . ' €';
    }

    /**
     * Get formatted promo price
     */
    public function getFormattedPromoPriceAttribute(): ?string
    {
        return $this->promo_price ? number_format($this->promo_price, 2, ',', '.') . ' €' : null;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'min';
        } elseif ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'min';
    }
}
