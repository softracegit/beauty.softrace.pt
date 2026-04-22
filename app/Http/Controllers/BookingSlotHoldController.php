<?php

namespace App\Http\Controllers;

use App\Models\CrmSetting;
use App\Services\BookingSlotHoldService;
use App\Services\OnlineBookingCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingSlotHoldController extends Controller
{
    public function __construct(
        private OnlineBookingCheckoutService $checkout,
        private BookingSlotHoldService $holds,
    ) {}

    public function acquire(Request $request): JsonResponse
    {
        $validated = $request->validate($this->checkout->slotHoldRules() + [
            'hold_session_token' => ['required', 'string', 'min:16', 'max:80'],
        ]);

        $hold = $this->holds->acquire(
            $validated,
            (string) $validated['hold_session_token'],
            $request->user()
        );

        return response()->json($this->holdPayload($hold));
    }

    public function extend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hold_public_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'],
            'hold_session_token' => ['required', 'string', 'min:16', 'max:80'],
        ]);

        $hold = $this->holds->extend(
            (string) $validated['hold_public_id'],
            (string) $validated['hold_session_token'],
            $request->user()
        );

        return response()->json($this->holdPayload($hold));
    }

    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hold_public_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'],
            'hold_session_token' => ['required', 'string', 'min:16', 'max:80'],
            'reason' => ['nullable', 'string', 'max:32'],
        ]);

        $this->holds->release(
            (string) $validated['hold_public_id'],
            (string) $validated['hold_session_token'],
            isset($validated['reason']) ? (string) $validated['reason'] : 'manual'
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function holdPayload(\App\Models\BookingSlotHold $hold): array
    {
        $expiresAt = $hold->expires_at instanceof Carbon ? $hold->expires_at : Carbon::parse((string) $hold->expires_at);

        return [
            'ok' => true,
            'hold_public_id' => (string) $hold->public_id,
            'expires_at' => $expiresAt->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'hold_seconds' => max(10, CrmSetting::bookingSlotHoldMinutes() * 60),
        ];
    }
}

