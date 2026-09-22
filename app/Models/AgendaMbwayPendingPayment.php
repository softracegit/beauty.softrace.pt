<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaMbwayPendingPayment extends Model
{
    public const FLOW_CHECKOUT = 'checkout';

    public const FLOW_DEPOSIT = 'deposit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'stripe_payment_intent_id',
        'flow',
        'store_id',
        'calendar_event_id',
        'staff_user_id',
        'status',
        'payload',
        'sale_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
