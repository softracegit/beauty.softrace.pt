<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentCancellationException;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Store;
use App\Models\User;
use App\Services\AppointmentCancellationService;
use App\Services\CancellationPolicyService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingAccountCancelController extends Controller
{
    public function __construct(
        private AppointmentCancellationService $cancellationService,
        private CancellationPolicyService $policyService,
    ) {}

    public function store(Request $request, Store $store, CalendarEvent $calendarEvent): RedirectResponse
    {
        $client = $this->resolveBookingClient($request, $store);
        $this->assertEventCancellableByClient($calendarEvent, $client, $store);

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = trim((string) ($validated['cancellation_reason'] ?? ''));
        if ($reason === '') {
            $reason = __('booking.messages.cancel_default_reason_account');
        }

        try {
            $result = $this->cancellationService->cancel($calendarEvent, [
                'cancellation_reason' => $reason,
                'block_if_outside_notice_period' => true,
                'notify_client' => true,
                'notify_team' => true,
                'previous_status' => (string) ($calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO),
                'from_public_booking' => true,
                'created_by_type' => ClientWalletTransaction::CREATED_BY_CLIENT,
            ]);
        } catch (AppointmentCancellationException $e) {
            return redirect()
                ->route('booking.conta.marcacoes', ['store' => $store->slug])
                ->withErrors(['cancel' => $e->getMessage()]);
        }

        $message = $this->successMessage($result);

        return redirect()
            ->route('booking.conta.marcacoes', ['store' => $store->slug])
            ->with('success', $message);
    }

    private function resolveBookingClient(Request $request, Store $store): Client
    {
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->isBookingClient()) {
            abort(403);
        }

        $client = $user->loadMissing('client')->client;
        if (! $client instanceof Client) {
            abort(404);
        }

        if ((int) $client->store_id !== (int) $store->id) {
            abort(404);
        }

        return $client;
    }

    private function assertEventCancellableByClient(CalendarEvent $event, Client $client, Store $store): void
    {
        if ((int) $event->store_id !== (int) $store->id) {
            abort(404);
        }

        if ((int) $event->client_id !== (int) $client->id) {
            abort(403);
        }

        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            abort(404);
        }

        if ($event->isMarcacaoStatusLocked()) {
            abort(422, __('booking.validation.cancel_not_allowed'));
        }

        if (($event->status ?? '') === CalendarEvent::STATUS_COMPLETO) {
            abort(422, __('booking.validation.cancel_paid_not_allowed'));
        }

        if (! $event->start_at) {
            abort(422, __('booking.validation.cancel_no_date'));
        }

        $tz = $this->policyService->businessTimezoneForStore((int) $event->store_id);
        $startLocal = Carbon::instance($event->start_at)->timezone($tz);
        if ($startLocal->lte(now($tz))) {
            abort(422, __('booking.validation.cancel_past_only_future'));
        }

        $policy = $this->policyService->resolveForEvent($event);
        if (! $policy->canCancelOnline()) {
            abort(422, __('booking.validation.cancel_too_late_contact_store', [
                'deadline' => $policy->deadlineFormatted(),
            ]));
        }
    }

    private function successMessage(\App\Services\AppointmentCancellationResult $result): string
    {
        if ($result->alreadyCancelled) {
            return __('booking.messages.cancel_already_cancelled');
        }

        if ($result->walletCredited && $result->walletCreditAmountCents > 0) {
            $amount = number_format($result->walletCreditAmountCents / 100, 2, ',', ' ');

            return __('booking.messages.cancel_success_credit', ['amount' => $amount]);
        }

        if ($result->policy->isWithinNoticePeriod || ! $result->policy->hasPaidDeposit) {
            return __('booking.messages.cancel_success');
        }

        return __('booking.messages.cancel_success_no_credit');
    }
}
