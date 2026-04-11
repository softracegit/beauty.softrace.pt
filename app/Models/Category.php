<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

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
}
