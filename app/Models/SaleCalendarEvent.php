<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleCalendarEvent extends Model
{
    protected $fillable = [
        'sale_id',
        'calendar_event_id',
        'amount_settled_cents',
        'is_primary',
    ];

    protected $casts = [
        'amount_settled_cents' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
