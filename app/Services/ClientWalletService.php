<?php

namespace App\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ClientWalletService
{
    /** @var list<string> */
    public const TRANSACTION_TYPES = [
        ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY,
        ClientWalletTransaction::TYPE_DEBIT_BOOKING_CHECKOUT,
        ClientWalletTransaction::TYPE_DEBIT_POS_CHECKOUT,
        ClientWalletTransaction::TYPE_CREDIT_MANUAL_TOPUP,
        ClientWalletTransaction::TYPE_CREDIT_CASHBACK,
        ClientWalletTransaction::TYPE_CREDIT_ADMIN_ADJUSTMENT,
        ClientWalletTransaction::TYPE_DEBIT_ADMIN_ADJUSTMENT,
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function credit(
        Client $client,
        int $amountCents,
        string $type,
        string $idempotencyKey,
        array $context = [],
    ): ClientWalletTransaction {
        $this->assertPositiveAmount($amountCents);
        $this->assertValidType($type);

        return $this->applyMovement($client, $amountCents, $type, $idempotencyKey, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debit(
        Client $client,
        int $amountCents,
        string $type,
        string $idempotencyKey,
        array $context = [],
    ): ClientWalletTransaction {
        $this->assertPositiveAmount($amountCents);
        $this->assertValidType($type);

        return $this->applyMovement($client, -$amountCents, $type, $idempotencyKey, $context);
    }

    public function getBalanceCents(Client $client): int
    {
        return max(0, (int) ($client->wallet_balance_cents ?? 0));
    }

    public function getLedgerBalanceCents(Client $client): int
    {
        $sum = (int) ClientWalletTransaction::query()
            ->where('client_id', $client->id)
            ->sum('amount_cents');

        return max(0, $sum);
    }

    public function assertIdempotency(string $key): ?ClientWalletTransaction
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return ClientWalletTransaction::query()
            ->where('idempotency_key', $key)
            ->first();
    }

    public static function idempotencyKeyForCancellation(int $calendarEventId): string
    {
        return 'cancel_credit:event:'.$calendarEventId;
    }

    public static function idempotencyKeyForBookingDebit(int $bookingId): string
    {
        return 'booking_debit:booking:'.$bookingId;
    }

    public static function idempotencyKeyForPosDebit(int $saleId): string
    {
        return 'pos_debit:sale:'.$saleId;
    }

    public static function idempotencyKeyForAgendaDeposit(int $calendarEventId): string
    {
        return 'agenda_deposit:event:'.$calendarEventId;
    }

    public function reconcileClient(Client $client, bool $fix = false): WalletReconciliationResult
    {
        $ledgerBalance = $this->getLedgerBalanceCents($client);
        $cachedBalance = $this->getBalanceCents($client);
        $wasFixed = false;

        if ($fix && $ledgerBalance !== $cachedBalance) {
            $previousCached = $cachedBalance;

            Client::query()
                ->whereKey($client->id)
                ->update(['wallet_balance_cents' => $ledgerBalance]);

            $cachedBalance = $ledgerBalance;
            $wasFixed = true;

            Log::warning('wallet.reconcile.fixed', [
                'client_id' => $client->id,
                'store_id' => $client->store_id,
                'ledger_balance_cents' => $ledgerBalance,
                'previous_cached_balance_cents' => $previousCached,
            ]);
        }

        return new WalletReconciliationResult(
            clientId: (int) $client->id,
            storeId: (int) $client->store_id,
            cachedBalanceCents: $cachedBalance,
            ledgerBalanceCents: $ledgerBalance,
            wasFixed: $wasFixed,
        );
    }

    /**
     * @return Collection<int, WalletReconciliationResult>
     */
    public function reconcileAll(?int $storeId = null, bool $fix = false): Collection
    {
        $results = collect();

        $query = Client::query()->select(['id', 'store_id', 'wallet_balance_cents']);
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $query->orderBy('id')->chunkById(200, function ($clients) use ($fix, $results): void {
            foreach ($clients as $client) {
                $result = $this->reconcileClient($client, $fix);
                if (! $result->isConsistent()) {
                    $results->push($result);
                }
            }
        });

        return $results;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyMovement(
        Client $client,
        int $signedAmountCents,
        string $type,
        string $idempotencyKey,
        array $context,
    ): ClientWalletTransaction {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $existing = $this->assertIdempotency($idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($client, $signedAmountCents, $type, $idempotencyKey, $context): ClientWalletTransaction {
                $locked = Client::query()->whereKey($client->id)->lockForUpdate()->firstOrFail();

                $existingInTx = ClientWalletTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingInTx !== null) {
                    return $existingInTx;
                }

                $balance = max(0, (int) ($locked->wallet_balance_cents ?? 0));
                $newBalance = $balance + $signedAmountCents;

                if ($newBalance < 0) {
                    throw new InsufficientWalletBalanceException(
                        clientId: (int) $locked->id,
                        requestedCents: abs($signedAmountCents),
                        availableCents: $balance,
                    );
                }

                $locked->forceFill(['wallet_balance_cents' => $newBalance])->save();

                $transaction = ClientWalletTransaction::query()->create([
                    'store_id' => (int) ($locked->store_id ?? $client->store_id),
                    'client_id' => $locked->id,
                    'amount_cents' => $signedAmountCents,
                    'balance_after_cents' => $newBalance,
                    'type' => $type,
                    'idempotency_key' => $idempotencyKey,
                    'calendar_event_id' => $context['calendar_event_id'] ?? null,
                    'booking_id' => $context['booking_id'] ?? null,
                    'sale_id' => $context['sale_id'] ?? null,
                    'payment_id' => $context['payment_id'] ?? null,
                    'description' => (string) ($context['description'] ?? ''),
                    'metadata' => $context['metadata'] ?? null,
                    'created_by_type' => (string) ($context['created_by_type'] ?? ClientWalletTransaction::CREATED_BY_SYSTEM),
                    'created_by_user_id' => $context['created_by_user_id'] ?? null,
                ]);

                Log::info('wallet.transaction', [
                    'transaction_id' => $transaction->id,
                    'client_id' => $locked->id,
                    'store_id' => $transaction->store_id,
                    'type' => $type,
                    'amount_cents' => $signedAmountCents,
                    'balance_after_cents' => $newBalance,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $transaction;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateIdempotencyKey($e)) {
                $existing = $this->assertIdempotency($idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    private function assertPositiveAmount(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amount_cents must be positive.');
        }
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, self::TRANSACTION_TYPES, true)) {
            throw new InvalidArgumentException('Invalid wallet transaction type: '.$type);
        }
    }

    private function isDuplicateIdempotencyKey(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return in_array($code, ['1062', '23000', '23505'], true);
    }
}
