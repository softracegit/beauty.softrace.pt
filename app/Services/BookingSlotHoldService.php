<?php

namespace App\Services;

use App\Models\BookingSlotHold;
use App\Models\CrmSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingSlotHoldService
{
    public function acquire(array $validatedHold, string $sessionToken, ?User $actor): BookingSlotHold
    {
        return DB::transaction(function () use ($validatedHold, $sessionToken, $actor) {
            $checkout = app(OnlineBookingCheckoutService::class);
            $storeId = $checkout->storeIdFromBookingServices($validatedHold['services'] ?? []);
            $candidate = $checkout->resolveSlotCandidateForHold($validatedHold);
            $selectedUserId = (int) $candidate['userId'];
            $slotStart = $candidate['startForDb'];
            $slotEnd = $candidate['endForDb'];
            $slotDate = (string) $validatedHold['date'];
            $duration = max(1, (int) $slotStart->diffInMinutes($slotEnd));
            $servicesSignature = $this->servicesSignature($validatedHold['services'] ?? []);
            $now = now();
            $expiresAt = $now->copy()->addSeconds($this->holdSeconds($storeId));

            BookingSlotHold::query()
                ->where('session_token', $sessionToken)
                ->whereNull('released_at')
                ->update([
                    'released_at' => $now,
                    'release_reason' => 'replaced',
                ]);

            $conflict = $this->hasOverlapWithActiveHolds(
                $selectedUserId,
                $slotStart,
                $slotEnd,
                $sessionToken,
                null,
                $storeId
            );
            if ($conflict) {
                throw ValidationException::withMessages([
                    'time' => [__('booking.validation.slot_taken')],
                ]);
            }

            return BookingSlotHold::query()->create([
                'store_id' => $storeId,
                'public_id' => (string) Str::ulid(),
                'session_token' => $sessionToken,
                'booking_user_id' => $actor?->isBookingClient() ? $actor->id : null,
                'selected_user_id' => $selectedUserId,
                'slot_date' => $slotDate,
                'slot_start_at' => $slotStart,
                'slot_end_at' => $slotEnd,
                'duration_minutes' => $duration,
                'agent_id_raw' => isset($validatedHold['agent_id']) ? (string) $validatedHold['agent_id'] : null,
                'services_signature' => $servicesSignature,
                'expires_at' => $expiresAt,
                'meta' => [
                    'services' => $validatedHold['services'] ?? [],
                ],
            ]);
        });
    }

    public function extend(string $publicId, string $sessionToken, ?User $actor): BookingSlotHold
    {
        return DB::transaction(function () use ($publicId, $sessionToken, $actor) {
            $hold = BookingSlotHold::query()
                ->where('public_id', $publicId)
                ->where('session_token', $sessionToken)
                ->lockForUpdate()
                ->first();

            if (! $hold || $hold->released_at !== null) {
                throw ValidationException::withMessages([
                    'hold' => [__('booking.validation.hold_not_found')],
                ]);
            }

            if ($actor instanceof User && $actor->isBookingClient()) {
                if ($hold->booking_user_id !== null && (int) $hold->booking_user_id !== (int) $actor->id) {
                    throw ValidationException::withMessages([
                        'hold' => [__('booking.validation.hold_wrong_session')],
                    ]);
                }
            }

            $now = now();
            $conflict = $this->hasOverlapWithActiveHolds(
                (int) $hold->selected_user_id,
                Carbon::parse((string) $hold->slot_start_at),
                Carbon::parse((string) $hold->slot_end_at),
                $sessionToken,
                $publicId,
                (int) $hold->store_id
            );
            if ($conflict) {
                $hold->released_at = $now;
                $hold->release_reason = 'conflict';
                $hold->save();

                throw ValidationException::withMessages([
                    'time' => [__('booking.validation.slot_taken')],
                ]);
            }

            $services = is_array($hold->meta['services'] ?? null) ? $hold->meta['services'] : [];
            $storeId = app(OnlineBookingCheckoutService::class)->storeIdFromBookingServices($services);
            $hold->expires_at = $now->copy()->addSeconds($this->holdSeconds($storeId));
            $hold->save();

            return $hold;
        });
    }

    public function release(?string $publicId, string $sessionToken, string $reason = 'manual'): void
    {
        if (! $publicId) {
            return;
        }
        BookingSlotHold::query()
            ->where('public_id', $publicId)
            ->where('session_token', $sessionToken)
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'release_reason' => $reason,
            ]);
    }

    public function assertCheckoutHold(array $validatedBookingPayload, string $publicId, string $sessionToken, ?User $actor): BookingSlotHold
    {
        if ($publicId === '' || $sessionToken === '') {
            throw ValidationException::withMessages([
                'time' => [__('booking.validation.hold_expired')],
            ]);
        }

        $hold = BookingSlotHold::query()
            ->where('public_id', $publicId)
            ->where('session_token', $sessionToken)
            ->lockForUpdate()
            ->first();

        if (! $hold || $hold->released_at !== null || $hold->expires_at === null || $hold->expires_at->lte(now())) {
            throw ValidationException::withMessages([
                'time' => [__('booking.validation.hold_expired')],
            ]);
        }

        $urlStore = request()->route('store');
        if ($urlStore instanceof \App\Models\Store && (int) $hold->store_id !== (int) $urlStore->id) {
            throw ValidationException::withMessages([
                'time' => [__('booking.validation.hold_wrong_store')],
            ]);
        }

        if ($actor instanceof User && $actor->isBookingClient()) {
            if ($hold->booking_user_id !== null && (int) $hold->booking_user_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'time' => [__('booking.validation.hold_wrong_user')],
                ]);
            }
        }

        $checkout = app(OnlineBookingCheckoutService::class);
        $candidate = $checkout->resolveSlotCandidateForHold($validatedBookingPayload);
        $candidateStart = Carbon::parse((string) $candidate['startForDb']);
        $candidateEnd = Carbon::parse((string) $candidate['endForDb']);
        $candidateServicesSig = $this->servicesSignature($validatedBookingPayload['services'] ?? []);

        if (
            (int) $hold->selected_user_id !== (int) $candidate['userId']
            || ! $hold->slot_start_at?->equalTo($candidateStart)
            || ! $hold->slot_end_at?->equalTo($candidateEnd)
            || (string) $hold->services_signature !== $candidateServicesSig
        ) {
            throw ValidationException::withMessages([
                'time' => [__('booking.validation.hold_selection_changed')],
            ]);
        }

        return $hold;
    }

    /**
     * @return array{0:int,1:int}[]
     */
    public function busyIntervalsForUserOnDay(int $userId, CarbonInterface $day, ?string $excludeSessionToken = null): array
    {
        $tz = (string) config('booking.business_timezone');
        $rangeStart = $day->copy()->timezone($tz)->startOfDay();
        $rangeEnd = $rangeStart->copy()->addDay();

        $query = BookingSlotHold::query()
            ->active()
            ->where('selected_user_id', $userId)
            ->where('slot_start_at', '<', $rangeEnd)
            ->where('slot_end_at', '>', $rangeStart)
            ->orderBy('slot_start_at');
        if ($excludeSessionToken) {
            $query->where('session_token', '!=', $excludeSessionToken);
        }

        $out = [];
        foreach ($query->get(['slot_start_at', 'slot_end_at']) as $hold) {
            if (! $hold->slot_start_at || ! $hold->slot_end_at) {
                continue;
            }
            $st = $hold->slot_start_at->copy()->timezone($tz);
            $en = $hold->slot_end_at->copy()->timezone($tz);
            $holdStart = max($st->timestamp, $rangeStart->timestamp);
            $holdEnd = min($en->timestamp, $rangeEnd->timestamp);
            if ($holdEnd <= $holdStart) {
                continue;
            }
            $sMin = (int) floor(($holdStart - $rangeStart->timestamp) / 60);
            $eMin = (int) ceil(($holdEnd - $rangeStart->timestamp) / 60);
            if ($eMin > $sMin) {
                $out[] = [$sMin, $eMin];
            }
        }

        return $out;
    }

    private function hasOverlapWithActiveHolds(
        int $selectedUserId,
        CarbonInterface $slotStart,
        CarbonInterface $slotEnd,
        string $sessionToken,
        ?string $excludePublicId = null,
        ?int $storeId = null
    ): bool {
        $q = BookingSlotHold::query()
            ->active()
            ->when($storeId !== null, fn ($b) => $b->where('store_id', $storeId))
            ->where('selected_user_id', $selectedUserId)
            ->where('session_token', '!=', $sessionToken)
            ->where('slot_start_at', '<', $slotEnd)
            ->where('slot_end_at', '>', $slotStart);
        if ($excludePublicId) {
            $q->where('public_id', '!=', $excludePublicId);
        }

        return $q->exists();
    }

    /**
     * @param  list<array<string,mixed>>  $services
     */
    private function servicesSignature(array $services): string
    {
        $normalized = [];
        foreach ($services as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $opt = $row['service_option_id'] ?? null;
            $optNorm = ($opt === null || $opt === '') ? null : (int) $opt;
            $normalized[] = [$sid, $optNorm];
        }
        usort($normalized, function (array $a, array $b): int {
            if ($a[0] === $b[0]) {
                return ($a[1] ?? -1) <=> ($b[1] ?? -1);
            }

            return $a[0] <=> $b[0];
        });

        return hash('sha256', json_encode($normalized));
    }

    private function holdSeconds(?int $storeId = null): int
    {
        return max(10, CrmSetting::bookingSlotHoldMinutes($storeId) * 60);
    }
}
