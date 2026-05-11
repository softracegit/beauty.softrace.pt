<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Extra extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['extra_category_id', 'name', 'description', 'price', 'duration', 'sort_order'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Extra criado',
                'updated' => 'Extra atualizado',
                'deleted' => 'Extra eliminado',
                default => 'Extra alterado',
            });
    }

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

    /**
     * Extras vivem na categoria; a loja vem de {@see ExtraCategory::store_id}.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->whereHas('extraCategory', fn ($q) => $q->where('store_id', current_store_id()))
            ->firstOrFail();
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.').' €';
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration <= 0) {
            return '0 min';
        }
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'min';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $minutes.'min';
    }
}
