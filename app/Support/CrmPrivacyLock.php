<?php

namespace App\Support;

use App\Models\CrmSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Session\Store as SessionStore;

class CrmPrivacyLock
{
    public const SESSION_LOCKED_KEY = 'crm_privacy_locked';
    public const SESSION_LOCKED_AT_KEY = 'crm_privacy_locked_at';

    public function __construct(
        private readonly SessionStore $session,
    ) {}

    public function isActive(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return (bool) $this->session->get(self::SESSION_LOCKED_KEY, false);
    }

    public function lock(): bool
    {
        if (! $this->isConfigured()) {
            $this->unlockWithoutPin();

            return false;
        }

        $this->session->put(self::SESSION_LOCKED_KEY, true);
        $this->session->put(self::SESSION_LOCKED_AT_KEY, now()->toIso8601String());

        return true;
    }

    public function unlock(string $pin): bool
    {
        $hash = CrmSetting::privacyLockPinHash(current_store_id());
        if ($hash === '' || ! Hash::check($pin, $hash)) {
            return false;
        }

        $this->unlockWithoutPin();

        return true;
    }

    public function unlockWithoutPin(): void
    {
        $this->session->forget(self::SESSION_LOCKED_KEY);
        $this->session->forget(self::SESSION_LOCKED_AT_KEY);
    }

    public function lockedAt(): ?string
    {
        $value = $this->session->get(self::SESSION_LOCKED_AT_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isConfigured(): bool
    {
        return CrmSetting::privacyLockEnabled(current_store_id());
    }
}
