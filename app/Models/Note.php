<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    use HasFactory;

    // Tipos de notas
    public const TYPE_GERAL = 'geral';
    public const TYPE_EMAIL = 'email';
    public const TYPE_CHAMADA = 'chamada';
    public const TYPE_REUNIAO = 'reuniao';

    protected $fillable = [
        'notable_type',
        'notable_id',
        'user_id',
        'type',
        'note',
        'reminder_at',
        'reminder_advance_minutes',
        'reminder_sent',
    ];

    protected $casts = [
        'reminder_at' => 'datetime',
        'reminder_advance_minutes' => 'integer',
        'reminder_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all note types
     */
    public static function types(): array
    {
        return [
            self::TYPE_GERAL => 'Geral',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_CHAMADA => 'Chamada',
            self::TYPE_REUNIAO => 'Reunião',
        ];
    }

    /**
     * Get icon for note type
     */
    public static function getIconForType(string $type): string
    {
        return match($type) {
            self::TYPE_EMAIL => 'ri-mail-line',
            self::TYPE_CHAMADA => 'ri-phone-line',
            self::TYPE_REUNIAO => 'ri-calendar-event-line',
            default => 'ri-file-text-line',
        };
    }

    /**
     * Get color class for note type
     */
    public static function getColorForType(string $type): string
    {
        return match($type) {
            self::TYPE_EMAIL => 'text-primary',
            self::TYPE_CHAMADA => 'text-success',
            self::TYPE_REUNIAO => 'text-info',
            default => 'text-secondary',
        };
    }

    /**
     * Get the parent model (polymorphic)
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the note
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Check if reminder is due
     */
    public function isReminderDue(): bool
    {
        if (!$this->reminder_at || $this->reminder_sent) {
            return false;
        }

        $reminderTime = $this->reminder_at->subMinutes($this->reminder_advance_minutes ?? 15);
        return now()->greaterThanOrEqualTo($reminderTime);
    }
}
