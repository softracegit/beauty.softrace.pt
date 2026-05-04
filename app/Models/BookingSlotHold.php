<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlotHold extends Model
{
    protected $fillable = [
        'store_id',
        'public_id',
        'session_token',
        'booking_user_id',
        'selected_user_id',
        'slot_date',
        'slot_start_at',
        'slot_end_at',
        'duration_minutes',
        'agent_id_raw',
        'services_signature',
        'expires_at',
        'released_at',
        'release_reason',
        'meta',
    ];

    protected $casts = [
        'slot_date' => 'date:Y-m-d',
        'slot_start_at' => 'datetime',
        'slot_end_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('released_at')
            ->where('expires_at', '>', now());
    }

    public function bookingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booking_user_id');
    }

    public function selectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_user_id');
    }
}
