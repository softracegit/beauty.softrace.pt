<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalTimeType extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'icon', 'duration', 'sort_order', 'is_active'];

    protected $casts = [
        'duration' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration >= 60) {
            $h = floor($this->duration / 60);
            $m = $this->duration % 60;

            return $m > 0 ? "{$h}h {$m}min" : "{$h}h";
        }

        return $this->duration.' min';
    }
}
