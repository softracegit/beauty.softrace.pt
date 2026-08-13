<?php

namespace App\Support;

use App\Models\CrmSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Throwable;

/**
 * Credenciais Stripe por loja (Definições → Pagamentos).
 * Sem fallback para .env — cada loja configura as suas chaves.
 */
class StripeCredentials
{
    public static function isEnabled(?int $storeId = null): bool
    {
        return CrmSetting::getBool(CrmSetting::KEY_STRIPE_ENABLED, false, $storeId);
    }

    public static function setEnabled(bool $enabled, ?int $storeId = null): void
    {
        CrmSetting::setBool(CrmSetting::KEY_STRIPE_ENABLED, $enabled, $storeId);
    }

    public static function publishableKey(?int $storeId = null): string
    {
        return trim(CrmSetting::getString(CrmSetting::KEY_STRIPE_PUBLISHABLE_KEY, '', $storeId));
    }

    public static function secretKey(?int $storeId = null): string
    {
        $enc = CrmSetting::getString(CrmSetting::KEY_STRIPE_SECRET_KEY, '', $storeId);
        if ($enc === '') {
            return '';
        }

        try {
            $plain = Crypt::decryptString($enc);

            return is_string($plain) ? trim($plain) : '';
        } catch (Throwable) {
            // Valor antigo em texto claro (migração) ou corrompido.
            return trim($enc);
        }
    }

    public static function webhookSecret(?int $storeId = null): string
    {
        $sid = CrmSetting::resolveStoreId($storeId);
        $enc = CrmSetting::getString(CrmSetting::KEY_STRIPE_WEBHOOK_SECRET, '', $sid);
        if ($enc === '') {
            return '';
        }

        try {
            $plain = Crypt::decryptString($enc);

            return is_string($plain) ? trim($plain) : '';
        } catch (Throwable) {
            return trim($enc);
        }
    }

    /**
     * Secrets de webhook gravados nas lojas (endpoint global /stripe/webhook).
     *
     * @return list<string>
     */
    public static function allWebhookSecrets(): array
    {
        $rows = CrmSetting::query()
            ->where('key', CrmSetting::KEY_STRIPE_WEBHOOK_SECRET)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->orderBy('id')
            ->get(['value']);

        $secrets = [];
        foreach ($rows as $row) {
            try {
                $plain = trim(Crypt::decryptString((string) $row->value));
            } catch (Throwable) {
                $plain = trim((string) $row->value);
            }
            if ($plain !== '' && ! in_array($plain, $secrets, true)) {
                $secrets[] = $plain;
            }
        }

        return $secrets;
    }

    public static function keysConfigured(?int $storeId = null): bool
    {
        return self::publishableKey($storeId) !== '' && self::secretKey($storeId) !== '';
    }

    /**
     * Stripe utilizável: toggle activo + API keys + webhook secret da loja.
     */
    public static function isReady(?int $storeId = null): bool
    {
        return self::isEnabled($storeId)
            && self::keysConfigured($storeId)
            && self::webhookSecret($storeId) !== '';
    }

    public static function webhookConfigured(?int $storeId = null): bool
    {
        return self::webhookSecret($storeId) !== '';
    }

    public static function setPublishableKey(string $key, ?int $storeId = null): void
    {
        CrmSetting::setString(CrmSetting::KEY_STRIPE_PUBLISHABLE_KEY, trim($key), $storeId);
    }

    public static function setSecretKey(?string $secret, ?int $storeId = null): void
    {
        if ($secret === null) {
            return;
        }
        $secret = trim($secret);
        if ($secret === '') {
            CrmSetting::setString(CrmSetting::KEY_STRIPE_SECRET_KEY, '', $storeId);

            return;
        }
        CrmSetting::setString(CrmSetting::KEY_STRIPE_SECRET_KEY, Crypt::encryptString($secret), $storeId);
    }

    public static function setWebhookSecret(?string $secret, ?int $storeId = null): void
    {
        if ($secret === null) {
            return;
        }
        $secret = trim($secret);
        if ($secret === '') {
            CrmSetting::setString(CrmSetting::KEY_STRIPE_WEBHOOK_SECRET, '', $storeId);

            return;
        }
        CrmSetting::setString(CrmSetting::KEY_STRIPE_WEBHOOK_SECRET, Crypt::encryptString($secret), $storeId);
    }

    public static function configureSdk(?int $storeId = null): void
    {
        $secret = self::secretKey($storeId);
        if ($secret === '') {
            throw new \RuntimeException('Stripe não configurado nesta loja. Definições → Pagamentos.');
        }

        Stripe::setApiKey($secret);

        $apiVersion = config('stripe.api_version');
        if (! is_string($apiVersion) || $apiVersion === '') {
            return;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}\.[a-zA-Z0-9_]+$/', $apiVersion)) {
            Log::warning('STRIPE_API_VERSION ignorada. Usa o valor exacto do Dashboard ou deixa vazio.', [
                'value' => $apiVersion,
            ]);

            return;
        }

        Stripe::setApiVersion($apiVersion);
    }

    /**
     * Máscara para UI (sk_live_…ABCD).
     */
    public static function maskSecret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($secret, 0, 7).str_repeat('•', max(4, $len - 11)).substr($secret, -4);
    }
}
