<?php

namespace App\Http\Controllers;

use App\Models\BookingSavedCard;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;

class BookingSavedCardController extends Controller
{
    public function createSetupIntent(Request $request): JsonResponse
    {
        $customerId = $this->resolveStripeCustomerIdForBookingActor($request->user());
        if (! is_string($customerId) || $customerId === '') {
            return response()->json(['message' => 'Não foi possível preparar o cartão para esta conta.'], 422);
        }

        $this->configureStripeSdk();

        try {
            $intent = SetupIntent::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'scope' => 'booking_saved_cards',
                    'booking_user_id' => (string) $request->user()->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe SetupIntent::create falhou no wallet de cartões.', [
                'user_id' => $request->user()->id,
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Não foi possível preparar a validação do cartão.'], 422);
        }

        $publishable = config('stripe.key');
        if (! is_string($publishable) || $publishable === '') {
            return response()->json(['message' => 'Chave pública Stripe em falta.'], 503);
        }

        return response()->json([
            'client_secret' => $intent->client_secret,
            'publishable_key' => $publishable,
        ]);
    }

    public function syncAfterSetupIntent(Request $request): JsonResponse
    {
        $request->validate([
            'setup_intent_id' => ['required', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        if (! ($actor instanceof User) || ! $actor->isBookingClient()) {
            return response()->json(['message' => 'Sessão inválida para gerir cartões.'], 403);
        }

        $client = $actor->loadMissing('client')->client;
        if (! $client instanceof Client) {
            return response()->json(['message' => 'Conta sem cliente associado.'], 422);
        }

        $this->configureStripeSdk();

        try {
            $intent = SetupIntent::retrieve((string) $request->string('setup_intent_id'));
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Não foi possível verificar o cartão.'], 422);
        }

        if ($intent->status !== 'succeeded') {
            return response()->json(['message' => 'A validação do cartão não foi concluída.'], 422);
        }

        $paymentMethodId = is_string($intent->payment_method) ? $intent->payment_method : null;
        if (! $paymentMethodId) {
            return response()->json(['message' => 'Cartão inválido.'], 422);
        }

        try {
            $method = PaymentMethod::retrieve($paymentMethodId);
            try {
                PaymentMethod::update($paymentMethodId, [
                    'allow_redisplay' => 'always',
                ]);
                $method = PaymentMethod::retrieve($paymentMethodId);
            } catch (\Throwable) {
                // Campo pode não existir em versões antigas/contas antigas; segue sem bloquear.
            }
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Não foi possível obter os dados do cartão.'], 422);
        }

        if (($method->type ?? null) !== 'card' || ! isset($method->card)) {
            return response()->json(['message' => 'O método guardado não é um cartão.'], 422);
        }

        $customerId = trim((string) ($client->stripe_customer_id ?? ''));
        $pmCustomer = is_string($method->customer) ? $method->customer : '';
        if ($customerId === '' || $pmCustomer === '' || $pmCustomer !== $customerId) {
            return response()->json(['message' => 'O cartão não está associado a esta conta.'], 422);
        }

        DB::transaction(function () use ($client, $method, $customerId): void {
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
                $this->setDefaultCardForClient($client, $row->stripe_payment_method_id);
            }
        });

        return response()->json([
            'success' => true,
            'cards' => $this->cardsPayloadForClient($client->fresh()),
        ]);
    }

    public function makeDefault(Request $request, BookingSavedCard $card): JsonResponse
    {
        $client = $this->resolveClientFromBookingActor($request->user());
        if (! $client) {
            return response()->json(['message' => 'Sessão inválida para gerir cartões.'], 403);
        }

        if ((int) $card->client_id !== (int) $client->id || $card->detached_at !== null) {
            return response()->json(['message' => 'Cartão não encontrado.'], 404);
        }

        try {
            $this->setDefaultCardForClient($client, $card->stripe_payment_method_id);
        } catch (ApiErrorException) {
            return response()->json(['message' => 'Não foi possível definir o cartão principal.'], 422);
        }

        return response()->json([
            'success' => true,
            'cards' => $this->cardsPayloadForClient($client->fresh()),
        ]);
    }

    public function destroy(Request $request, BookingSavedCard $card): JsonResponse
    {
        $client = $this->resolveClientFromBookingActor($request->user());
        if (! $client) {
            return response()->json(['message' => 'Sessão inválida para gerir cartões.'], 403);
        }
        if ((int) $card->client_id !== (int) $client->id || $card->detached_at !== null) {
            return response()->json(['message' => 'Cartão não encontrado.'], 404);
        }

        $this->configureStripeSdk();
        try {
            $stripePaymentMethod = PaymentMethod::retrieve((string) $card->stripe_payment_method_id);
            $stripePaymentMethod->detach();
        } catch (ApiErrorException $e) {
            Log::warning('Falha ao remover cartão Stripe do customer.', [
                'client_id' => $client->id,
                'payment_method_id' => $card->stripe_payment_method_id,
                'stripe_code' => $e->getStripeCode(),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Não foi possível remover o cartão.'], 422);
        }

        DB::transaction(function () use ($client, $card): void {
            $wasDefault = $card->is_default;
            $card->update([
                'is_default' => false,
                'detached_at' => now(),
            ]);

            if ($wasDefault) {
                $next = BookingSavedCard::query()
                    ->where('client_id', $client->id)
                    ->whereNull('detached_at')
                    ->orderByDesc('updated_at')
                    ->first();
                if ($next) {
                    try {
                        $this->setDefaultCardForClient($client, $next->stripe_payment_method_id);
                    } catch (ApiErrorException) {
                        // Mantém sem default em caso de falha remota; utilizador pode voltar a escolher.
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'cards' => $this->cardsPayloadForClient($client->fresh()),
        ]);
    }

    private function setDefaultCardForClient(Client $client, string $stripePaymentMethodId): void
    {
        $this->configureStripeSdk();
        Customer::update((string) $client->stripe_customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $stripePaymentMethodId,
            ],
        ]);

        BookingSavedCard::query()
            ->where('client_id', $client->id)
            ->whereNull('detached_at')
            ->update(['is_default' => false]);

        BookingSavedCard::query()
            ->where('client_id', $client->id)
            ->where('stripe_payment_method_id', $stripePaymentMethodId)
            ->whereNull('detached_at')
            ->update(['is_default' => true]);
    }

    /**
     * @return array<int, array{id: int, brand: string, last4: string, exp_month: int|null, exp_year: int|null, is_default: bool}>
     */
    private function cardsPayloadForClient(Client $client): array
    {
        return BookingSavedCard::query()
            ->where('client_id', $client->id)
            ->whereNull('detached_at')
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (BookingSavedCard $row): array => [
                'id' => (int) $row->id,
                'brand' => (string) ($row->brand ?? ''),
                'last4' => (string) ($row->last4 ?? ''),
                'exp_month' => $row->exp_month !== null ? (int) $row->exp_month : null,
                'exp_year' => $row->exp_year !== null ? (int) $row->exp_year : null,
                'is_default' => (bool) $row->is_default,
            ])
            ->values()
            ->all();
    }

    private function resolveClientFromBookingActor(mixed $actor): ?Client
    {
        if (! ($actor instanceof User) || ! $actor->isBookingClient()) {
            return null;
        }

        return $actor->loadMissing('client')->client;
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
            Log::warning('Stripe Customer::create falhou para wallet do cliente.', [
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

    private function configureStripeSdk(): void
    {
        Stripe::setApiKey((string) config('stripe.secret'));

        $apiVersion = config('stripe.api_version');
        if (! is_string($apiVersion) || $apiVersion === '') {
            return;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}\.[a-zA-Z0-9_]+$/', $apiVersion)) {
            return;
        }

        Stripe::setApiVersion($apiVersion);
    }
}
