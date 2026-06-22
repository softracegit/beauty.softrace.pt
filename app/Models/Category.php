<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'color',
        'sort_order',
        'hidden_from_booking',
    ];

    protected $casts = [
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
     * Get all services for this category
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order');
    }

    /**
     * Serviços da categoria (ordenados). A coluna is_active foi removida de services.
     */
    public function activeServices(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order');
    }

    public function scopeVisibleInBooking(Builder $query): Builder
    {
        return $query->where('hidden_from_booking', false);
    }
}
