<?php

namespace App\Services;

use App\Exceptions\CashRegisterNotOpenException;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Support\DateTimeDisplay;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CashRegisterService
{
    public function __construct(
        private readonly CashRegisterActivityLogger $activityLogger,
    ) {}

    public function getOpenSession(int $storeId): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->forStore($storeId)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->orderByDesc('id')
            ->first();
    }

    public function assertOpenSession(int $storeId): void
    {
        if ($this->getOpenSession($storeId) === null) {
            throw new CashRegisterNotOpenException;
        }
    }

    public function sessionIdForNewStoreSale(int $storeId): ?int
    {
        return $this->getOpenSession($storeId)?->id;
    }

    /**
     * Pré-pagamentos booking online sem sessão, após o último fecho (exclui anteriores à 1.ª abertura).
     *
     * @return array{
     *   has_previous_close: bool,
     *   count: int,
     *   total: float,
     *   sales: list<array{amount: float, payment_method: string, payment_label: string, created_at_label: string}>
     * }
     */
    public function pendingBookingOrphansPreview(int $storeId): array
    {
        $sales = $this->pendingBookingOrphanSalesQuery($storeId)
            ->orderBy('created_at')
            ->get();

        $labels = Sale::paymentMethods();

        return [
            'has_previous_close' => $this->firstClosedSession($storeId) !== null,
            'count' => $sales->count(),
            'total' => round($sales->sum(fn (Sale $sale) => $sale->effectiveAmountPaid()), 2),
            'sales' => $sales->map(function (Sale $sale) use ($labels): array {
                $method = (string) ($sale->payment_method ?? Sale::PAYMENT_OUTRO);

                return [
                    'amount' => round($sale->effectiveAmountPaid(), 2),
                    'payment_method' => $method,
                    'payment_label' => $labels[$method] ?? $method,
                    'created_at_label' => DateTimeDisplay::business($sale->created_at),
                ];
            })->values()->all(),
        ];
    }

    public function openSession(User $user, int $storeId, float $openingFloatEur): CashRegisterSession
    {
        if ($openingFloatEur < 0) {
            throw new InvalidArgumentException('O fundo de maneio não pode ser negativo.');
        }

        if ($this->getOpenSession($storeId) !== null) {
            throw new RuntimeException('Já existe uma sessão de caixa aberta nesta loja.');
        }

        return DB::transaction(function () use ($user, $storeId, $openingFloatEur): CashRegisterSession {
            $session = CashRegisterSession::query()->create([
                'store_id' => $storeId,
                'opened_by_user_id' => $user->id,
                'opened_at' => StoreBusinessTime::nowUtcForStore($storeId),
                'opening_float_cents' => $this->eurosToCents($openingFloatEur),
                'status' => CashRegisterSession::STATUS_OPEN,
            ]);

            $this->assignPendingBookingOrphansToSession($session);

            $session = $session->fresh(['openedBy']);

            $this->activityLogger->logOpened(
                $session,
                $user,
                $this->countBookingSalesAssignedToSession($session),
            );

            return $session;
        });
    }

    /**
     * @return array{
     *   by_method: array<string, float>,
     *   methods: list<array{method: string, label: string, amount: float, informational: bool}>,
     *   cash_sales_total: float,
     *   expected_cash_in_drawer: float,
     *   sales_count: int,
     *   assigned_booking_orphans_count: int
     * }
     */
    public function buildExpectedSummary(CashRegisterSession $session): array
    {
        $periodEnd = $session->closed_at ?? StoreBusinessTime::nowUtcForStore((int) $session->store_id);

        if ($session->isOpen()) {
            $this->assignPendingBookingOrphansToSession($session);
            $this->attachInSessionSalesWithoutSessionId($session, $periodEnd);
        }

        $rows = $this->salesForSessionSummaryQuery($session, $periodEnd)
            ->select([
                'payment_method',
                DB::raw('SUM(COALESCE(valor_pago, total)) as amount'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->groupBy('payment_method')
            ->get();

        $byMethod = [];
        $methods = [];
        $salesCount = 0;

        foreach ($rows as $row) {
            $method = (string) ($row->payment_method ?? Sale::PAYMENT_OUTRO);
            if ($method === '') {
                $method = Sale::PAYMENT_OUTRO;
            }
            $amount = round((float) $row->amount, 2);
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;
            $salesCount += (int) $row->sales_count;
        }

        foreach (Sale::paymentMethods() as $method => $label) {
            if (! array_key_exists($method, $byMethod)) {
                continue;
            }
            $methods[] = [
                'method' => $method,
                'label' => $label,
                'amount' => round($byMethod[$method], 2),
                'informational' => $method === Sale::PAYMENT_CREDITOS_CARTEIRA,
            ];
        }

        usort($methods, function (array $a, array $b): int {
            if ($a['informational'] !== $b['informational']) {
                return $a['informational'] <=> $b['informational'];
            }

            return strcmp($a['label'], $b['label']);
        });

        $cashSalesTotal = round($byMethod[Sale::PAYMENT_DINHEIRO] ?? 0, 2);
        $expectedCash = round($session->openingFloatEur() + $cashSalesTotal, 2);

        return [
            'by_method' => $byMethod,
            'methods' => $methods,
            'cash_sales_total' => $cashSalesTotal,
            'expected_cash_in_drawer' => $expectedCash,
            'sales_count' => $salesCount,
            'assigned_booking_orphans_count' => $this->countBookingSalesAssignedToSession($session),
            'booking_prepayments' => $this->bookingPrepaymentsSummaryForSession($session),
        ];
    }

    public function closeSession(
        CashRegisterSession $session,
        User $user,
        float $countedCashEur,
        ?string $notes = null,
    ): CashRegisterSession {
        if (! $session->isOpen()) {
            throw new RuntimeException('Esta sessão de caixa já está fechada.');
        }

        if ($countedCashEur < 0) {
            throw new InvalidArgumentException('O dinheiro contado não pode ser negativo.');
        }

        $periodEnd = StoreBusinessTime::nowUtcForStore((int) $session->store_id);
        $this->assignPendingBookingOrphansToSession($session);
        $this->attachInSessionSalesWithoutSessionId($session, $periodEnd);

        $session->update(['closed_at' => $periodEnd]);

        $summary = $this->buildExpectedSummary($session->fresh());

        $countedCents = $this->eurosToCents($countedCashEur);
        $expectedCents = $this->eurosToCents($summary['expected_cash_in_drawer']);
        $differenceCents = $countedCents - $expectedCents;

        $session->update([
            'closed_by_user_id' => $user->id,
            'closing_cash_counted_cents' => $countedCents,
            'closing_summary' => array_merge($summary, [
                'counted_cash' => round($countedCashEur, 2),
                'cash_difference' => round($differenceCents / 100, 2),
                'assigned_booking_orphans_count' => $summary['assigned_booking_orphans_count'],
            ]),
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'status' => CashRegisterSession::STATUS_CLOSED,
        ]);

        $closed = $session->fresh(['openedBy', 'closedBy']);

        $this->activityLogger->logClosed($closed, $user);

        return $closed;
    }

    public function userCanManageCashRegister(User $user): bool
    {
        return $user->canManageCashRegister();
    }

    public function assignPendingBookingOrphansToSession(CashRegisterSession $session): int
    {
        return $this->pendingBookingOrphanSalesQuery((int) $session->store_id)
            ->update(['cash_register_session_id' => $session->id]);
    }

    /**
     * @return array{count: int, total: float, sales: list<array{amount: float, payment_label: string, created_at_label: string}>}
     */
    private function bookingPrepaymentsSummaryForSession(CashRegisterSession $session): array
    {
        $sales = Sale::query()
            ->forStore((int) $session->store_id)
            ->where('cash_register_session_id', $session->id)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->where('status', Sale::STATUS_PAGO)
            ->orderBy('created_at')
            ->get();

        $labels = Sale::paymentMethods();

        return [
            'count' => $sales->count(),
            'total' => round($sales->sum(fn (Sale $sale) => $sale->effectiveAmountPaid()), 2),
            'sales' => $sales->map(function (Sale $sale) use ($labels): array {
                $method = (string) ($sale->payment_method ?? Sale::PAYMENT_OUTRO);

                return [
                    'amount' => round($sale->effectiveAmountPaid(), 2),
                    'payment_label' => $labels[$method] ?? $method,
                    'created_at_label' => DateTimeDisplay::business($sale->created_at),
                ];
            })->values()->all(),
        ];
    }

    public function countBookingSalesAssignedToSession(CashRegisterSession $session): int
    {
        return Sale::query()
            ->forStore((int) $session->store_id)
            ->where('cash_register_session_id', $session->id)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->count();
    }

    /**
     * Vendas da sessão: atribuídas explicitamente + cobranças em loja ainda sem ID na hora do fecho.
     */
    private function salesForSessionSummaryQuery(CashRegisterSession $session, Carbon $periodEnd): Builder
    {
        return Sale::query()
            ->forStore((int) $session->store_id)
            ->where('status', Sale::STATUS_PAGO)
            ->excludingInvoiceDrafts()
            ->where(function (Builder $query) use ($session, $periodEnd): void {
                $query->where('cash_register_session_id', $session->id)
                    ->orWhere(function (Builder $inner) use ($session, $periodEnd): void {
                        $openedAt = StoreBusinessTime::toUtcInstant($session->opened_at);
                        $endAt = StoreBusinessTime::toUtcInstant($periodEnd);
                        if ($openedAt === null || $endAt === null) {
                            $inner->whereRaw('0 = 1');

                            return;
                        }
                        $inner->whereNull('cash_register_session_id')
                            ->where('created_at', '>=', StoreBusinessTime::lowerBoundForQuery($openedAt))
                            ->where('created_at', '<=', $endAt);
                    });
            });
    }

    private function attachInSessionSalesWithoutSessionId(CashRegisterSession $session, Carbon $periodEnd): void
    {
        $openedAt = StoreBusinessTime::toUtcInstant($session->opened_at);
        $endAt = StoreBusinessTime::toUtcInstant($periodEnd);
        if ($openedAt === null || $endAt === null) {
            return;
        }

        Sale::query()
            ->forStore((int) $session->store_id)
            ->where('status', Sale::STATUS_PAGO)
            ->excludingInvoiceDrafts()
            ->whereNull('cash_register_session_id')
            ->where('created_at', '>=', StoreBusinessTime::lowerBoundForQuery($openedAt))
            ->where('created_at', '<=', $endAt)
            ->update(['cash_register_session_id' => $session->id]);
    }

    private function sessionCloseInstant(CashRegisterSession $session): ?DateTimeInterface
    {
        return $session->closed_at
            ?? $session->updated_at
            ?? $session->opened_at;
    }

    /**
     * Primeiro fecho da loja: vendas anteriores não entram na caixa; posteriores sem sessão são órfãs.
     */
    private function firstClosedSession(int $storeId): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->forStore($storeId)
            ->where('status', CashRegisterSession::STATUS_CLOSED)
            ->orderByRaw('COALESCE(closed_at, updated_at) ASC')
            ->orderBy('id')
            ->first();
    }

    private function orphanExclusionBoundaryUtc(int $storeId): ?Carbon
    {
        $firstClosed = $this->firstClosedSession($storeId);
        if ($firstClosed === null) {
            return null;
        }

        return StoreBusinessTime::toUtcInstant($this->sessionCloseInstant($firstClosed));
    }

    private function pendingBookingOrphanSalesQuery(int $storeId): Builder
    {
        $query = Sale::query()
            ->forStore($storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->whereNull('cash_register_session_id');

        $boundary = StoreBusinessTime::lowerBoundForQuery($this->orphanExclusionBoundaryUtc($storeId));
        if ($boundary === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('created_at', '>=', $boundary);
    }

    private function eurosToCents(float $eur): int
    {
        return (int) round(max(0, $eur) * 100);
    }
}
