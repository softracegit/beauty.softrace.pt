<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Fee extends Model
{
    use BelongsToStore, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'price', 'sort_order'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Taxa criada',
                'updated' => 'Taxa atualizada',
                'deleted' => 'Taxa eliminada',
                default => 'Taxa alterada',
            });
    }

    protected $fillable = [
        'store_id',
        'name',
        'price',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_fee');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 2, ',', '.').' €';
    }
}
