<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Online booking checkout: deposit (Stripe) + link to calendar event after payment.
 */
class Booking extends Model
{
    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'public_id',
        'calendar_event_id',
        'client_id',
        'stripe_payment_intent_id',
        'total_price',
        'paid_amount',
        'remaining_amount',
        'deposit_percent_used',
        'payment_status',
        'request_payload',
        'authenticated_booking_user_id',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function authenticatedBookingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authenticated_booking_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
