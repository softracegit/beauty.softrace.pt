<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AppointmentCancellationException;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\CrmSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\AppointmentCancellationService;
use App\Services\BookingSlotHoldService;
use App\Services\CancellationPolicyService;
use App\Services\OnlineBookingCheckoutService;
use App\Support\Api\ClientApiPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Criar e cancelar marcações a partir da app.
 *
 * Criar usa exatamente o fluxo do booking web sem pagamento
 * (reserva temporária do horário + BookingPaymentController::confirmWithoutPayment):
 * mesmas validações, preços, escolha de técnica, emails e avisos à equipa.
 */
class ClientBookingController extends Controller
{
    public function __construct(
        private readonly OnlineBookingCheckoutService $checkout,
        private readonly BookingSlotHoldService $slotHolds,
        private readonly CancellationPolicyService $policies,
    ) {}

    /**
     * POST /api/v1/stores/{store}/bookings
     * { date, time, agent_id: "any"|id, notes?, services: [{ id, service_option_id?, extras?: [{ extra_id }] }] }
     * Adicionais (Sim) vão como linhas próprias { id, service_option_id: <adicional> }, como no booking web.
     */
    public function store(Request $request, Store $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = $user->loadMissing('client')->client;
        abort_unless($client instanceof Client, 404);

        if (CrmSetting::onlineBookingPaymentRequired((int) $store->id)) {
            return response()->json([
                'message' => 'Esta loja pede pagamento antecipado. Conclua a marcação no site de marcações.',
                'web_url' => route('booking.index', ['store' => $store->slug]),
            ], 422);
        }

        $email = trim((string) ($client->email ?: $user->email));
        $phone = trim((string) $client->phone);
        if ($email === '' || $phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['Complete o email e o telemóvel da sua conta antes de marcar.'],
            ]);
        }

        $payload = [
            'name' => (string) $client->name,
            'email' => $email,
            'phone' => $phone,
            'notes' => $request->input('notes'),
            'date' => $request->input('date'),
            'time' => $request->input('time'),
            'agent_id' => (string) ($request->input('agent_id') ?? 'any'),
            'services' => $request->input('services'),
        ];

        // 1) Reserva temporária do horário (deteta horário já ocupado).
        $holdValidated = Validator::make($payload, $this->checkout->slotHoldRules())->validate();
        $this->checkout->assertBookingServicesActiveInStore($holdValidated['services'], $store);
        $holdToken = 'app_'.Str::random(40);
        $hold = $this->slotHolds->acquire($holdValidated, $holdToken, $user);

        // 2) Confirmação pelo mesmo código do booking web.
        $confirmRequest = Request::create('/', 'POST', $payload + [
            'slot_hold_public_id' => (string) $hold->public_id,
            'slot_hold_token' => $holdToken,
        ]);
        $confirmRequest->setUserResolver(fn () => $user);
        $confirmRequest->headers->set('Accept', 'application/json');

        $startedAt = now()->subSecond();
        try {
            $response = app(BookingPaymentController::class)->confirmWithoutPayment($confirmRequest);
        } catch (\Throwable $e) {
            $this->slotHolds->release((string) $hold->public_id, $holdToken, 'api_failed');
            throw $e;
        }

        if ($response->getStatusCode() >= 400) {
            $this->slotHolds->release((string) $hold->public_id, $holdToken, 'api_failed');

            return response()->json($response->getData(true), $response->getStatusCode());
        }

        $event = CalendarEvent::query()
            ->where('store_id', $store->id)
            ->where('client_id', $client->id)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('created_at', '>=', $startedAt)
            ->latest('id')
            ->first();

        if (! $event instanceof CalendarEvent) {
            return response()->json(['message' => __('booking.validation.appointment_save_failed')], 500);
        }

        return response()->json(['data' => $this->present($event)], 201);
    }

    /** POST /api/v1/stores/{store}/bookings/{booking}/cancel  { reason? } */
    public function cancel(
        Request $request,
        Store $store,
        int $booking,
        AppointmentCancellationService $cancellations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $client = $user->loadMissing('client')->client;
        abort_unless($client instanceof Client, 404);

        // Sem route model binding: o scope de loja do CalendarEvent só fica ativo depois do
        // middleware da loja. Loja e cliente são verificados logo a seguir.
        $calendarEvent = CalendarEvent::query()->withoutGlobalScopes()->find($booking);
        if (! $calendarEvent instanceof CalendarEvent) {
            return response()->json(['message' => 'Marcação não encontrada.'], 404);
        }

        // Mesmas verificações do BookingAccountCancelController (booking web).
        if ((int) $calendarEvent->store_id !== (int) $store->id
            || (int) $calendarEvent->client_id !== (int) $client->id
            || ($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO
        ) {
            return response()->json(['message' => 'Marcação não encontrada.'], 404);
        }
        $this->assertCancellable($calendarEvent);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            $reason = __('booking.messages.cancel_default_reason_account');
        }

        try {
            $cancellations->cancel($calendarEvent, [
                'cancellation_reason' => $reason,
                'block_if_outside_notice_period' => true,
                'notify_client' => true,
                'notify_team' => true,
                'previous_status' => (string) ($calendarEvent->status ?? CalendarEvent::STATUS_AGENDADO),
                'from_public_booking' => true,
                'created_by_type' => ClientWalletTransaction::CREATED_BY_CLIENT,
            ]);
        } catch (AppointmentCancellationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($calendarEvent->fresh())]);
    }

    private function assertCancellable(CalendarEvent $event): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['cancel' => [$message]]);

        if ($event->isMarcacaoStatusLocked()) {
            $fail(__('booking.validation.cancel_not_allowed'));
        }
        if (($event->status ?? '') === CalendarEvent::STATUS_COMPLETO) {
            $fail(__('booking.validation.cancel_paid_not_allowed'));
        }
        if (! $event->start_at) {
            $fail(__('booking.validation.cancel_no_date'));
        }
        $tz = $this->policies->businessTimezoneForStore((int) $event->store_id);
        if (Carbon::instance($event->start_at)->timezone($tz)->lte(now($tz))) {
            $fail(__('booking.validation.cancel_past_only_future'));
        }
        $policy = $this->policies->resolveForEvent($event);
        if (! $policy->canCancelOnline()) {
            $fail(__('booking.validation.cancel_too_late_contact_store', ['deadline' => $policy->deadlineFormatted()]));
        }
    }

    /** @return array<string, mixed> */
    private function present(CalendarEvent $event): array
    {
        $event->load([
            'user:id,name',
            'eventServiceItems.service:id,name,category_id',
            'eventServiceItems.service.category:id,name',
            'eventServiceItems.serviceOption:id,name',
            'eventServiceItems.extras.extra:id,name',
        ]);

        return ClientApiPresenter::booking($event, $this->policies);
    }
}
