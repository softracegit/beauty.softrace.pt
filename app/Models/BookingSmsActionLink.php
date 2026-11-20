<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingSmsActionLink extends Model
{
    protected $fillable = [
        'token',
        'calendar_event_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    /**
     * Link de confirmação (SMS / email lembrete), válido até start_at da marcação.
     */
    public static function findOrCreateForEvent(CalendarEvent $event): self
    {
        $expiresAt = $event->start_at?->copy() ?? now()->addDay();

        $link = self::query()
            ->where('calendar_event_id', $event->id)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $link) {
            return self::create([
                'token' => self::generateUniqueToken(),
                'calendar_event_id' => $event->id,
                'expires_at' => $expiresAt,
            ]);
        }

        $link->expires_at = $expiresAt;
        $link->save();

        return $link;
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(10));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }
}
