<?php

namespace App\Models;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSetting extends Model
{
    public const KEY_BOOKING_ONLINE_PAYMENT_REQUIRED = 'booking.online_payment_required';

    /** Caixa (modal de pagamento na agenda): campo e linha de gorjeta. */
    public const KEY_POS_GORJETA_ENABLED = 'pos.gorjeta_enabled';

    /** JSON: métodos de pagamento por canal (caixa / booking / reserva). */
    public const KEY_PAYMENT_METHODS = 'payments.methods';

    public const KEY_STRIPE_ENABLED = 'payments.stripe.enabled';

    public const KEY_STRIPE_PUBLISHABLE_KEY = 'payments.stripe.publishable_key';

    /** Secret Stripe (encriptado com Crypt). */
    public const KEY_STRIPE_SECRET_KEY = 'payments.stripe.secret_key';

    /** Webhook signing secret (encriptado). */
    public const KEY_STRIPE_WEBHOOK_SECRET = 'payments.stripe.webhook_secret';

    public const KEY_BOOKING_SLOT_HOLD_MINUTES = 'booking.slot_hold_minutes';

    public const KEY_BOOKING_ANY_STAFF_RULE = 'booking.any_staff_rule';

    public const KEY_BOOKING_CANCELLATION_NOTICE_HOURS = 'booking.cancellation_notice_hours';

    public const KEY_BOOKING_THEME = 'booking.theme';

    public const KEY_EMAIL_USE_BUSINESS_BRANDING = 'email.use_business_branding';

    /** Agenda — tempo pessoal: limitar horas de início/fim ao horário da loja. */
    public const KEY_AGENDA_PERSONAL_TIME_LIMIT_STORE_HOURS = 'agenda.personal_time_limit_store_hours';

    public const KEY_PRIVACY_LOCK_PIN_HASH = 'privacy_lock.pin_hash';
    public const KEY_PRIVACY_LOCK_IDLE_MINUTES = 'privacy_lock.idle_minutes';

    public const BOOKING_CANCELLATION_NOTICE_HOURS_MIN = 0;

    public const BOOKING_CANCELLATION_NOTICE_HOURS_MAX = 168;

    public const BOOKING_CANCELLATION_NOTICE_HOURS_DEFAULT = 3;

    public const BOOKING_ANY_STAFF_RULE_A = 'day_load_then_agenda_order';

    public const BOOKING_ANY_STAFF_RULE_B = 'agenda_order_then_day_load';

    public const BOOKING_ANY_STAFF_RULE_C = 'month_load_then_agenda_order';

    public const BOOKING_ANY_STAFF_RULE_D = 'agenda_order_then_month_load';

