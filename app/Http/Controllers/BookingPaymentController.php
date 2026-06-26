<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Booking;
use App\Models\BookingSavedCard;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\CrmSetting;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ClientAppointmentCreatedNotification;
use App\Services\BookingSlotHoldService;
use App\Services\ClientWalletService;
use App\Services\OnlineBookingCheckoutService;
use App\Services\VendusInvoiceEmailService;
use App\Services\VendusInvoiceService;
use App\Support\BookingLocale;
use App\Support\CurrentStore;
use App\Support\StoreBusinessTime;
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
        private VendusInvoiceService $vendusInvoiceService,
        private VendusInvoiceEmailService $vendusInvoiceEmailService,
        private ClientWalletService $walletService,
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
                'message' => __('booking.validation.payment_disabled'),
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
                'message' => __('booking.validation.payment_not_configured'),
            ], 503);
        }

        $currency = (string) config('booking.currency');
        $depositPercent = (int) config('booking.deposit_percent');

        $total = round((float) $resolved['totalPrice'], 2);
        $paidAmount = round($total * ($depositPercent / 100), 2);
        $remaining = round($total - $paidAmount, 2);
        if ($paidAmount <= 0) {
            throw ValidationException::withMessages([
                'services' => [__('booking.validation.deposit_zero')],
            ]);
        }

        $depositCents = (int) round($paidAmount * 100);
        $walletApplyCents = $this->resolveWalletApplyCentsForIntent($request, $depositCents, $storeId);
        $stripeCents = max(0, $depositCents - $walletApplyCents);

        if ($currency === 'eur' && $stripeCents > 0 && $stripeCents < 50) {
            $shortfall = 50 - $stripeCents;
            $actorForWallet = $request->user();
            $clientForWallet = $actorForWallet instanceof User && $actorForWallet->isBookingClient()
                ? $actorForWallet->loadMissing('client')->client
                : null;
            if ($clientForWallet instanceof Client) {
                $extra = min(
                    $shortfall,
                    max(0, $this->walletService->getBalanceCents($clientForWallet) - $walletApplyCents),
                );
                $walletApplyCents += $extra;
                $stripeCents = max(0, $depositCents - $walletApplyCents);
            }
        }

        if ($currency === 'eur' && $stripeCents > 0 && $stripeCents < 50) {
            throw ValidationException::withMessages([
                'services' => [__('booking.validation.card_minimum')],
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
                'wallet_applied_cents' => $walletApplyCents,
                'remaining_amount' => $remaining,
                'deposit_percent_used' => $depositPercent,
                'payment_status' => Booking::PAYMENT_PENDING,
                'request_payload' => $validated,
                'authenticated_booking_user_id' => $authenticatedId,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('booking.validation.payment_start_failed')], 500);
        }

        if ($stripeCents <= 0 && $walletApplyCents > 0) {
            return response()->json([
                'wallet_only' => true,
                'booking_public_id' => $booking->public_id,
                'currency' => strtoupper($currency),
                'total_price' => $total,
                'paid_amount' => $paidAmount,
                'wallet_applied_cents' => $walletApplyCents,
                'remaining_amount' => $remaining,
                'deposit_percent' => $depositPercent,
            ]);
        }

        try {
            $intent = PaymentIntent::create(
                $this->bookingPaymentIntentCreateParams(
                    $stripeCents,
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
                'message' => __('booking.validation.payment_prepare_failed'),
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
            'amount' => round($stripeCents / 100, 2),
            'currency' => (string) config('booking.currency'),
            'status' => Payment::STATUS_PENDING,
        ]);

        $publishable = config('stripe.key');
        if (! is_string($publishable) || $publishable === '') {
            return response()->json(['message' => __('booking.validation.stripe_key_missing')], 503);
        }

        return response()->json([
            'client_secret' => $intent->client_secret,
            'customer_session_client_secret' => $this->createCustomerSessionClientSecret($stripeCustomerId),
            'publishable_key' => $publishable,
            'booking_public_id' => $booking->public_id,
            'currency' => strtoupper($currency),
            'total_price' => $total,
            'paid_amount' => $paidAmount,
            'wallet_applied_cents' => $walletApplyCents,
            'stripe_amount_cents' => $stripeCents,
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
            'wallet_apply' => ['sometimes', 'boolean'],
            'wallet_apply_cents' => ['sometimes', 'integer', 'min:0'],
            'send_invoice_email' => ['sometimes', 'boolean'],
            'want_invoice_with_nif' => ['sometimes', 'boolean'],
            'billing_nif' => ['nullable', 'string', 'max:32'],
            'invoice_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => __('booking.validation.payment_not_configured')], 503);
        }

        $this->configureStripeSdk();

        $createdBookingUser = false;
        $confirmParams = [];
        $event = null;
        $resolvedUserId = null;
        $partialSale = null;

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

                return response()->json(['message' => __('booking.validation.appointment_wrong_store')], 403);
            }

            if ($booking->authenticated_booking_user_id !== null) {
                $uid = $request->user()?->id;
                if ((int) $booking->authenticated_booking_user_id !== (int) $uid) {
                    DB::rollBack();

                    return response()->json(['message' => __('booking.validation.payment_session_invalid')], 403);
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

            $depositCents = (int) round(((float) $booking->paid_amount) * 100);
            $walletAppliedCents = $this->resolveWalletApplyCentsForComplete($request, $booking, $depositCents);
            $expectedStripeCents = max(0, $depositCents - $walletAppliedCents);
            $intent = null;

            if ($expectedStripeCents <= 0 && $walletAppliedCents > 0) {
                // Pagamento integral com carteira (ex.: utilizador voltou a marcar o checkbox após preparar Stripe).
            } else {
                $piId = $request->input('payment_intent_id') ?: $booking->stripe_payment_intent_id;
                if (! is_string($piId) || $piId === '' || $piId !== $booking->stripe_payment_intent_id) {
                    DB::rollBack();

                    return response()->json(['message' => __('booking.validation.payment_id_invalid')], 422);
                }

                try {
                    $intent = PaymentIntent::retrieve($piId);
                } catch (ApiErrorException $e) {
                    DB::rollBack();

                    return response()->json(['message' => __('booking.validation.payment_verify_failed')], 422);
                }

                if ($intent->status !== 'succeeded') {
                    DB::rollBack();
                    $booking->update(['payment_status' => Booking::PAYMENT_FAILED]);
                    $booking->payments()->first()?->update([
                        'status' => Payment::STATUS_FAILED,
                        'failure_message' => 'PaymentIntent status: '.$intent->status,
                    ]);

                    return response()->json([
                        'message' => __('booking.validation.payment_not_completed'),
                    ], 422);
                }

                if ((int) $intent->amount !== $expectedStripeCents) {
                    DB::rollBack();
                    report(new \RuntimeException('Stripe amount mismatch for booking '.$booking->id));

                    return response()->json(['message' => __('booking.validation.payment_amount_mismatch')], 422);
                }
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

            if ($walletAppliedCents > 0) {
                $this->walletService->debit(
                    $client,
                    $walletAppliedCents,
                    ClientWalletTransaction::TYPE_DEBIT_BOOKING_CHECKOUT,
                    ClientWalletService::idempotencyKeyForBookingDebit((int) $booking->id),
                    [
                        'booking_id' => $booking->id,
                        'calendar_event_id' => $event->id,
                        'description' => __('booking.messages.wallet_used_prepayment'),
                        'created_by_type' => ClientWalletTransaction::CREATED_BY_CLIENT,
                        'created_by_user_id' => $actor instanceof User ? $actor->id : null,
                    ],
                );
            }

            $booking->payments()->first()?->update([
                'status' => Payment::STATUS_SUCCEEDED,
                'failure_message' => null,
            ]);
            $partialSale = $this->createPartialSaleForBooking($booking, $resolved, $client, $intent);
            if ($intent instanceof PaymentIntent && $actor instanceof User && $actor->isBookingClient()) {
                $this->syncSavedCardFromIntent($intent, $actor);
            }
            $this->slotHolds->release($holdPublicId, $holdToken, 'booked');

            DB::commit();
        } catch (InsufficientWalletBalanceException $e) {
            DB::rollBack();

            return response()->json(['message' => __('booking.validation.wallet_insufficient')], 422);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json(['message' => __('booking.validation.appointment_not_found')], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['message' => __('booking.validation.appointment_save_after_payment_failed')], 500);
        }

        if ($event !== null && $resolvedUserId !== null) {
            $this->checkout->notifyTechnician($event, $resolvedUserId);
        }
        if ($event !== null && $event->client) {
            $this->notifyClientAppointmentCreated($event->id, $event->client);
        }
        if ($partialSale instanceof Sale) {
            $this->applyBookingCompleteInvoiceOptionsToSale($request, $partialSale);
            $partialSale->refresh();
            $this->syncSaleWithVendus($partialSale);
            $partialSale->refresh();
            $sendInvoice = $this->bookingCompleteSendInvoiceEmailDesired($request, $booking);
            if ($sendInvoice) {
                $this->vendusInvoiceEmailService->trySendToClient($partialSale);
            }
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
                'message' => __('booking.validation.payment_use_checkout'),
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

            return response()->json(['message' => __('booking.validation.appointment_save_failed')], 500);
        }

        if ($event !== null && $resolvedUserId !== null) {
            $this->checkout->notifyTechnician($event, $resolvedUserId);
        }
        if ($event !== null && $event->client) {
            $this->notifyClientAppointmentCreated($event->id, $event->client);
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
            'description' => __('booking.messages.stripe_deposit_description', ['title' => config('app.name')]),
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
    private function createPartialSaleForBooking(Booking $booking, array $resolved, Client $client, ?PaymentIntent $intent): ?Sale
    {
        $eventId = (int) ($booking->calendar_event_id ?? 0);
        if ($eventId <= 0) {
            return null;
        }

        $walletCents = max(0, (int) ($booking->wallet_applied_cents ?? 0));
        $depositCents = (int) round(((float) $booking->paid_amount) * 100);
        $stripePortionCents = max(0, $depositCents - $walletCents);

        // Créditos da carteira (ex.: provenientes de cancelamentos já faturados) não geram
        // fatura de reserva — o sinal fica registado no Booking e no ledger da carteira.
        if ($stripePortionCents <= 0) {
            return null;
        }

        $activeSale = Sale::query()
            ->where('calendar_event_id', $eventId)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->first();
        if ($activeSale) {
            return $activeSale;
        }

        $storeId = (int) ($booking->store_id ?: CalendarEvent::query()->whereKey($eventId)->value('store_id'));
        $now = StoreBusinessTime::nowUtcForStore($storeId);
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'), $storeId);
        $paymentMethod = $intent instanceof PaymentIntent
            ? $this->salePaymentMethodFromIntent($intent)
            : Sale::PAYMENT_OUTRO;
        $total = round((float) $booking->total_price, 2);
        $valorPago = round($stripePortionCents / 100, 2);
        $eventModel = CalendarEvent::query()
            ->with(['eventServiceItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->find($eventId, ['id', 'title']);
        $primaryServiceId = null;
        $firstBookingLine = $resolved['bookingLines'][0] ?? null;
        if (is_array($firstBookingLine) && isset($firstBookingLine['service'])) {
            $primaryServiceId = (int) ($firstBookingLine['service']->id ?? 0);
            if ($primaryServiceId <= 0) {
                $primaryServiceId = null;
            }
        }
        $primaryEventServiceId = (int) ($eventModel?->eventServiceItems?->first()?->id ?? 0);
        if ($primaryEventServiceId <= 0) {
            $primaryEventServiceId = null;
        }

        $validated = $resolved['validated'] ?? [];
        $wantNif = filter_var($validated['want_invoice_with_nif'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $client->refresh();
        $nifDigits = preg_replace('/\D/', '', (string) ($client->nif ?? ''));
        $issueWithoutFiscalId = ! ($wantNif && strlen($nifDigits) === 9);

        $sale = Sale::create([
            'store_id' => $storeId,
            'calendar_event_id' => $eventId,
            'client_id' => $client->id,
            'numero_fatura' => $numeroFatura,
            'data_emissao' => $now->copy()->timezone(StoreBusinessTime::timezoneForStore($storeId))->toDateString(),
            'total' => min($valorPago, $total),
            'gorjeta' => null,
            'desconto' => null,
            'valor_pago' => $valorPago,
            'iva_total' => null,
            'payment_method' => $paymentMethod,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'issue_without_fiscal_id' => $issueWithoutFiscalId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventTitle = trim((string) ($eventModel?->title ?? ''));
        $descricaoReserva = $eventTitle !== ''
            ? __('booking.messages.prepayment_title_with_service', ['title' => $eventTitle])
            : __('booking.messages.prepayment_title');

        $sort = 0;
        SaleItem::create([
            'sale_id' => $sale->id,
            'tipo' => SaleItem::TIPO_SERVICO,
            'calendar_event_service_id' => $primaryEventServiceId,
            'service_id' => $primaryServiceId,
            'extra_id' => null,
            'descricao' => $descricaoReserva,
            'quantidade' => 1,
            'preco_unitario' => $valorPago,
            'subtotal' => $valorPago,
            'desconto' => null,
            'sort_order' => $sort++,
        ]);

        return $sale;
    }

    private function syncSaleWithVendus(Sale $sale): void
    {
        try {
            $result = $this->vendusInvoiceService->syncSale($sale);
            if ($result['ok']) {
                $sale->forceFill([
                    'vendus_sync_status' => 'synced',
                    'vendus_document_id' => $result['document_id'],
                    'vendus_synced_at' => now(),
                    'vendus_sync_error' => null,
                ])->save();

                return;
            }

            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $result['message'],
            ])->save();

            Log::warning('vendus_invoice_sync_failed_booking_reserva', [
                'sale_id' => $sale->id,
                'status' => $result['status'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            $sale->forceFill([
                'vendus_sync_status' => 'error',
                'vendus_sync_error' => $e->getMessage(),
            ])->save();

            Log::error('vendus_invoice_sync_exception_booking_reserva', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
        }
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

    private function resolveWalletApplyCentsForComplete(Request $request, Booking $booking, int $depositCents): int
    {
        $storeId = (int) $booking->store_id;
        $fromRequest = $this->resolveWalletApplyCentsForIntent($request, $depositCents, $storeId);
        if ($fromRequest > 0) {
            $booking->forceFill(['wallet_applied_cents' => $fromRequest])->save();

            return $fromRequest;
        }

        return max(0, (int) ($booking->wallet_applied_cents ?? 0));
    }

    private function resolveWalletApplyCentsForIntent(Request $request, int $depositCents, int $storeId): int
    {
        $requestedCents = max(0, (int) $request->input('wallet_apply_cents', 0));
        $wantsWallet = $request->boolean('wallet_apply') || $requestedCents > 0;
        if ($depositCents <= 0 || ! $wantsWallet) {
            return 0;
        }

        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->isBookingClient()) {
            return 0;
        }

        $client = $actor->loadMissing('client')->client;
        if (! $client instanceof Client || (int) $client->store_id !== $storeId) {
            return 0;
        }

        $balanceCents = $this->walletService->getBalanceCents($client);
        if ($requestedCents <= 0) {
            $requestedCents = $balanceCents;
        }

        return min($requestedCents, $depositCents, $balanceCents);
    }

    private function notifyClientAppointmentCreated(int $eventId, Client $client): void
    {
        if (! $this->clientAllowsEmailBookingUpdates($client)) {
            return;
        }

        $email = $this->resolveClientNotificationRecipientEmail($client->email);
        if (! is_string($email) || trim($email) === '') {
            return;
        }
        try {
            Notification::route('mail', $email)
                ->notify(
                    (new ClientAppointmentCreatedNotification($eventId))
                        ->locale(BookingLocale::emailLocale())
                );
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

    private function clientAllowsEmailBookingUpdates(Client $client): bool
    {
        return (bool) ($client->notify_email_booking_updates ?? true);
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

    private function bookingCompleteSendInvoiceEmailDesired(Request $request, Booking $booking): bool
    {
        if ($request->exists('send_invoice_email')) {
            return $request->boolean('send_invoice_email');
        }

        $payload = is_array($booking->request_payload) ? $booking->request_payload : [];

        return filter_var($payload['send_invoice_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function applyBookingCompleteInvoiceOptionsToSale(Request $request, Sale $partialSale): void
    {
        $partialSale->loadMissing('client');
        $client = $partialSale->client;
        if ($client === null) {
            return;
        }

        if ($request->exists('send_invoice_email') && $request->boolean('send_invoice_email')) {
            $invoiceEmail = strtolower(trim((string) $request->input('invoice_email', '')));
            if ($invoiceEmail !== '' && filter_var($invoiceEmail, FILTER_VALIDATE_EMAIL)) {
                $client->forceFill(['email' => $invoiceEmail])->save();
            }
        }

        if (! $request->exists('want_invoice_with_nif')) {
            return;
        }

        $client->refresh();

        $want = $request->boolean('want_invoice_with_nif');
        $bn = preg_replace('/\D/', '', (string) $request->input('billing_nif', ''));
        if ($bn === '' && $want) {
            $bn = preg_replace('/\D/', '', (string) ($client->nif ?? ''));
        }
        if ($want && strlen($bn) === 9 && trim((string) ($client->nif ?? '')) === '') {
            $client->forceFill(['nif' => $bn])->save();
            $client->refresh();
        }

        $issueWithout = ! ($want && strlen($bn) === 9);
        $partialSale->forceFill(['issue_without_fiscal_id' => $issueWithout])->save();
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
