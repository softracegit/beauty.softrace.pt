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
            $reason = 'Cancelado pelo cliente na área reservada.';
        }

        try {
            $result = $this->cancellationService->cancel($calendarEvent, [
                'cancellation_reason' => $reason,
                'block_if_outside_notice_period' => true,
                'notify_client' => false,
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
            abort(422, 'Esta marcação já não pode ser cancelada.');
        }

        if (! $event->start_at) {
            abort(422, 'Marcação sem data definida.');
        }

        $tz = $this->policyService->businessTimezoneForStore((int) $event->store_id);
        $startLocal = Carbon::instance($event->start_at)->timezone($tz);
        if ($startLocal->lte(now($tz))) {
            abort(422, 'Só pode cancelar marcações futuras.');
        }
    }

    private function successMessage(\App\Services\AppointmentCancellationResult $result): string
    {
        if ($result->alreadyCancelled) {
            return 'Esta marcação já estava cancelada.';
        }

        if ($result->walletCredited && $result->walletCreditAmountCents > 0) {
            $amount = number_format($result->walletCreditAmountCents / 100, 2, ',', ' ');

            return 'Marcação cancelada. O pré-pagamento de '.$amount.' € foi convertido em créditos na sua carteira.';
        }

        if ($result->policy->isWithinNoticePeriod) {
            return 'Marcação cancelada com sucesso.';
        }

        return 'Marcação cancelada. O pré-pagamento online não foi convertido em créditos (fora do prazo de aviso).';
    }
}
