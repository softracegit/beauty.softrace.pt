<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSavedCard;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ClientAppointmentCreatedNotification;
use App\Services\BookingSlotHoldService;
use App\Services\OnlineBookingCheckoutService;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Stripe\Customer;
use Stripe\CustomerSession;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
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
        $validated = $this->checkout->validateBookingRequest($request);
        $this->checkout->assertPublicBookingServicesBelongToUrlStore($validated['services'] ?? [], $request);
        $storeId = $this->checkout->storeIdFromBookingServices($validated['services']);

        if (! CrmSetting::onlineBookingPaymentRequired($storeId)) {
            return response()->json([
                'message' => 'O pagamento online está desactivado. Confirma a marcação sem pagamento.',
            ], 422);
        }
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
                'store_id' => $storeId,
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
            'customer_session_client_secret' => $this->createCustomerSessionClientSecret($stripeCustomerId),
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

            $routeStore = $request->route('store');
            if ($routeStore instanceof Store && (int) $booking->store_id !== (int) $routeStore->id) {
                DB::rollBack();

                return response()->json(['message' => 'Esta marcação não pertence a esta loja.'], 403);
            }

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
                    'redirect' => $this->bookingSuccessRedirect(
                        $request,
                        (int) ($booking->client_id ?? 0) ?: null,
                        $confirmParams,
                    ),
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
            $this->checkout->assertPublicBookingServicesBelongToUrlStore($validated['services'] ?? [], $request);
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
            $this->createPartialSaleForBooking($booking, $resolved, $client, $intent);
            if ($actor instanceof User && $actor->isBookingClient()) {
                $this->syncSavedCardFromIntent($intent, $actor);
            }
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
            'redirect' => $this->bookingSuccessRedirect(
                $request,
                (int) ($booking->client_id ?? 0) ?: null,
                $confirmParams,
            ),
        ]);
    }

    /**
     * Marcação online sem depósito (quando "Pagamento nas marcações online" está desactivado no CRM).
     */
    public function confirmWithoutPayment(Request $request): JsonResponse
    {
        $validated = $this->checkout->validateBookingRequest($request);
        $this->checkout->assertPublicBookingServicesBelongToUrlStore($validated['services'] ?? [], $request);
        $storeId = $this->checkout->storeIdFromBookingServices($validated['services']);

        if (CrmSetting::onlineBookingPaymentRequired($storeId)) {
            return response()->json([
                'message' => 'O pagamento online está activo. Usa o fluxo normal de checkout.',
            ], 422);
        }
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
            'redirect' => $this->bookingSuccessRedirect(
                $request,
                (int) $client->id,
                $confirmParams,
            ),
        ]);
    }

    /**
     * @param  array<string, string>  $confirmParams
     */
    private function bookingSuccessRedirect(Request $request, ?int $clientId, array $confirmParams): string
    {
        $actor = $request->user();
        if ($actor instanceof User && $actor->isBookingClient()) {
            return $this->bookingMarcacoesConfirmedUrl($confirmParams);
        }

        if ($actor === null && $clientId !== null && $clientId > 0) {
            $bookingUser = User::query()
                ->where('role', User::ROLE_CLIENTE)
                ->where('client_id', $clientId)
                ->orderByDesc('id')
                ->first();
            if ($bookingUser !== null) {
                Auth::login($bookingUser, true);
                $request->session()->regenerate();

                return $this->bookingMarcacoesConfirmedUrl($confirmParams);
            }
        }

        return route('booking.confirm', array_merge(['store' => $this->bookingStoreSlug()], $confirmParams));
    }

    /**
     * @param  array<string, string>  $confirmParams
     */
    private function bookingMarcacoesConfirmedUrl(array $confirmParams): string
    {
        $query = ['marcacao_confirmada' => '1'];
        if (($confirmParams['primeira_marcacao'] ?? '') === '1') {
            $query['primeira_marcacao'] = '1';
        }

        return route('booking.conta.marcacoes', array_merge(['store' => $this->bookingStoreSlug()], $query));
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

    private function syncSavedCardFromIntent(PaymentIntent $intent, User $actor): void
    {
        $customerId = is_string($intent->customer ?? null) ? $intent->customer : '';
        $paymentMethodId = is_string($intent->payment_method ?? null) ? $intent->payment_method : '';
        if ($customerId === '' || $paymentMethodId === '') {
            return;
        }

        /** @var Client|null $client */
        $client = $actor->loadMissing('client')->client;
        if (! $client || trim((string) ($client->stripe_customer_id ?? '')) !== $customerId) {
            return;
        }

        try {
            $method = PaymentMethod::retrieve($paymentMethodId);
            try {
                PaymentMethod::update($paymentMethodId, [
                    'allow_redisplay' => 'always',
                ]);
                $method = PaymentMethod::retrieve($paymentMethodId);
            } catch (\Throwable) {
                // Campo opcional conforme versão/conta; não bloquear marcação.
            }
        } catch (ApiErrorException) {
            return;
        }
        if (($method->type ?? null) !== 'card' || ! isset($method->card)) {
            return;
        }

        DB::transaction(function () use ($client, $customerId, $method): void {
            $row = BookingSavedCard::query()->updateOrCreate(
                ['stripe_payment_method_id' => (string) $method->id],
                [
                    'client_id' => $client->id,
                    'stripe_customer_id' => $customerId,
                    'brand' => (string) ($method->card->brand ?? ''),
                    'last4' => (string) ($method->card->last4 ?? ''),
                    'exp_month' => (int) ($method->card->exp_month ?? 0) ?: null,
                    'exp_year' => (int) ($method->card->exp_year ?? 0) ?: null,
                    'fingerprint' => (string) ($method->card->fingerprint ?? ''),
                    'detached_at' => null,
                ]
            );
            $hasDefault = BookingSavedCard::query()
                ->where('client_id', $client->id)
                ->whereNull('detached_at')
                ->where('is_default', true)
                ->exists();
            if (! $hasDefault) {
                BookingSavedCard::query()
                    ->where('client_id', $client->id)
                    ->whereNull('detached_at')
                    ->update(['is_default' => false]);
                $row->update(['is_default' => true]);
            }
        });
    }

    private function createCustomerSessionClientSecret(?string $stripeCustomerId): ?string
    {
        if (! is_string($stripeCustomerId) || trim($stripeCustomerId) === '') {
            return null;
        }

        try {
            $session = CustomerSession::create([
                'customer' => $stripeCustomerId,
                'components' => [
                    'payment_element' => [
                        'enabled' => true,
                        'features' => [
                            'payment_method_redisplay' => 'enabled',
                            'payment_method_allow_redisplay_filters' => ['always', 'limited', 'unspecified'],
                            'payment_method_save' => 'enabled',
                            'payment_method_save_usage' => 'off_session',
                            'payment_method_remove' => 'enabled',
                        ],
                    ],
                ],
            ]);

            return is_string($session->client_secret ?? null) ? $session->client_secret : null;
        } catch (\Throwable $e) {
            Log::warning('Stripe CustomerSession::create falhou no checkout booking.', [
                'customer_id' => $stripeCustomerId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cria venda parcial (adiantamento) no momento do depósito online.
     *
     * @param  array{
     *     bookingLines: list<array{
     *         service: \App\Models\Service,
     *         option: \App\Models\ServiceOption|null,
     *         duration: int,
     *         price: float,
     *         original_price: float,
     *         display_name: string
     *     }>
     * }  $resolved
     */
    private function createPartialSaleForBooking(Booking $booking, array $resolved, Client $client, PaymentIntent $intent): void
    {
        $eventId = (int) ($booking->calendar_event_id ?? 0);
        if ($eventId <= 0) {
            return;
        }

        $activeSale = Sale::query()
            ->where('calendar_event_id', $eventId)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->first();
        if ($activeSale) {
            return;
        }

        $now = now();
        $storeId = (int) ($booking->store_id ?: CalendarEvent::query()->whereKey($eventId)->value('store_id'));
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'), $storeId);
        $paymentMethod = $this->salePaymentMethodFromIntent($intent);
        $total = round((float) $booking->total_price, 2);
        $valorPago = round((float) $booking->paid_amount, 2);

        $sale = Sale::create([
            'store_id' => $storeId,
            'calendar_event_id' => $eventId,
            'client_id' => $client->id,
            'numero_fatura' => $numeroFatura,
            'data_emissao' => $now->toDateString(),
            'total' => min($valorPago, $total),
            'gorjeta' => null,
            'desconto' => null,
            'valor_pago' => min($valorPago, $total),
            'iva_total' => null,
            'payment_method' => $paymentMethod,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
        ]);

        $sort = 0;
        SaleItem::create([
            'sale_id' => $sale->id,
            'tipo' => SaleItem::TIPO_SERVICO,
            'calendar_event_service_id' => null,
            'service_id' => null,
            'extra_id' => null,
            'descricao' => 'Adiantamento de reserva (marcação online)',
            'quantidade' => 1,
            'preco_unitario' => min($valorPago, $total),
            'subtotal' => min($valorPago, $total),
            'desconto' => null,
            'sort_order' => $sort++,
        ]);
    }

    private function salePaymentMethodFromIntent(PaymentIntent $intent): string
    {
        $types = $intent->payment_method_types ?? [];
        $first = is_array($types) && isset($types[0]) ? strtolower((string) $types[0]) : '';

        return match ($first) {
            'card' => Sale::PAYMENT_CARTAO,
            'mb_way' => Sale::PAYMENT_MBWAY,
            'multibanco' => Sale::PAYMENT_MULTIBANCO,
            default => Sale::PAYMENT_OUTRO,
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

    private function bookingStoreSlug(): string
    {
        return (string) app(CurrentStore::class)->get()->slug;
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
