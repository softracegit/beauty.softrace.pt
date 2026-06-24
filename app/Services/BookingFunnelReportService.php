<?php

namespace App\Services;

use App\Models\BookingAuthCode;
use App\Models\BookingSlotHold;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\PhoneDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BookingFunnelReportService
{
    public const TAB_SMS_PENDING = 'sms_pending';

    public const TAB_OTP_FAILED = 'otp_failed';

    public const TAB_ACCOUNTS = 'accounts';

    public const TAB_HOLDS = 'holds';

    private const HOLD_REASON_LABELS = [
        'manual' => 'Libertado manualmente',
        'expired_restart' => 'Tempo esgotado',
        'cart_empty' => 'Carrinho vazio',
        'technician_changed' => 'Técnica alterada',
        'time_cleared' => 'Hora limpa',
        'selection_cleared' => 'Seleção limpa',
        'no_slots_for_day' => 'Sem horários no dia',
        'slot_invalidated' => 'Horário inválido',
        'acquire_failed' => 'Falha ao reservar',
        'replaced' => 'Substituído',
        'conflict' => 'Conflito de horário',
    ];

    /**
     * @return list<string>
     */
    public function tabKeys(): array
    {
        return [
            self::TAB_SMS_PENDING,
            self::TAB_OTP_FAILED,
            self::TAB_ACCOUNTS,
            self::TAB_HOLDS,
        ];
    }

    public function resolveTab(string $raw): string
    {
        return in_array($raw, $this->tabKeys(), true) ? $raw : self::TAB_SMS_PENDING;
    }

    public function holdIsTimeExpired(BookingSlotHold $hold): bool
    {
        if ($hold->release_reason === 'expired_restart') {
            return true;
        }

        return $hold->released_at === null
            && $hold->expires_at !== null
            && $hold->expires_at->isPast();
    }

    public function holdReasonLabel(BookingSlotHold $hold): string
    {
        if ($this->holdIsTimeExpired($hold)) {
            return 'Tempo esgotado';
        }

        $reason = (string) ($hold->release_reason ?? '');

        return self::HOLD_REASON_LABELS[$reason] ?? ($reason !== '' ? $reason : '—');
    }

    /**
     * @return array{sms_pending: int, otp_failed: int, accounts_without_booking: int, expired_holds: int}
     */
    public function summaryCounts(int $storeId): array
    {
        return [
            'sms_pending' => $this->otpSmsWithoutResponseQuery($storeId)->count(),
            'otp_failed' => $this->otpFailedQuery($storeId)->count(),
            'accounts_without_booking' => $this->bookingUsersWithoutMarcacaoQuery($storeId)->count(),
            'expired_holds' => $this->abandonedHoldsQuery($storeId)->count(),
        ];
    }

    /**
     * OTP por SMS pedido, enviado, mas código nunca consumido.
     *
     * @return Builder<BookingAuthCode>
     */
    public function otpSmsWithoutResponseQuery(int $storeId): Builder
    {
        return BookingAuthCode::query()
            ->where('store_id', $storeId)
            ->where('email', 'like', '+%')
            ->whereNull('consumed_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('sms_messages')
                    ->whereColumn('sms_messages.store_id', 'booking_auth_codes.store_id')
                    ->whereColumn('sms_messages.to_phone', 'booking_auth_codes.email')
                    ->where('sms_messages.type', SmsMessage::TYPE_AUTH_OTP)
                    ->whereColumn('sms_messages.sent_at', '>=', 'booking_auth_codes.created_at')
                    ->whereRaw('sms_messages.sent_at <= DATE_ADD(booking_auth_codes.created_at, INTERVAL 10 MINUTE)');
            })
            ->orderByDesc('created_at');
    }

    /**
     * Código introduzido com erro (tentativas) mas nunca validado.
     *
     * @return Builder<BookingAuthCode>
     */
    public function otpFailedQuery(int $storeId): Builder
    {
        return BookingAuthCode::query()
            ->where('store_id', $storeId)
            ->whereNull('consumed_at')
            ->where('attempts', '>', 0)
            ->orderByDesc('updated_at');
    }

    /**
     * Utilizadores de marcação online (role cliente) sem marcação na loja desde a criação da conta.
     * Marcações históricas anteriores à conta (ex.: import Zappy) não contam.
     *
     * @return Builder<User>
     */
    public function bookingUsersWithoutMarcacaoQuery(int $storeId): Builder
    {
        return User::query()
            ->where('role', User::ROLE_CLIENTE)
            ->whereNotNull('client_id')
            ->whereHas('client', fn (Builder $q): Builder => $q->where('store_id', $storeId))
            ->whereNotExists(function ($query) use ($storeId): void {
                $query->selectRaw('1')
                    ->from('calendar_events')
                    ->whereColumn('calendar_events.client_id', 'users.client_id')
                    ->where('calendar_events.store_id', $storeId)
                    ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
                    ->whereColumn('calendar_events.created_at', '>=', 'users.created_at');
            })
            ->with(['client:id,name,email,phone,store_id,created_at'])
            ->orderByDesc('created_at');
    }

    /**
     * Reserva temporária de horário que expirou ou foi libertada sem conclusão.
     *
     * @return Builder<BookingSlotHold>
     */
    public function abandonedHoldsQuery(int $storeId): Builder
    {
        return BookingSlotHold::query()
            ->where('store_id', $storeId)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('released_at')
                        ->where('expires_at', '<', now());
                })->orWhere(function (Builder $inner): void {
                    $inner->whereNotNull('released_at')
                        ->whereNotIn('release_reason', ['replaced', 'conflict']);
                });
            })
            ->with([
                'bookingUser:id,name,email,client_id',
                'bookingUser.client:id,name,email,phone',
                'selectedUser:id,name',
            ])
            ->orderByDesc('created_at');
    }

    /**
     * @return Collection<int, BookingAuthCode>
     */
    public function paginatedTabQuery(string $tab, int $storeId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = match ($tab) {
            self::TAB_SMS_PENDING => $this->otpSmsWithoutResponseQuery($storeId),
            self::TAB_OTP_FAILED => $this->otpFailedQuery($storeId),
            self::TAB_ACCOUNTS => $this->bookingUsersWithoutMarcacaoQuery($storeId),
            self::TAB_HOLDS => $this->abandonedHoldsQuery($storeId),
            default => $this->otpSmsWithoutResponseQuery($storeId),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function bookingUserHadPreExistingCrmClient(User $user): bool
    {
        $client = $user->client;
        if (! $client || ! $user->created_at || ! $client->created_at) {
            return false;
        }

        return $client->created_at->lt($user->created_at->copy()->subDay());
    }

    public function authCodeChannelLabel(BookingAuthCode $row): string
    {
        $identifier = trim((string) $row->email);

        return str_contains($identifier, '@') ? 'Email' : 'SMS';
    }

    public function authCodeStatusLabel(BookingAuthCode $row): string
    {
        if ($row->consumed_at !== null) {
            return 'Validado';
        }
        if ((int) $row->attempts > 0) {
            return 'Tentativas falhadas';
        }
        if ($row->expires_at !== null && $row->expires_at->isPast()) {
            return 'Expirado';
        }

        return 'Aguarda código';
    }

    /**
     * @param  Collection<int, BookingAuthCode>  $codes
     * @return array<int, Client|null> keyed by booking_auth_codes.id
     */
    public function clientsForAuthCodes(Collection $codes, int $storeId): array
    {
        if ($codes->isEmpty()) {
            return [];
        }

        $phones = [];
        $emails = [];
        foreach ($codes as $code) {
            $identifier = trim((string) $code->email);
            if ($identifier === '') {
                continue;
            }
            if (str_contains($identifier, '@')) {
                $emails[strtolower($identifier)] = true;
            } else {
                $phones[$identifier] = true;
            }
        }

        $clients = Client::query()
            ->forStore($storeId)
            ->get(['id', 'name', 'email', 'phone']);

        $byEmail = [];
        $byPhone = [];
        foreach ($clients as $client) {
            $email = strtolower(trim((string) ($client->email ?? '')));
            if ($email !== '') {
                $byEmail[$email] = $client;
            }
            $phone = PhoneDisplay::toE164((string) ($client->phone ?? ''));
            if ($phone !== null) {
                $byPhone[$phone] = $client;
            }
        }

        $out = [];
        foreach ($codes as $code) {
            $identifier = trim((string) $code->email);
            if (str_contains($identifier, '@')) {
                $out[$code->id] = $byEmail[strtolower($identifier)] ?? null;
            } else {
                $out[$code->id] = $byPhone[$identifier] ?? null;
            }
        }

        return $out;
    }
}
