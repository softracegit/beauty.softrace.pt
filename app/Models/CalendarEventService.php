<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CalendarEventService extends Pivot
{
    protected $table = 'calendar_event_services';

    public $incrementing = true;

    protected $fillable = [
        'calendar_event_id',
        'service_id',
        'duration',
        'price',
        'original_price',
        'sort_order',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function extras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarEventServiceExtra::class, 'calendar_event_service_id');
    }
}
