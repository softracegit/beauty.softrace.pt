<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    /** Nova marcação criada e atribuída ao consultor. */
    public const TYPE_ASSIGNED = 'assigned';

    /** Marcação transferida para outro consultor. */
    public const TYPE_REASSIGNED = 'reassigned';

    /** Data ou hora da marcação alterada (mesmo responsável). */
    public const TYPE_RESCHEDULED = 'rescheduled';

    /** Estado da marcação alterado (inclui fluxo rápido de estado). */
    public const TYPE_STATUS_CHANGED = 'status_changed';

    /**
     * Tipos emitidos por {@see \App\Notifications\AppointmentNotification} (coluna `category` na BD).
     *
     * @var list<string>
     */
    public const MARCACAO_NOTIFICATION_KEYS = [
        self::TYPE_ASSIGNED,
        self::TYPE_REASSIGNED,
        self::TYPE_RESCHEDULED,
        self::TYPE_STATUS_CHANGED,
    ];

    protected $fillable = [
        'user_id',
        'category',
        'bell_enabled',
        'email_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bell_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Canais a usar para AppointmentNotification (sininho = database, email = mail).
     *
     * @return list<string>
     */
    public static function channelsForMarcacaoNotification(User $user, string $type): array
    {
        if (! in_array($type, self::MARCACAO_NOTIFICATION_KEYS, true)) {
            return ['database', 'mail'];
        }

        $pref = self::query()
            ->where('user_id', $user->getKey())
            ->where('category', $type)
            ->first();

        // Sem registo: tudo ativo. Com registo: respeitar false explícito (0/false na BD).
        $bell = $pref === null ? true : (bool) $pref->bell_enabled;
        $email = $pref === null ? true : (bool) $pref->email_enabled;

        $channels = [];
        if ($bell) {
            $channels[] = 'database';
        }
        if ($email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Verificação por canal (usada por Notification::shouldSend no momento do envio real).
     */
    public static function wantsMarcacaoChannel(User $user, string $type, string $channel): bool
    {
        if (! in_array($type, self::MARCACAO_NOTIFICATION_KEYS, true)) {
            return true;
        }

        $pref = self::query()
            ->where('user_id', $user->getKey())
            ->where('category', $type)
            ->first();

        $bell = $pref === null ? true : (bool) $pref->bell_enabled;
        $email = $pref === null ? true : (bool) $pref->email_enabled;

        return match ($channel) {
            'database' => $bell,
            'mail' => $email,
            default => true,
        };
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function marcacaoTypesMeta(): array
    {
        return [
            self::TYPE_ASSIGNED => [
                'label' => 'Nova marcação',
                'description' => 'Quando uma marcação é criada e atribuída a si.',
            ],
            self::TYPE_REASSIGNED => [
                'label' => 'Transferência',
                'description' => 'Quando uma marcação é transferida de outro consultor para si.',
            ],
            self::TYPE_RESCHEDULED => [
                'label' => 'Reagendamento',
                'description' => 'Quando a data, hora ou serviços de uma marcação sua são alterados.',
            ],
            self::TYPE_STATUS_CHANGED => [
                'label' => 'Cancelamento / falta',
                'description' => 'Sininho só em cancelamento; email em cancelamento e falta.',
            ],
        ];
    }
}
