<?php

namespace App\Services;

use App\Exceptions\AgendaDepositException;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Booking;
use App\Models\BookingSavedCard;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\CrmSetting;
use App\Models\ClientWalletTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ApplicableFees;
use App\Support\PaymentMethodCatalog;
use App\Support\PhoneDisplay;
use App\Support\StripeCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class AgendaDepositService
{
    public function __construct(
        private readonly ClientWalletService $walletService,
        private readonly CashRegisterService $cashRegisterService,
        private readonly MarcacaoPaymentActivityLogger $paymentActivityLogger,
    ) {}

    public function depositPercent(): int
    {
        return (int) config('booking.deposit_percent');
    }

    public function subtotalFromEvent(CalendarEvent $calendarEvent): float
    {
        $calendarEvent->loadMissing([
            'eventServiceItems.service.fees',
            'eventServiceItems.extras.extra',
        ]);

        return ApplicableFees::chargeSubtotalForCalendarEvent($calendarEvent, $calendarEvent->eventServiceItems);
    }

    public function bookingPaidAmount(int $calendarEventId): float
    {
        $bookingPaid = (float) Booking::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->value('paid_amount');

        return round(max($bookingPaid, 0.0), 2);
    }

    public function hasActiveReservaSale(int $calendarEventId): bool
    {
        return Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->exists();
    }

    /**
     * @return array{
     *     subtotal: float,
     *     deposit_percent: int,
     *     deposit_amount: float,
     *     booking_paid_amount: float,
     *     has_reserva_sale: bool,
     *     can_collect: bool,
     *     wallet_balance_cents: int|null
     * }
     */
    public function preview(CalendarEvent $calendarEvent, ?float $customAmount = null): array
    {
        $this->assertMarcacaoCollectible($calendarEvent, false);

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        $bookingPaid = $this->bookingPaidAmount((int) $calendarEvent->id);
        $hasReservaSale = $this->hasActiveReservaSale((int) $calendarEvent->id);
        $canCollect = $this->canCollectDeposit($calendarEvent, $depositAmount, $bookingPaid, $hasReservaSale);

        $client = $calendarEvent->client;
        $walletBalanceCents = $client instanceof Client
            ? $this->walletService->getBalanceCents($client)
            : null;

        return [
            'subtotal' => $subtotal,
            'deposit_percent' => $this->depositPercent(),
            'deposit_amount' => $depositAmount,
            'booking_paid_amount' => $bookingPaid,
            'has_reserva_sale' => $hasReservaSale,
            'can_collect' => $canCollect,
            'wallet_balance_cents' => $walletBalanceCents,
        ];
    }

    public function depositAmountForEvent(
        CalendarEvent $calendarEvent,
        ?float $customAmount = null,
        ?float $subtotal = null,
    ): float {
        $subtotal ??= $this->subtotalFromEvent($calendarEvent);
        if ($subtotal <= 0.00001) {
            return 0.0;
        }

        $percent = $this->depositPercent();
        if ($percent > 0) {
            return round($subtotal * ($percent / 100), 2);
        }

        if ($customAmount === null) {
            return 0.0;
        }

        $custom = round(max(0.0, $customAmount), 2);
        if ($custom < 0.01) {
            throw new AgendaDepositException('Indique um valor de adiantamento entre 0,01 € e o total da marcação.');
        }
        if ($custom > $subtotal + 0.00001) {
            throw new AgendaDepositException('O adiantamento não pode exceder o total da marcação.');
        }

        return $custom;
    }

    public function assertCanCollectDeposit(CalendarEvent $calendarEvent, ?float $customAmount = null): void
    {
        $this->assertMarcacaoCollectible($calendarEvent, true);

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        if ($subtotal <= 0.00001) {
            throw new AgendaDepositException('A marcação não tem serviços com valor.');
        }

        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        if ($depositAmount <= 0.00001) {
            throw new AgendaDepositException('Não há valor de pré-pagamento a cobrar. Defina um valor ou configure a percentagem de depósito.');
        }

        $bookingPaid = $this->bookingPaidAmount((int) $calendarEvent->id);
        $hasReservaSale = $this->hasActiveReservaSale((int) $calendarEvent->id);

        if (! $this->canCollectDeposit($calendarEvent, $depositAmount, $bookingPaid, $hasReservaSale)) {
            if ($hasReservaSale) {
                throw new AgendaDepositException('Já existe uma fatura de pré-pagamento para esta marcação.');
            }
            if ($bookingPaid + 0.00001 >= $depositAmount) {
                throw new AgendaDepositException('O pré-pagamento desta marcação já foi pago.');
            }

            throw new AgendaDepositException('Não é possível cobrar o pré-pagamento nesta marcação.');
        }
    }

    /**
     * @param  array{
     *     invoice_fiscal_mode: string,
     *     billing_nif?: string|null,
     *     payment_method?: string,
     *     wallet_apply_cents?: int,
     *     custom_amount?: float|null,
     *     staff_user_id?: int|null
     * }  $options
     */
    public function collectWithCashAndWallet(CalendarEvent $calendarEvent, array $options): AgendaDepositResult
    {
        $customAmount = isset($options['custom_amount']) ? (float) $options['custom_amount'] : null;
        $this->assertCanCollectDeposit($calendarEvent, $customAmount);

        $client = $calendarEvent->client;
        if (! $client instanceof Client) {
            throw new AgendaDepositException('A marcação não tem cliente associado.');
        }

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        $depositCents = (int) round($depositAmount * 100);
        $walletApplyCents = $this->resolveWalletApplyCents(
            $client,
            $depositCents,
            max(0, (int) ($options['wallet_apply_cents'] ?? 0)),
            (bool) ($options['wallet_apply'] ?? false),
        );
        $stripePortionCents = max(0, $depositCents - $walletApplyCents);

        $paymentMethod = (string) ($options['payment_method'] ?? '');
        if ($stripePortionCents > 0) {
            $stripeMbwayActive = PaymentMethodCatalog::isEnabled(Sale::PAYMENT_MBWAY, PaymentMethodCatalog::CHANNEL_AGENDA, (int) $calendarEvent->store_id)
                && StripeCredentials::isReady((int) $calendarEvent->store_id);

            if ($paymentMethod === Sale::PAYMENT_MBWAY && $stripeMbwayActive) {
                throw new AgendaDepositException('MB WAY automático requer o fluxo Stripe. Use o botão MB Way (Stripe) ou MB Way (registo).');
            }

            $allowedManual = [
                Sale::PAYMENT_DINHEIRO,
                Sale::PAYMENT_MBWAY_MANUAL,
                Sale::PAYMENT_TRANSFERENCIA,
            ];
            if (! $stripeMbwayActive) {
                $allowedManual[] = Sale::PAYMENT_MBWAY;
            }

            if (! in_array($paymentMethod, $allowedManual, true)) {
                throw new AgendaDepositException('Para o valor em falta após créditos, use dinheiro, MB Way (registo) ou transferência.');
            }
        } elseif ($walletApplyCents <= 0) {
            throw new AgendaDepositException('Indique créditos da carteira ou um método de pagamento.');
        }

        $fiscal = $this->resolveFiscalOptions($client, (string) ($options['invoice_fiscal_mode'] ?? 'consumer'), $options['billing_nif'] ?? null);

        return $this->persistDeposit(
            $calendarEvent,
            $client,
            $subtotal,
            $depositAmount,
            $depositCents,
            $walletApplyCents,
            $stripePortionCents,
            $stripePortionCents > 0 ? $paymentMethod : null,
            $fiscal,
            (int) ($options['staff_user_id'] ?? 0) ?: null,
            $this->resolveInvoiceStatusFromCheckoutMode($options['checkout_mode'] ?? 'faturar'),
        );
    }

    /**
     * @return array{
     *     payment_intent_id: string,
     *     status: string,
     *     amount: float,
     *     phone: string
     * }
     */
    public function createMbwayIntent(
        CalendarEvent $calendarEvent,
        int $requestedWalletApplyCents,
        ?float $customAmount,
        ?string $mbwayPhone,
        bool $walletApply = false,
    ): array {
        $this->assertCanCollectDeposit($calendarEvent, $customAmount);

        $client = $calendarEvent->client;
        if (! $client instanceof Client) {
            throw new AgendaDepositException('A marcação não tem cliente associado.');
        }

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        $depositCents = (int) round($depositAmount * 100);
        $walletApplyCents = $this->resolveWalletApplyCents(
            $client,
            $depositCents,
            $requestedWalletApplyCents,
            $walletApply || $requestedWalletApplyCents > 0,
        );
        $stripePortionCents = max(0, $depositCents - $walletApplyCents);

        if ($stripePortionCents <= 0) {
            throw new AgendaDepositException('O valor do pré-pagamento está coberto pelos créditos. Confirme o pagamento sem MB WAY.');
        }

        $currency = strtolower((string) config('booking.currency', 'eur'));
        if ($currency === 'eur' && $stripePortionCents < 50) {
            throw new AgendaDepositException('O valor mínimo para MB WAY é 0,50 €.');
        }

        $rawPhone = trim((string) ($client->phone ?? ''));
        if ($rawPhone === '') {
            $rawPhone = trim((string) ($mbwayPhone ?? ''));
        }
        $phoneE164 = PhoneDisplay::toE164($rawPhone);
        if (! is_string($phoneE164) || $phoneE164 === '' || ! preg_match('/^\+3519\d{8}$/', $phoneE164)) {
            throw new AgendaDepositException('O número MB WAY do cliente é inválido. Indique um telemóvel português válido (ex.: +3519XXXXXXXX).');
        }

        if (trim((string) ($client->phone ?? '')) === '') {
            $client->phone = $phoneE164;
            $client->save();
        }

        $this->configureStripeSdk((int) $calendarEvent->store_id);

        try {
            $intent = PaymentIntent::create([
                'amount' => $stripePortionCents,
                'currency' => $currency,
                'payment_method_types' => ['mb_way'],
                'confirm' => true,
                'payment_method_data' => [
                    'type' => 'mb_way',
                    'billing_details' => [
                        'name' => (string) ($client->name ?? ''),
                        'email' => (string) ($client->email ?? ''),
                        'phone' => $phoneE164,
                    ],
                ],
                'description' => 'Pré-pagamento MB WAY (receção) — '.config('app.name'),
                'metadata' => [
                    'agenda_deposit' => '1',
                    'event_id' => (string) $calendarEvent->id,
                    'wallet_apply_cents' => (string) $walletApplyCents,
                    'deposit_cents' => (string) $depositCents,
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw new AgendaDepositException('Não foi possível gerar o pedido MB WAY.');
        }

        return [
            'payment_intent_id' => $intent->id,
            'status' => (string) $intent->status,
            'amount' => round($stripePortionCents / 100, 2),
            'phone' => $phoneE164,
            'wallet_apply_cents' => $walletApplyCents,
            'deposit_amount' => $depositAmount,
        ];
    }

    /**
     * @param  array{
     *     invoice_fiscal_mode: string,
     *     billing_nif?: string|null,
     *     custom_amount?: float|null,
     *     staff_user_id?: int|null
     * }  $options
     */
    public function collectAfterMbwayIntent(
        CalendarEvent $calendarEvent,
        PaymentIntent $intent,
        array $options,
    ): AgendaDepositResult {
        $customAmount = isset($options['custom_amount']) ? (float) $options['custom_amount'] : null;
        $this->assertCanCollectDeposit($calendarEvent, $customAmount);

        if ((string) ($intent->status ?? '') !== 'succeeded') {
            throw new AgendaDepositException('Pagamento MB WAY ainda não confirmado.', 202);
        }

        $metaEventId = (int) ($intent->metadata['event_id'] ?? 0);
        if ($metaEventId > 0 && $metaEventId !== (int) $calendarEvent->id) {
            throw new AgendaDepositException('O pagamento MB WAY não corresponde a esta marcação.');
        }

        $client = $calendarEvent->client;
        if (! $client instanceof Client) {
            throw new AgendaDepositException('A marcação não tem cliente associado.');
        }

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        $depositCents = (int) round($depositAmount * 100);
        $walletApplyCents = max(0, (int) ($intent->metadata['wallet_apply_cents'] ?? 0));
        $walletApplyCents = min($walletApplyCents, $depositCents, $this->walletService->getBalanceCents($client));
        $stripePortionCents = (int) ($intent->amount ?? 0);
        $expectedStripe = max(0, $depositCents - $walletApplyCents);

        if ($stripePortionCents !== $expectedStripe) {
            throw new AgendaDepositException('Valor do pagamento MB WAY não coincide com o pré-pagamento esperado.');
        }

        $fiscal = $this->resolveFiscalOptions($client, (string) ($options['invoice_fiscal_mode'] ?? 'consumer'), $options['billing_nif'] ?? null);

        return $this->persistDeposit(
            $calendarEvent,
            $client,
            $subtotal,
            $depositAmount,
            $depositCents,
            $walletApplyCents,
            $stripePortionCents,
            $this->salePaymentMethodFromIntent($intent),
            $fiscal,
            (int) ($options['staff_user_id'] ?? 0) ?: null,
            $this->resolveInvoiceStatusFromCheckoutMode($options['checkout_mode'] ?? 'faturar'),
        );
    }

    /**
     * @param  array{
     *     invoice_fiscal_mode: string,
     *     billing_nif?: string|null,
     *     saved_card_id?: int|null,
     *     wallet_apply_cents?: int,
     *     custom_amount?: float|null,
     *     staff_user_id?: int|null
     * }  $options
     */
    public function collectWithSavedCard(CalendarEvent $calendarEvent, array $options): AgendaDepositResult
    {
        $customAmount = isset($options['custom_amount']) ? (float) $options['custom_amount'] : null;
        $this->assertCanCollectDeposit($calendarEvent, $customAmount);

        $client = $calendarEvent->client;
        if (! $client instanceof Client) {
            throw new AgendaDepositException('A marcação não tem cliente associado.');
        }

        $customerId = trim((string) ($client->stripe_customer_id ?? ''));
        if ($customerId === '') {
            throw new AgendaDepositException('Este cliente não tem cartão guardado no sistema de pagamentos.');
        }

        $card = $this->resolveSavedCard($client, isset($options['saved_card_id']) ? (int) $options['saved_card_id'] : null);
        if ($card === null) {
            throw new AgendaDepositException('Cartão guardado não encontrado.');
        }

        $subtotal = $this->subtotalFromEvent($calendarEvent);
        $depositAmount = $this->depositAmountForEvent($calendarEvent, $customAmount, $subtotal);
        $depositCents = (int) round($depositAmount * 100);
        $walletApplyCents = $this->resolveWalletApplyCents(
            $client,
            $depositCents,
            max(0, (int) ($options['wallet_apply_cents'] ?? 0)),
            (bool) ($options['wallet_apply'] ?? false),
        );
        $stripePortionCents = max(0, $depositCents - $walletApplyCents);

        if ($stripePortionCents <= 0) {
            throw new AgendaDepositException('O valor do pré-pagamento está coberto pelos créditos. Confirme sem cartão.');
        }

        $currency = strtolower((string) config('booking.currency', 'eur'));
        if ($currency === 'eur' && $stripePortionCents < 50) {
            throw new AgendaDepositException('O valor mínimo para pagamento com cartão é 0,50 €.');
        }

        $this->configureStripeSdk((int) $calendarEvent->store_id);

        try {
            $intent = PaymentIntent::create([
                'amount' => $stripePortionCents,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $card->stripe_payment_method_id,
                'payment_method_types' => ['card'],
                'confirm' => true,
                'off_session' => true,
                'description' => 'Pré-pagamento cartão (receção) — '.config('app.name'),
                'metadata' => [
                    'agenda_deposit' => '1',
                    'event_id' => (string) $calendarEvent->id,
                    'wallet_apply_cents' => (string) $walletApplyCents,
                    'deposit_cents' => (string) $depositCents,
                ],
            ]);
        } catch (ApiErrorException $e) {
            $code = (string) $e->getStripeCode();
            if ($code === 'authentication_required' || str_contains(strtolower($e->getMessage()), 'authentication')) {
                throw new AgendaDepositException('O cartão requer confirmação adicional (3D Secure). Use MB WAY ou dinheiro.');
            }

            throw new AgendaDepositException('Não foi possível cobrar o cartão guardado.');
        }

        if ((string) ($intent->status ?? '') === 'requires_action') {
            throw new AgendaDepositException('O cartão requer confirmação adicional (3D Secure). Use MB WAY ou dinheiro.');
        }

        if ((string) ($intent->status ?? '') !== 'succeeded') {
            throw new AgendaDepositException('O pagamento com cartão não foi concluído.');
        }

        $fiscal = $this->resolveFiscalOptions($client, (string) ($options['invoice_fiscal_mode'] ?? 'consumer'), $options['billing_nif'] ?? null);

        return $this->persistDeposit(
            $calendarEvent,
            $client,
            $subtotal,
            $depositAmount,
            $depositCents,
            $walletApplyCents,
            $stripePortionCents,
            $this->salePaymentMethodFromIntent($intent),
            $fiscal,
            (int) ($options['staff_user_id'] ?? 0) ?: null,
            $this->resolveInvoiceStatusFromCheckoutMode($options['checkout_mode'] ?? 'faturar'),
        );
    }

    private function canCollectDeposit(
        CalendarEvent $calendarEvent,
        float $depositAmount,
        float $bookingPaid,
        bool $hasReservaSale,
    ): bool {
        if ($depositAmount <= 0.00001) {
            return false;
        }
        if (! $calendarEvent->client_id) {
            return false;
        }
        if ($hasReservaSale) {
            return false;
        }
        if ($bookingPaid + 0.00001 >= $depositAmount) {
            return false;
        }
        if ($calendarEvent->isMarcacaoStatusLocked()) {
            return false;
        }

        return ($calendarEvent->event_type ?? '') === CalendarEvent::TYPE_MARCACAO;
    }

    private function assertMarcacaoCollectible(CalendarEvent $calendarEvent, bool $requireClient): void
    {
        if (($calendarEvent->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            throw new AgendaDepositException('Apenas marcações podem ter pré-pagamento cobrado na receção.');
        }
        if ($calendarEvent->isMarcacaoStatusLocked()) {
            throw new AgendaDepositException('Esta marcação não pode ser paga.');
        }
        if ($requireClient && ! $calendarEvent->client_id) {
            throw new AgendaDepositException('A marcação não tem cliente associado.');
        }
    }

    private function resolveWalletApplyCents(
        Client $client,
        int $depositCents,
        int $requestedCents,
        bool $wantsWallet,
    ): int {
        if ($depositCents <= 0 || (! $wantsWallet && $requestedCents <= 0)) {
            return 0;
        }

        $balanceCents = $this->walletService->getBalanceCents($client);
        if ($requestedCents <= 0 && $wantsWallet) {
            $requestedCents = $balanceCents;
        }

        return min(max(0, $requestedCents), $depositCents, $balanceCents);
    }

    /**
     * @return array{issue_without_fiscal_id: bool, billing_nif_applied: bool}
     */
    private function resolveFiscalOptions(Client $client, string $fiscalMode, ?string $billingNif): array
    {
        $billingNifDigits = preg_replace('/\D/', '', (string) ($billingNif ?? ''));
        $clientNif = preg_replace('/\D/', '', (string) ($client->nif ?? ''));

        if ($fiscalMode === 'with_nif') {
            if (strlen($clientNif) !== 9 && strlen($billingNifDigits) !== 9) {
                throw new AgendaDepositException('Para faturar com NIF, indique 9 dígitos na ficha do cliente ou no campo «NIF nesta fatura».');
            }
            if (strlen($clientNif) !== 9 && strlen($billingNifDigits) === 9) {
                $client->nif = $billingNifDigits;
                $client->save();
                $clientNif = $billingNifDigits;
            }

            return [
                'issue_without_fiscal_id' => false,
                'billing_nif_applied' => true,
            ];
        }

        return [
            'issue_without_fiscal_id' => true,
            'billing_nif_applied' => false,
        ];
    }

    /**
     * @param  array{issue_without_fiscal_id: bool}  $fiscal
     */
    private function persistDeposit(
        CalendarEvent $calendarEvent,
        Client $client,
        float $subtotal,
        float $depositAmount,
        int $depositCents,
        int $walletApplyCents,
        int $stripePortionCents,
        ?string $salePaymentMethod,
        array $fiscal,
        ?int $staffUserId,
        string $invoiceStatus = Sale::INVOICE_STATUS_FATURADO,
    ): AgendaDepositResult {
        try {
            return DB::transaction(function () use (
                $calendarEvent,
                $client,
                $subtotal,
                $depositAmount,
                $depositCents,
                $walletApplyCents,
                $stripePortionCents,
                $salePaymentMethod,
                $fiscal,
                $staffUserId,
                $invoiceStatus,
            ): AgendaDepositResult {
                $lockedEvent = CalendarEvent::query()
                    ->whereKey($calendarEvent->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertCanCollectDeposit($lockedEvent, $this->depositPercent() > 0 ? null : $depositAmount);

                $booking = $this->ensureBookingRecord(
                    $lockedEvent,
                    $client,
                    $subtotal,
                    $depositAmount,
                    $walletApplyCents,
                );

                if ($walletApplyCents > 0) {
                    $this->walletService->debit(
                        $client,
                        $walletApplyCents,
                        ClientWalletTransaction::TYPE_DEBIT_BOOKING_CHECKOUT,
                        ClientWalletService::idempotencyKeyForAgendaDeposit((int) $lockedEvent->id),
                        [
                            'booking_id' => $booking->id,
                            'calendar_event_id' => $lockedEvent->id,
                            'description' => 'Utilizado no pré-pagamento (receção)',
                            'created_by_type' => ClientWalletTransaction::CREATED_BY_STAFF,
                            'created_by_user_id' => $staffUserId,
                        ],
                    );
                }

                $sale = $this->createReservaSale(
                    $lockedEvent,
                    $client,
                    $booking,
                    $subtotal,
                    $stripePortionCents,
                    $salePaymentMethod,
                    (bool) $fiscal['issue_without_fiscal_id'],
                    $invoiceStatus,
                );

                $this->markBookingPaid($booking, $depositAmount, $walletApplyCents, $subtotal);

                $result = new AgendaDepositResult(
                    $booking->fresh(),
                    $sale,
                    $depositAmount,
                    $walletApplyCents,
                    $stripePortionCents,
                );

                $causer = $this->paymentActivityLogger->resolveCauser($staffUserId);
                $this->paymentActivityLogger->logPrePagamentoRecebido(
                    $lockedEvent,
                    $depositAmount,
                    $sale,
                    $causer,
                );

                return $result;
            });
        } catch (InsufficientWalletBalanceException) {
            throw new AgendaDepositException('Saldo de créditos insuficiente.');
        }
    }

    private function ensureBookingRecord(
        CalendarEvent $calendarEvent,
        Client $client,
        float $subtotal,
        float $depositAmount,
        int $walletAppliedCents,
    ): Booking {
        $depositPercent = $this->depositPercent();
        $remaining = round(max(0, $subtotal - $depositAmount), 2);

        $booking = Booking::query()
            ->where('calendar_event_id', $calendarEvent->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $attrs = [
            'store_id' => (int) $calendarEvent->store_id,
            'calendar_event_id' => $calendarEvent->id,
            'client_id' => $client->id,
            'total_price' => $subtotal,
            'paid_amount' => $depositAmount,
            'wallet_applied_cents' => $walletAppliedCents,
            'remaining_amount' => $remaining,
            'deposit_percent_used' => $depositPercent,
            'payment_status' => Booking::PAYMENT_PENDING,
        ];

        if ($booking instanceof Booking) {
            $booking->fill($attrs);
            $booking->save();

            return $booking;
        }

        return Booking::query()->create(array_merge($attrs, [
            'public_id' => (string) Str::ulid(),
            'request_payload' => [
                'source' => 'agenda_reception',
                'calendar_event_id' => (int) $calendarEvent->id,
            ],
        ]));
    }

    private function markBookingPaid(
        Booking $booking,
        float $depositAmount,
        int $walletAppliedCents,
        float $subtotal,
    ): void {
        $booking->update([
            'payment_status' => Booking::PAYMENT_PAID,
            'paid_amount' => $depositAmount,
            'wallet_applied_cents' => $walletAppliedCents,
            'remaining_amount' => round(max(0, $subtotal - $depositAmount), 2),
        ]);
    }

    private function createReservaSale(
        CalendarEvent $calendarEvent,
        Client $client,
        Booking $booking,
        float $subtotal,
        int $stripePortionCents,
        ?string $paymentMethod,
        bool $issueWithoutFiscalId,
        string $invoiceStatus = Sale::INVOICE_STATUS_FATURADO,
    ): ?Sale {
        if ($stripePortionCents <= 0 || $paymentMethod === null) {
            return null;
        }

        $eventId = (int) $calendarEvent->id;
        $activeSale = Sale::query()
            ->where('calendar_event_id', $eventId)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->lockForUpdate()
            ->first();
        if ($activeSale) {
            return $activeSale;
        }

        $now = now();
        $storeId = (int) $calendarEvent->store_id;
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'), $storeId);
        $valorPago = round($stripePortionCents / 100, 2);

        $calendarEvent->loadMissing([
            'eventServiceItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'eventServiceItems.service',
        ]);
        $primaryEventServiceId = (int) ($calendarEvent->eventServiceItems->first()?->id ?? 0);
        if ($primaryEventServiceId <= 0) {
            $primaryEventServiceId = null;
        }
        $primaryServiceId = (int) ($calendarEvent->eventServiceItems->first()?->service_id ?? 0);
        if ($primaryServiceId <= 0) {
            $primaryServiceId = null;
        }

        $eventTitle = trim((string) ($calendarEvent->title ?? ''));
        $descricaoReserva = $eventTitle !== ''
            ? $eventTitle.' — pré-pagamento (receção)'
            : 'Pré-pagamento (receção)';

        $sale = Sale::create([
            'store_id' => $storeId,
            'cash_register_session_id' => $this->cashRegisterService->sessionIdForNewStoreSale($storeId),
            'calendar_event_id' => $eventId,
            'client_id' => $client->id,
            'numero_fatura' => $numeroFatura,
            'data_emissao' => $now->toDateString(),
            'total' => min($valorPago, round($subtotal, 2)),
            'gorjeta' => null,
            'desconto' => null,
            'valor_pago' => $valorPago,
            'iva_total' => null,
            'payment_method' => $paymentMethod,
            'scope' => Sale::SCOPE_BOOKING_RESERVA,
            'status' => Sale::STATUS_PAGO,
            'invoice_status' => $invoiceStatus,
            'issue_without_fiscal_id' => $issueWithoutFiscalId,
        ]);

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
            'sort_order' => 0,
        ]);

        return $sale;
    }

    private function resolveSavedCard(Client $client, ?int $savedCardId): ?BookingSavedCard
    {
        $query = BookingSavedCard::query()
            ->where('client_id', $client->id)
            ->whereNull('detached_at');

        if ($savedCardId !== null && $savedCardId > 0) {
            return $query->whereKey($savedCardId)->first();
        }

        return $query->where('is_default', true)->orderByDesc('updated_at')->first()
            ?? $query->orderByDesc('updated_at')->first();
    }

    private function resolveInvoiceStatusFromCheckoutMode(?string $checkoutMode): string
    {
        return ($checkoutMode ?? 'faturar') === 'rascunho'
            ? Sale::INVOICE_STATUS_RASCUNHO
            : Sale::INVOICE_STATUS_FATURADO;
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

    private function configureStripeSdk(?int $storeId = null): void
    {
        try {
            StripeCredentials::configureSdk($storeId);
        } catch (\RuntimeException $e) {
            throw new AgendaDepositException($e->getMessage() !== '' ? $e->getMessage() : 'Stripe não configurado.', 503);
        }
    }
}
