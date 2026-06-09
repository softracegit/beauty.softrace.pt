<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventServiceExtra extends Model
{
    protected $fillable = [
        'calendar_event_service_id',
        'extra_id',
        'duration',
        'price',
        'sort_order',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function calendarEventService(): BelongsTo
    {
        return $this->belongsTo(CalendarEventService::class);
    }

    public function extra(): BelongsTo
    {
        // withTrashed para que marcações antigas continuem a resolver nome/preço/duração
        // do extra mesmo depois de este ser eliminado (soft delete) do catálogo.
        return $this->belongsTo(Extra::class)->withTrashed();
    }
}
