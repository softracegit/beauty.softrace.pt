<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\ClientAppointmentCreatedNotification;
use App\Services\BookingSlotHoldService;
use App\Services\OnlineBookingCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class BookingPaymentController extends Controller
{
    public function __construct(
        private OnlineBookingCheckoutService $checkout,
        private BookingSlotHoldService $slotHolds,
    ) {}

    /**
     * Validates the wizard payload, stores a pending Booking, creates a Stripe PaymentIntent,
     * and returns the client_secret for Stripe.js.
     */
    public function createIntent(Request $request): JsonResponse
    {
        if (! CrmSetting::onlineBookingPaymentRequired()) {
            return response()->json([
                'message' => 'O pagamento online está desactivado. Confirma a marcação sem pagamento.',
            ], 422);
        }

        $validated = $this->checkout->validateBookingRequest($request);
        $slotHoldPublicId = trim((string) $request->input('slot_hold_public_id', ''));
        $slotHoldToken = trim((string) $request->input('slot_hold_token', ''));
        $this->slotHolds->assertCheckoutHold($validated, $slotHoldPublicId, $slotHoldToken, $request->user());
        $validated['slot_hold_public_id'] = $slotHoldPublicId;
        $validated['slot_hold_token'] = $slotHoldToken;
        $resolved = $this->checkout->resolveValidatedBookingPayload($validated);
        $this->checkout->assertPayableBookingState($request->user(), $validated);

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'message' => 'Pagamentos online não estão configurados.',
            ], 503);
        }

        $currency = (string) config('booking.currency');
        $depositPercent = (int) config('booking.deposit_percent');

        $total = round((float) $resolved['totalPrice'], 2);
        $paidAmount = round($total * ($depositPercent / 100), 2);
        $remaining = round($total - $paidAmount, 2);
        if ($paidAmount <= 0) {
            throw ValidationException::withMessages([
                'services' => ['O valor do depósito é zero. Contacta a loja.'],
            ]);
        }

        $amountCents = (int) round($paidAmount * 100);
        if ($currency === 'eur' && $amountCents < 50) {
            throw ValidationException::withMessages([
                'services' => ['O valor mínimo para pagamento com cartão é 0,50 €. Escolhe mais serviços ou contacta a loja.'],
            ]);
        }

        $actor = $request->user();
        $authenticatedId = $actor instanceof User && $actor->isBookingClient() ? $actor->id : null;

        $this->configureStripeSdk();
        $stripeCustomerId = $this->resolveStripeCustomerIdForBookingActor($actor);

        try {
            $booking = Booking::query()->create([
                'public_id' => (string) \Illuminate\Support\Str::ulid(),
                'total_price' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'deposit_percent_used' => $depositPercent,
                'payment_status' => Booking::PAYMENT_PENDING,
                'request_payload' => $validated,
                'authenticated_booking_user_id' => $authenticatedId,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não foi possível iniciar o pagamento. Tenta novamente.'], 500);
        }

        try {
            $intent = PaymentIntent::create(
                $this->bookingPaymentIntentCreateParams(
                    $amountCents,
                    $currency,
                    $booking->public_id,
                    (string) $booking->id,
                    is_string($validated['email'] ?? null) ? $validated['email'] : null,
                    $stripeCustomerId,
                )
            );
        } catch (ApiErrorException $e) {
            Log::warning('Stripe PaymentIntent::create falhou (ainda sem dados de cartão).', [
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);
            $booking->update(['payment_status' => Booking::PAYMENT_FAILED]);
            $booking->delete();

            $payload = [
                'message' => 'Não foi possível preparar o pagamento no servidor (contacto com o Stripe). Isto acontece antes de introduzires o cartão — costuma ser configuração (chaves, versão da API) ou rede. Tenta mais tarde ou contacta a loja.',
            ];
            if (config('app.debug')) {
                $payload['message'] .= ' [Debug] '.$e->getMessage();
                $payload['stripe_code'] = $e->getStripeCode();
            }

            return response()->json($payload, 422);
        }

        $booking->update(['stripe_payment_intent_id' => $intent->id]);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'stripe_payment_intent_id' => $intent->id,
            'amount' => $paidAmount,
            'currency' => (string) config('booking.currency'),
            'status' => Payment::STATUS_PENDING,
        ]);

        $publishable = config('stripe.key');
        if (! is_string($publishable) || $publishable === '') {
            return response()->json(['message' => 'Chave pública Stripe em falta.'], 503);
        }

        return response()->json([
            'client_secret' => $intent->client_secret,
            'publishable_key' => $publishable,
            'booking_public_id' => $booking->public_id,
            'currency' => strtoupper($currency),
            'total_price' => $total,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'deposit_percent' => $depositPercent,
        ]);
    }

    /**
     * After Stripe.js confirms the PaymentIntent, creates the Client/User (if needed) and CalendarEvent.
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'booking_public_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i'],
            'payment_intent_id' => ['nullable', 'string', 'max:255'],
        ]);

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Pagamentos online não estão configurados.'], 503);
        }

        $this->configureStripeSdk();

        $createdBookingUser = false;
        $confirmParams = [];
        $event = null;
        $resolvedUserId = null;

        DB::beginTransaction();
        try {
            /** @var Booking $booking */
            $booking = Booking::query()
                ->where('public_id', $request->string('booking_public_id')->toString())
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->authenticated_booking_user_id !== null) {
                $uid = $request->user()?->id;
                if ((int) $booking->authenticated_booking_user_id !== (int) $uid) {
                    DB::rollBack();

                    return response()->json(['message' => 'Sessão inválida para concluir esta marcação.'], 403);
                }
            }

            if ($booking->calendar_event_id !== null) {
                DB::commit();
                if ($booking->payment_status !== Booking::PAYMENT_PAID) {
                    $booking->update(['payment_status' => Booking::PAYMENT_PAID]);
                }

                return response()->json([
                    'success' => true,
                    'redirect' => route('booking.confirm', $confirmParams),
                ]);
            }

            $piId = $request->input('payment_intent_id') ?: $booking->stripe_payment_intent_id;
            if (! is_string($piId) || $piId === '' || $piId !== $booking->stripe_payment_intent_id) {
                DB::rollBack();

                return response()->json(['message' => 'Identificador de pagamento inválido.'], 422);
            }

            try {
                $intent = PaymentIntent::retrieve($piId);
            } catch (ApiErrorException $e) {
                DB::rollBack();

                return response()->json(['message' => 'Não foi possível verificar o pagamento no Stripe.'], 422);
            }

            if ($intent->status !== 'succeeded') {
                DB::rollBack();
                $booking->update(['payment_status' => Booking::PAYMENT_FAILED]);
                $booking->payments()->first()?->update([
                    'status' => Payment::STATUS_FAILED,
                    'failure_message' => 'PaymentIntent status: '.$intent->status,
                ]);

                return response()->json([
                    'message' => 'O pagamento não foi concluído. Verifica os dados do cartão e tenta novamente.',
                ], 422);
            }

            $expectedCents = (int) round(((float) $booking->paid_amount) * 100);
            if ((int) $intent->amount !== $expectedCents) {
                DB::rollBack();
                report(new \RuntimeException('Stripe amount mismatch for booking '.$booking->id));

                return response()->json(['message' => 'Valor do pagamento não coincide. Contacta a loja.'], 422);
            }

            $validated = $this->checkout->validateStoredPayload($booking->request_payload ?? []);
            $resolved = $this->checkout->resolveValidatedBookingPayload($validated);
            $holdPublicId = trim((string) ($booking->request_payload['slot_hold_public_id'] ?? ''));
            $holdToken = trim((string) ($booking->request_payload['slot_hold_token'] ?? ''));

            $actor = $request->user();
            $clientBundle = $this->checkout->resolveClientForBooking(
                $validated,
                $actor instanceof User ? $actor : null,
            );
            $client = $clientBundle['client'];
            $createdBookingUser = $clientBundle['created_booking_user'];

            $event = $this->checkout->persistMarcacao($resolved, $client);
            $resolvedUserId = $resolved['userId'];

            $booking->update([
                'payment_status' => Booking::PAYMENT_PAID,
                'calendar_event_id' => $event->id,
                'client_id' => $client->id,
            ]);

            $booking->payments()->first()?->update([
                'status' => Payment::STATUS_SUCCEEDED,
                'failure_message' => null,
            ]);
            $this->slotHolds->release($holdPublicId, $holdToken, 'booked');

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json(['message' => 'Marcação não encontrada.'], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['message' => 'Não foi possível guardar a marcação após o pagamento. Contacta a loja.'], 500);
        }

        if ($event !== null && $resolvedUserId !== null) {
            $this->checkout->notifyTechnician($event, $resolvedUserId);
        }
        if ($event !== null && $event->client) {
            $this->notifyClientAppointmentCreated($event->id, $event->client->email);
        }

        if ($createdBookingUser) {
            $confirmParams['primeira_marcacao'] = '1';
        }

        return response()->json([
            'success' => true,
            'redirect' => route('booking.confirm', $confirmParams),
        ]);
    }

    /**
     * Marcação online sem depósito (quando "Pagamento nas marcações online" está desactivado no CRM).
     */
    public function confirmWithoutPayment(Request $request): JsonResponse
    {
        if (CrmSetting::onlineBookingPaymentRequired()) {
            return response()->json([
                'message' => 'O pagamento online está activo. Usa o fluxo normal de checkout.',
            ], 422);
        }

        $validated = $this->checkout->validateBookingRequest($request);
        $slotHoldPublicId = trim((string) $request->input('slot_hold_public_id', ''));
        $slotHoldToken = trim((string) $request->input('slot_hold_token', ''));
        $hold = $this->slotHolds->assertCheckoutHold($validated, $slotHoldPublicId, $slotHoldToken, $request->user());
        $resolved = $this->checkout->resolveValidatedBookingPayload($validated);
        $this->checkout->assertPayableBookingState($request->user(), $validated);

        $createdBookingUser = false;
        $confirmParams = [];
        $event = null;
        $resolvedUserId = null;

        try {
            $actor = $request->user();
            $clientBundle = $this->checkout->resolveClientForBooking(
                $validated,
                $actor instanceof User ? $actor : null,
            );
            $client = $clientBundle['client'];
            $createdBookingUser = $clientBundle['created_booking_user'];

            $event = $this->checkout->persistMarcacao($resolved, $client);
            $resolvedUserId = $resolved['userId'];
            $this->slotHolds->release((string) $hold->public_id, $slotHoldToken, 'booked');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não foi possível guardar a marcação. Tenta novamente ou contacta a loja.'], 500);
        }

        if ($event !== null && $resolvedUserId !== null) {
            $this->checkout->notifyTechnician($event, $resolvedUserId);
        }
        if ($event !== null && $event->client) {
            $this->notifyClientAppointmentCreated($event->id, $event->client->email);
        }

        if ($createdBookingUser) {
            $confirmParams['primeira_marcacao'] = '1';
        }

        return response()->json([
            'success' => true,
            'redirect' => route('booking.confirm', $confirmParams),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPaymentIntentCreateParams(
        int $amountCents,
        string $currency,
        string $bookingPublicId,
        string $bookingId,
        ?string $email,
        ?string $stripeCustomerId,
    ): array {
        $base = [
            'amount' => $amountCents,
            'currency' => $currency,
            'metadata' => [
                'booking_public_id' => $bookingPublicId,
                'booking_id' => $bookingId,
            ],
            'description' => 'Depósito marcação online — '.config('app.name'),
        ];
        if (is_string($email) && trim($email) !== '') {
            $base['receipt_email'] = trim($email);
        }
        if (is_string($stripeCustomerId) && $stripeCustomerId !== '') {
            $base['customer'] = $stripeCustomerId;
        }

        $configured = trim((string) config('stripe.booking_payment_methods'));
        $mode = strtolower($configured);

        if ($configured === '' || $mode === 'auto') {
            $base['automatic_payment_methods'] = ['enabled' => true];
            // Com `setup_future_usage`, o Payment Element esconde métodos como MB WAY.
            // Em modo `auto`, não definir — assim voltam MB WAY / Multibanco se o Dashboard permitir.

            return $base;
        }

        $types = array_values(array_filter(array_map('trim', explode(',', $configured))));
        foreach ($types as $i => $t) {
            $types[$i] = $this->normalizeStripePaymentMethodType(strtolower($t));
        }
        if ($types === []) {
            $types = ['card'];
        }

        $base['payment_method_types'] = $types;

        // Só pedir “guardar para futuro” quando o checkout é explicitamente só cartão.
        if (is_string($stripeCustomerId) && $stripeCustomerId !== ''
            && $types === ['card']) {
            $base['setup_future_usage'] = 'off_session';
        }

        return $base;
    }

    private function normalizeStripePaymentMethodType(string $type): string
    {
        return match ($type) {
            'mbway', 'mb-way' => 'mb_way',
            default => $type,
        };
    }

    private function notifyClientAppointmentCreated(int $eventId, ?string $clientEmail): void
    {
        $email = $this->resolveClientNotificationRecipientEmail($clientEmail);
        if (! is_string($email) || trim($email) === '') {
            return;
        }
        try {
            Notification::route('mail', $email)->notify(new ClientAppointmentCreatedNotification($eventId));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email de marcacao confirmada ao cliente.', [
                'calendar_event_id' => $eventId,
                'email' => $email,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Evita enviar emails de testes para clientes reais.
     * Em produção, mantém o email original; noutros ambientes, redireciona para suporte.
     */
    private function resolveClientNotificationRecipientEmail(?string $originalEmail): string
    {
        $originalEmail = trim((string) ($originalEmail ?? ''));
        $supportEmail = (string) env('MAIL_CLIENT_TEST_REDIRECT_TO', 'suporte@softrace.pt');

        if (app()->environment('production')) {
            return $originalEmail;
        }

        return $supportEmail;
    }

    private function resolveStripeCustomerIdForBookingActor(mixed $actor): ?string
    {
        if (! ($actor instanceof User) || ! $actor->isBookingClient()) {
            return null;
        }

        /** @var Client|null $client */
        $client = $actor->loadMissing('client')->client;
        if (! $client) {
            return null;
        }

        $existingId = trim((string) ($client->stripe_customer_id ?? ''));
        if ($existingId !== '') {
            return $existingId;
        }

        $email = trim((string) ($client->email ?? $actor->email ?? ''));
        $phone = trim((string) ($client->phone ?? ''));
        $name = trim((string) ($client->name ?? $actor->name ?? ''));

        try {
            $customer = Customer::create([
                'name' => $name !== '' ? $name : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'metadata' => [
                    'crm_client_id' => (string) $client->id,
                    'booking_user_id' => (string) $actor->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Customer::create falhou para cliente autenticado no booking.', [
                'user_id' => $actor->id,
                'client_id' => $client->id,
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $client->stripe_customer_id = $customer->id;
        $client->save();

        return $customer->id;
    }

    /**
     * Chave secreta + opcionalmente Stripe-Version (só se definires uma versão válida no .env).
     */
    private function configureStripeSdk(): void
    {
        Stripe::setApiKey((string) config('stripe.secret'));

        $apiVersion = config('stripe.api_version');
        if (! is_string($apiVersion) || $apiVersion === '') {
            return;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}\.[a-zA-Z0-9_]+$/', $apiVersion)) {
            Log::warning('STRIPE_API_VERSION ignorada. Usa o valor exacto do Dashboard (ex.: 2024-11-20.acacia) ou deixa vazio para o SDK escolher.', [
                'value' => $apiVersion,
            ]);

            return;
        }

        Stripe::setApiVersion($apiVersion);
    }
}
