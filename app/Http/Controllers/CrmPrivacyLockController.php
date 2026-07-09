<?php

namespace App\Http\Controllers;

use App\Support\CrmPrivacyLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmPrivacyLockController extends Controller
{
    private const SESSION_UNLOCK_FAILS = 'crm_privacy_unlock_fails';
    private const SESSION_UNLOCK_LOCKED_UNTIL = 'crm_privacy_unlock_locked_until';
    private const MAX_UNLOCK_FAILS = 5;
    private const LOCK_MINUTES_AFTER_FAILS = 2;

    public function status(CrmPrivacyLock $privacyLock): JsonResponse
    {
        return response()->json([
            'configured' => $privacyLock->isConfigured(),
            'locked' => $privacyLock->isActive(),
            'locked_at' => $privacyLock->lockedAt(),
        ]);
    }

    public function lock(CrmPrivacyLock $privacyLock): JsonResponse
    {
        $locked = $privacyLock->lock();

        return response()->json([
            'configured' => $privacyLock->isConfigured(),
            'locked' => $locked,
            'locked_at' => $privacyLock->lockedAt(),
        ]);
    }

    public function unlock(Request $request, CrmPrivacyLock $privacyLock): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $lockedUntil = (int) $request->session()->get(self::SESSION_UNLOCK_LOCKED_UNTIL, 0);
        if ($lockedUntil > now()->timestamp) {
            return response()->json([
                'error' => 'unlock_rate_limited',
                'message' => 'Demasiadas tentativas. Aguarde antes de tentar novamente.',
            ], 429);
        }

        if (! $privacyLock->unlock((string) $request->input('pin'))) {
            $fails = ((int) $request->session()->get(self::SESSION_UNLOCK_FAILS, 0)) + 1;
            $request->session()->put(self::SESSION_UNLOCK_FAILS, $fails);

            if ($fails >= self::MAX_UNLOCK_FAILS) {
                $request->session()->put(
                    self::SESSION_UNLOCK_LOCKED_UNTIL,
                    now()->addMinutes(self::LOCK_MINUTES_AFTER_FAILS)->timestamp
                );
                $request->session()->put(self::SESSION_UNLOCK_FAILS, 0);
            }

            return response()->json([
                'error' => 'invalid_pin',
                'message' => 'PIN inválido.',
            ], 422);
        }

        $request->session()->forget([
            self::SESSION_UNLOCK_FAILS,
            self::SESSION_UNLOCK_LOCKED_UNTIL,
        ]);

        return response()->json([
            'locked' => false,
            'locked_at' => null,
        ]);
    }
}
