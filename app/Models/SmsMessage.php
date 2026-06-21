<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use BelongsToStore;

    public const TYPE_AUTH_OTP = 'auth_otp';

    public const TYPE_CONTACT_VERIFICATION = 'contact_verification';

    public const TYPE_BOOKING_REMINDER = 'booking_reminder';

    public const TYPE_MARKETING = 'marketing';

    protected $fillable = [
        'store_id',
        'type',
        'client_id',
        'client_name',
        'calendar_event_id',
        'to_phone',
        'from_phone',
        'body',
        'twilio_sid',
        'twilio_status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_AUTH_OTP => 'OTP acesso',
            self::TYPE_CONTACT_VERIFICATION => 'OTP verificação',
            self::TYPE_BOOKING_REMINDER => 'Lembrete marcação',
            self::TYPE_MARKETING => 'Marketing',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