    protected $fillable = [
        'store_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Backoffice: loja da sessão. Booking público: passar $storeId ou usar {@see Store::defaultPublicBookingStoreId()}.
     */
    public static function resolveStoreId(?int $storeId = null): int
    {
        if ($storeId !== null) {
            return $storeId;
        }

        $try = app(CurrentStore::class)->tryId();
        if ($try !== null) {
            return $try;
        }

        return Store::defaultPublicBookingStoreId();
    }

    public static function getBool(string $key, bool $default = false, ?int $storeId = null): bool
    {
        $sid = self::resolveStoreId($storeId);
        $raw = static::query()->where('store_id', $sid)->where('key', $key)->value('value');
        if ($raw === null || $raw === '') {
            return $default;
        }
        $s = strtolower(trim((string) $raw));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    public static function setBool(string $key, bool $value, ?int $storeId = null): void
    {
        $sid = self::resolveStoreId($storeId);
        static::query()->updateOrCreate(
            ['store_id' => $sid, 'key' => $key],
            ['value' => $value ? '1' : '0'],
        );
    }

    public static function onlineBookingPaymentRequired(?int $storeId = null): bool
    {
        return self::getBool(self::KEY_BOOKING_ONLINE_PAYMENT_REQUIRED, true, $storeId);
    }

    /**
     * Pagamentos Stripe activos no booking online (toggle marcações + Stripe pronto).
     */
    public static function onlineBookingStripeEnabled(?int $storeId = null): bool
    {
        if (! self::onlineBookingPaymentRequired($storeId)) {
            return false;
        }

        return \App\Support\StripeCredentials::isReady($storeId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function paymentMethodsConfig(?int $storeId = null): array
    {
        $raw = self::getString(self::KEY_PAYMENT_METHODS, '', $storeId);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function setPaymentMethodsConfig(array $rows, ?int $storeId = null): void
    {
        self::setString(self::KEY_PAYMENT_METHODS, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE), $storeId);
    }

    public static function posGorjetaEnabled(?int $storeId = null): bool
    {
        return self::getBool(self::KEY_POS_GORJETA_ENABLED, true, $storeId);
    }

    public static function getInt(string $key, int $default = 0, ?int $storeId = null): int
    {
        $sid = self::resolveStoreId($storeId);
        $raw = static::query()->where('store_id', $sid)->where('key', $key)->value('value');
        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }
        if (! is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    public static function setInt(string $key, int $value, ?int $storeId = null): void
    {
        $sid = self::resolveStoreId($storeId);
        static::query()->updateOrCreate(
            ['store_id' => $sid, 'key' => $key],
            ['value' => (string) $value],
        );
    }

    public static function getString(string $key, string $default = '', ?int $storeId = null): string
    {
        $sid = self::resolveStoreId($storeId);
        $raw = static::query()->where('store_id', $sid)->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $default;
    }

    public static function setString(string $key, string $value, ?int $storeId = null): void
    {
        $sid = self::resolveStoreId($storeId);
        static::query()->updateOrCreate(
            ['store_id' => $sid, 'key' => $key],
            ['value' => trim($value)],
        );
    }

    public static function bookingSlotHoldMinutes(?int $storeId = null): int
    {
        return max(1, self::getInt(self::KEY_BOOKING_SLOT_HOLD_MINUTES, 6, $storeId));
    }

    public static function bookingCancellationNoticeHours(?int $storeId = null): int
    {
        $hours = self::getInt(
            self::KEY_BOOKING_CANCELLATION_NOTICE_HOURS,
            self::BOOKING_CANCELLATION_NOTICE_HOURS_DEFAULT,
            $storeId,
        );

        return max(
            self::BOOKING_CANCELLATION_NOTICE_HOURS_MIN,
            min(self::BOOKING_CANCELLATION_NOTICE_HOURS_MAX, $hours),
        );
    }

    public static function setBookingCancellationNoticeHours(int $hours, ?int $storeId = null): void
    {
        $clamped = max(
            self::BOOKING_CANCELLATION_NOTICE_HOURS_MIN,
            min(self::BOOKING_CANCELLATION_NOTICE_HOURS_MAX, $hours),
        );
        self::setInt(self::KEY_BOOKING_CANCELLATION_NOTICE_HOURS, $clamped, $storeId);
    }

    /**
     * Rótulo curto para UI (ex.: "3 horas", "1 hora", "até ao início da marcação").
     */
    public static function bookingCancellationNoticeHoursLabel(int $hours): string
    {
        if ($hours <= 0) {
            return 'até ao início da marcação';
        }
        if ($hours === 1) {
            return '1 hora';
        }

        return $hours.' horas';
    }

    /**
     * Texto da política de cancelamento para o fluxo público (checkout, conta, SMS).
     */
    public static function bookingCancellationPolicyNoticeText(?int $storeId = null): string
    {
        $hours = self::bookingCancellationNoticeHours($storeId);
        $notice = self::bookingCancellationNoticeHoursLabel($hours);
        $paymentRequired = self::onlineBookingPaymentRequired($storeId);

        if (! $paymentRequired) {
            if ($hours <= 0) {
                return 'Pode cancelar '.$notice.'.';
            }

            return 'As marcações só podem ser canceladas online com um aviso prévio de '
                .$notice
                .' em relação à hora da marcação.';
        }

        if ($hours <= 0) {
            return 'Pode cancelar '.$notice.'. Fora deste momento, o pré-pagamento online não é devolvido (nem em dinheiro nem em créditos).';
        }

        return 'As marcações só podem ser canceladas sem perda do pré-pagamento com um aviso prévio de '
            .$notice
            .' em relação à hora da marcação. Fora deste prazo, o pré-pagamento não é devolvido (nem em dinheiro nem em créditos).';
    }

    /**
     * Textos para o ecrã de definições (título + descrição).
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function bookingAnyStaffRulesUi(): array
    {
        return [
            self::BOOKING_ANY_STAFF_RULE_A => [
                'title' => 'Equilibrar o dia entre colaboradores.',
                'description' => 'Distribui as marcações por quem tem menos clientes hoje.',
            ],
            self::BOOKING_ANY_STAFF_RULE_B => [
                'title' => 'Atender o cliente o mais cedo possível',
                'description' => 'Escolhe sempre o horário disponível mais próximo.',
            ],
            self::BOOKING_ANY_STAFF_RULE_C => [
                'title' => 'Equilibrar o trabalho ao longo do mês',
                'description' => 'Dá prioridade a quem teve menos clientes este mês.',
            ],
            self::BOOKING_ANY_STAFF_RULE_D => [
                'title' => 'Atender cedo, mantendo equilíbrio mensal',
                'description' => 'Escolhe o horário mais cedo e, em caso de empate, equilibra entre colaboradores.',
            ],
        ];
    }

    /**
     * @return array<string, string> mapa id da regra → título (uma linha)
     */
    public static function bookingAnyStaffRules(): array
    {
        $ui = self::bookingAnyStaffRulesUi();

        return array_map(fn (array $row): string => $row['title'], $ui);
    }

    public static function bookingAnyStaffRule(?int $storeId = null): string
    {
        $rules = self::bookingAnyStaffRulesUi();
        $default = self::BOOKING_ANY_STAFF_RULE_A;
        $value = self::getString(self::KEY_BOOKING_ANY_STAFF_RULE, $default, $storeId);

        return array_key_exists($value, $rules) ? $value : $default;
    }

    public static function bookingTheme(?int $storeId = null): string
    {
        return \App\Support\BookingTheme::resolve(
            self::getString(self::KEY_BOOKING_THEME, \App\Support\BookingTheme::DEFAULT, $storeId),
            $storeId,
        );
    }

    public static function setBookingTheme(string $themeId, ?int $storeId = null): void
    {
        $resolved = \App\Support\BookingTheme::resolve($themeId, $storeId);
        self::setString(self::KEY_BOOKING_THEME, $resolved, $storeId);
    }

    public static function emailUseBusinessBranding(?int $storeId = null): bool
    {
        return self::getBool(self::KEY_EMAIL_USE_BUSINESS_BRANDING, false, $storeId);
    }

    public static function setEmailUseBusinessBranding(bool $enabled, ?int $storeId = null): void
    {
        self::setBool(self::KEY_EMAIL_USE_BUSINESS_BRANDING, $enabled, $storeId);
    }

    public static function personalTimeLimitStoreHours(?int $storeId = null): bool
    {
        return self::getBool(self::KEY_AGENDA_PERSONAL_TIME_LIMIT_STORE_HOURS, false, $storeId);
    }

    public static function setPersonalTimeLimitStoreHours(bool $enabled, ?int $storeId = null): void
    {
        self::setBool(self::KEY_AGENDA_PERSONAL_TIME_LIMIT_STORE_HOURS, $enabled, $storeId);
    }

    public static function privacyLockPinHash(?int $storeId = null): string
    {
        return self::getString(self::KEY_PRIVACY_LOCK_PIN_HASH, '', $storeId);
    }

    public static function setPrivacyLockPinHash(string $hash, ?int $storeId = null): void
    {
        self::setString(self::KEY_PRIVACY_LOCK_PIN_HASH, $hash, $storeId);
    }

    public static function privacyLockEnabled(?int $storeId = null): bool
    {
        return self::privacyLockPinHash($storeId) !== '';
    }

    public static function privacyLockIdleMinutes(?int $storeId = null): int
    {
        return max(0, min(240, self::getInt(self::KEY_PRIVACY_LOCK_IDLE_MINUTES, 5, $storeId)));
    }

    public static function setPrivacyLockIdleMinutes(int $minutes, ?int $storeId = null): void
    {
        $clamped = max(0, min(240, $minutes));
        self::setInt(self::KEY_PRIVACY_LOCK_IDLE_MINUTES, $clamped, $storeId);
    }
}
