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
        'organization_id',
        'store_id',
        'key',
        'setting_scope',
        'value',
    ];

    /**
     * Chaves partilhadas por toda a organização (não por loja).
     *
     * @return list<string>
     */
    public static function organizationScopedKeys(): array
    {
        return [
            self::KEY_PAYMENT_METHODS,
            self::KEY_STRIPE_ENABLED,
            self::KEY_STRIPE_PUBLISHABLE_KEY,
            self::KEY_STRIPE_SECRET_KEY,
            self::KEY_STRIPE_WEBHOOK_SECRET,
        ];
    }

    public static function isOrganizationScopedKey(string $key): bool
    {
        return in_array($key, self::organizationScopedKeys(), true);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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

    /**
     * Resolve organização a partir de uma loja de contexto (ou sessão).
     */
    public static function resolveOrganizationId(?int $storeId = null): int
    {
        $sid = self::resolveStoreId($storeId);
        $orgId = Store::query()->whereKey($sid)->value('organization_id');
        if ($orgId) {
            return (int) $orgId;
        }

        if (function_exists('current_organization_id')) {
            $fromHelper = (int) current_organization_id();
            if ($fromHelper > 0) {
                return $fromHelper;
            }
        }

        return 0;
    }

    public static function storeSettingScope(int $storeId, string $key): string
    {
        return 's:'.$storeId.':'.$key;
    }

    public static function organizationSettingScope(int $organizationId, string $key): string
    {
        return 'o:'.$organizationId.':'.$key;
    }

    public static function getBool(string $key, bool $default = false, ?int $storeId = null): bool
    {
        $raw = self::getRawValue($key, $storeId);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $s = strtolower(trim((string) $raw));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    public static function setBool(string $key, bool $value, ?int $storeId = null): void
    {
        self::setRawValue($key, $value ? '1' : '0', $storeId);
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
        $raw = self::getRawValue($key, $storeId);
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
        self::setRawValue($key, (string) $value, $storeId);
    }

    public static function getString(string $key, string $default = '', ?int $storeId = null): string
    {
        $raw = self::getRawValue($key, $storeId);
        if ($raw === null) {
            return $default;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : $default;
    }

    public static function setString(string $key, string $value, ?int $storeId = null): void
    {
        self::setRawValue($key, trim($value), $storeId);
    }

    private static function getRawValue(string $key, ?int $storeId = null): mixed
    {
        $hasScope = \Illuminate\Support\Facades\Schema::hasColumn('crm_settings', 'setting_scope');

        if (self::isOrganizationScopedKey($key)) {
            $orgId = self::resolveOrganizationId($storeId);
            if ($orgId <= 0) {
                return null;
            }

            if ($hasScope) {
                $orgValue = static::query()
                    ->where('setting_scope', self::organizationSettingScope($orgId, $key))
                    ->value('value');
                if ($orgValue !== null) {
                    return $orgValue;
                }
            }

            // Compat: dados antigos ainda por loja (pré-migração).
            $storeIds = Store::query()
                ->where('organization_id', $orgId)
                ->orderBy('id')
                ->pluck('id');
            if ($storeIds->isEmpty()) {
                return null;
            }

            return static::query()
                ->whereIn('store_id', $storeIds)
                ->where('key', $key)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->orderBy('id')
                ->value('value')
                ?? static::query()
                    ->whereIn('store_id', $storeIds)
                    ->where('key', $key)
                    ->orderBy('id')
                    ->value('value');
        }

        $sid = self::resolveStoreId($storeId);
        if ($hasScope) {
            $scoped = static::query()
                ->where('setting_scope', self::storeSettingScope($sid, $key))
                ->value('value');
            if ($scoped !== null) {
                return $scoped;
            }
        }

        return static::query()->where('store_id', $sid)->where('key', $key)->value('value');
    }

    private static function setRawValue(string $key, string $value, ?int $storeId = null): void
    {
        $hasScope = \Illuminate\Support\Facades\Schema::hasColumn('crm_settings', 'setting_scope');

        if (self::isOrganizationScopedKey($key)) {
            $orgId = self::resolveOrganizationId($storeId);
            if ($orgId <= 0) {
                throw new \RuntimeException('Organização em falta para gravar definição de pagamentos.');
            }

            if ($hasScope) {
                $scope = self::organizationSettingScope($orgId, $key);
                static::query()->updateOrCreate(
                    ['setting_scope' => $scope],
                    [
                        'organization_id' => $orgId,
                        'store_id' => null,
                        'key' => $key,
                        'value' => $value,
                    ],
                );
            } else {
                // Pré-migração: grava na loja de contexto (leitura já agrega por org).
                $sid = self::resolveStoreId($storeId);
                static::query()->updateOrCreate(
                    ['store_id' => $sid, 'key' => $key],
                    ['value' => $value],
                );
            }

            $storeIds = Store::query()->where('organization_id', $orgId)->pluck('id');
            if ($hasScope && $storeIds->isNotEmpty()) {
                static::query()
                    ->whereIn('store_id', $storeIds)
                    ->where('key', $key)
                    ->delete();
            }

            return;
        }

        $sid = self::resolveStoreId($storeId);
        if ($hasScope) {
            $scope = self::storeSettingScope($sid, $key);
            static::query()->updateOrCreate(
                ['setting_scope' => $scope],
                [
                    'organization_id' => null,
                    'store_id' => $sid,
                    'key' => $key,
                    'value' => $value,
                ],
            );

            return;
        }

        static::query()->updateOrCreate(
            ['store_id' => $sid, 'key' => $key],
            ['value' => $value],
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
        return self::getInheritableBool(self::KEY_EMAIL_USE_BUSINESS_BRANDING, false, $storeId);
    }

    public static function setEmailUseBusinessBranding(bool $enabled, ?int $storeId = null): void
    {
        self::setBool(self::KEY_EMAIL_USE_BUSINESS_BRANDING, $enabled, $storeId);
    }

    public static function setOrganizationEmailUseBusinessBranding(bool $enabled, int $organizationId): void
    {
        self::setOrganizationRawValue(self::KEY_EMAIL_USE_BUSINESS_BRANDING, $enabled ? '1' : '0', $organizationId);
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
        return max(0, min(240, self::getInheritableInt(self::KEY_PRIVACY_LOCK_IDLE_MINUTES, 5, $storeId)));
    }

    public static function setPrivacyLockIdleMinutes(int $minutes, ?int $storeId = null): void
    {
        $clamped = max(0, min(240, $minutes));
        self::setInt(self::KEY_PRIVACY_LOCK_IDLE_MINUTES, $clamped, $storeId);
    }

    public static function setOrganizationPrivacyLockIdleMinutes(int $minutes, int $organizationId): void
    {
        $clamped = max(0, min(240, $minutes));
        self::setOrganizationRawValue(self::KEY_PRIVACY_LOCK_IDLE_MINUTES, (string) $clamped, $organizationId);
    }

    public static function storeHasSettingOverride(int $storeId, string $key): bool
    {
        return static::query()
            ->where('setting_scope', self::storeSettingScope($storeId, $key))
            ->exists();
    }

    public static function clearStoreSettingOverride(int $storeId, string $key): void
    {
        static::query()
            ->where('setting_scope', self::storeSettingScope($storeId, $key))
            ->delete();
    }

    private static function getInheritableBool(string $key, bool $default, ?int $storeId = null): bool
    {
        $sid = self::resolveStoreId($storeId);
        if (self::storeHasSettingOverride($sid, $key)) {
            return self::getBool($key, $default, $sid);
        }

        $orgId = self::resolveOrganizationId($sid);
        if ($orgId > 0) {
            $raw = static::query()
                ->where('setting_scope', self::organizationSettingScope($orgId, $key))
                ->value('value');
            if ($raw !== null && $raw !== '') {
                $s = strtolower(trim((string) $raw));

                return in_array($s, ['1', 'true', 'yes', 'on'], true);
            }
        }

        return $default;
    }

    private static function getInheritableInt(string $key, int $default, ?int $storeId = null): int
    {
        $sid = self::resolveStoreId($storeId);
        if (self::storeHasSettingOverride($sid, $key)) {
            return self::getInt($key, $default, $sid);
        }

        $orgId = self::resolveOrganizationId($sid);
        if ($orgId > 0) {
            $raw = static::query()
                ->where('setting_scope', self::organizationSettingScope($orgId, $key))
                ->value('value');
            if ($raw !== null && trim((string) $raw) !== '' && is_numeric($raw)) {
                return (int) $raw;
            }
        }

        return $default;
    }

    private static function setOrganizationRawValue(string $key, string $value, int $organizationId): void
    {
        $scope = self::organizationSettingScope($organizationId, $key);
        static::query()->updateOrCreate(
            ['setting_scope' => $scope],
            [
                'organization_id' => $organizationId,
                'store_id' => null,
                'key' => $key,
                'value' => $value,
            ],
        );
    }
}
