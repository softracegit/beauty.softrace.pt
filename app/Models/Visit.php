<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Visit extends Model
{
    public const STATUS_AGENDADA = 'agendada';
    public const STATUS_REALIZADA = 'realizada';
    public const STATUS_CANCELADA = 'cancelada';

    protected $fillable = [
        'opportunity_id',
        'property_id',
        'scheduled_at',
        'status',
        'client_feedback_strengths',
        'client_feedback_weaknesses',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_AGENDADA => 'Agendada',
            self::STATUS_REALIZADA => 'Realizada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function calendarEvent(): MorphOne
    {
        return $this->morphOne(CalendarEvent::class, 'eventable');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_AGENDADA => 'primary',
            self::STATUS_REALIZADA => 'success',
            self::STATUS_CANCELADA => 'secondary',
            default => 'secondary',
        };
    }

    protected static function booted(): void
    {
        static::deleting(function (Visit $visit) {
            $visit->calendarEvent?->delete();
        });
    }
}
