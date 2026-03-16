<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalTimeType extends Model
{
    protected $fillable = ['name', 'icon', 'duration', 'sort_order', 'is_active'];

    protected $casts = [
        'duration' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

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
        return $this->duration . ' min';
    }
}
