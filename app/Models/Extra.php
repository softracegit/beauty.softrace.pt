<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Extra extends Model
{
    protected $fillable = [
        'extra_category_id',
        'name',
        'description',
        'price',
        'duration',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'sort_order' => 'integer',
    ];

    public function extraCategory(): BelongsTo
    {
        return $this->belongsTo(ExtraCategory::class, 'extra_category_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_extra');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.') . ' €';
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration <= 0) {
            return '0 min';
        }
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'min';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'min';
    }
}
