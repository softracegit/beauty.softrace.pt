<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'store_id',
        'name',
        'description',
        'color',
        'sort_order',
        'hidden_from_booking',
        'hidden_from_agenda',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'hidden_from_booking' => 'boolean',
        'hidden_from_agenda' => 'boolean',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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

    public function scopeVisibleInAgenda(Builder $query): Builder
    {
        return $query->where('hidden_from_agenda', false);
    }

    /**
     * @return HasMany<CategoryClinicalField, $this>
     */
    public function clinicalFields(): HasMany
    {
        return $this->hasMany(CategoryClinicalField::class)->orderBy('sort_order')->orderBy('id');
    }
}
