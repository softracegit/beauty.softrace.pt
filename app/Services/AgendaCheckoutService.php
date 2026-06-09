<?php

namespace App\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ApplicableFees;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgendaCheckoutService
{
    public function __construct(
        private readonly ClientWalletService $walletService,
        private readonly CashRegisterService $cashRegisterService,
    ) {}

    /**
     * @param  list<int>  $eventIds
     * @return Collection<int, CalendarEvent>
     */
    public function resolveEventsForCheckout(CalendarEvent $anchor, array $eventIds): Collection
    {
        $ids = collect($eventIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            $ids = collect([(int) $anchor->id]);
        }
        if (! $ids->contains((int) $anchor->id)) {
            $ids->prepend((int) $anchor->id);
        }

        $events = CalendarEvent::query()
            ->forStore(current_store_id())
            ->whereIn('id', $ids->all())
            ->with(['eventServiceItems.service', 'eventServiceItems.extras.extra', 'client'])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        if ($events->count() !== $ids->count()) {
            abort(422, 'Nem todas as marcações selecionadas são válidas.');
        }

        $clientId = (int) ($anchor->client_id ?? 0);
        if ($clientId <= 0) {
            abort(422, 'A marcação não tem cliente associado.');
        }
        $day = CarbonImmutable::parse((string) $anchor->start_at, config('app.timezone'))->toDateString();
        foreach ($events as $event) {
            if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
                abort(422, 'Apenas marcações podem ir a checkout.');
            }
            if ($event->isMarcacaoStatusLocked()) {
                abort(422, 'Esta marcação não pode ser paga.');
            }
            if ((int) ($event->client_id ?? 0) !== $clientId) {
                abort(422, 'As marcações selecionadas devem pertencer ao mesmo cliente.');
            }
            $eventDay = CarbonImmutable::parse((string) $event->start_at, config('app.timezone'))->toDateString();
            if ($eventDay !== $day) {
                abort(422, 'As marcações selecionadas devem ser do mesmo dia civil.');
            }
            $activeConsolidated = DB::table('sale_calendar_events as sce')
                ->join('sales as s', 's.id', '=', 'sce.sale_id')
                ->where('sce.calendar_event_id', (int) $event->id)
                ->where('s.status', '!=', Sale::STATUS_ANULADO)
                ->where('s.scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->exists();
            if ($activeConsolidated) {
                abort(422, 'Uma das marcações selecionadas já está associada a uma venda ativa.');
            }
        }

        return $events->values();
    }

    /**
     * @param  Collection<int, CalendarEvent>  $events
     * @return array{items:list<array<string,mixed>>,subtotal:float,already_paid:float}
     */
    public function buildCheckoutContext(Collection $events): array
    {
        $items = [];
        $feeItems = [];
        $sortOrder = 0;
        $subtotal = 0.0;
        $alreadyPaid = 0.0;
        $seenFeeIds = [];
        $dedupeFees = $events->count() > 1;
        foreach ($events as $event) {
            $prefix = $events->count() > 1 ? trim((string) optional($event->start_at)->format('H:i')).' - ' : '';
            foreach ($event->eventServiceItems as $esi) {
                $price = (float) $esi->price;
                $items[] = [
                    'tipo' => SaleItem::TIPO_SERVICO,
                    'calendar_event_id' => (int) $event->id,
                    'calendar_event_service_id' => $esi->id,
                    'service_id' => $esi->service_id,
                    'extra_id' => null,
                    'descricao' => $prefix.($esi->service?->name ?? 'Serviço'),
                    'quantidade' => 1,
                    'preco_unitario' => $price,
                    'subtotal' => $price,
                    'sort_order' => $sortOrder++,
                ];
                $subtotal += $price;

                foreach ($esi->extras as $ex) {
                    $extraPrice = (float) ($ex->price ?? $ex->extra?->price ?? 0);
                    $items[] = [
                        'tipo' => SaleItem::TIPO_EXTRA,
                        'calendar_event_id' => (int) $event->id,
                        'calendar_event_service_id' => $esi->id,
                        'service_id' => null,
                        'extra_id' => $ex->extra_id,
                        'descricao' => $prefix.'+ '.($ex->extra?->name ?? 'Extra'),
                        'quantidade' => 1,
                        'preco_unitario' => $extraPrice,
                        'subtotal' => $extraPrice,
                        'sort_order' => $sortOrder++,
                    ];
                    $subtotal += $extraPrice;
                }
            }
            foreach (ApplicableFees::checkoutFeeLineItems($event, $sortOrder) as $feeLine) {
                $feeId = (int) ($feeLine['fee_id'] ?? 0);
                if ($dedupeFees && $feeId > 0 && isset($seenFeeIds[$feeId])) {
                    continue;
                }
                $feeItems[] = array_merge($feeLine, [
                    'calendar_event_id' => (int) $event->id,
                    'calendar_event_service_id' => null,
                    'service_id' => null,
                    'extra_id' => null,
                    'descricao' => $prefix.(string) ($feeLine['descricao'] ?? 'Taxa'),
                ]);
                if ($feeId > 0) {
                    $seenFeeIds[$feeId] = true;
                }
                $sortOrder = ((int) ($feeLine['sort_order'] ?? $sortOrder)) + 1;
                $subtotal += (float) ($feeLine['subtotal'] ?? 0);
            }

            $alreadyPaid += ApplicableFees::marcacaoMoneyTowardSubtotal((int) $event->id);
        }

        if (! empty($feeItems)) {
            $items = array_merge($items, $feeItems);
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'already_paid' => round($alreadyPaid, 2),
        ];
    }

    /**
     * @param  Collection<int, CalendarEvent>  $events
     * @param  array<int,array<string,mixed>>  $items
     */
    public function persistSale(
        Collection $events,
        Client $client,
        array $items,
        string $invoiceStatus,
        string $paymentMethod,
        float $total,
        float $valorPago,
        float $gorjeta,
        float $descontoDoc,
        bool $issueWithoutFiscalId,
    ): Sale {
        $anchor = $events->first();
        $storeId = (int) $anchor->store_id;
        $now = now();
        $numeroFatura = Sale::nextNumeroFatura((int) $now->format('Y'), (int) $now->format('m'), $storeId);

        try {
            return DB::transaction(function () use (
                $events,
                $client,
                $items,
                $invoiceStatus,
                $paymentMethod,
                $total,
                $valorPago,
                $gorjeta,
                $descontoDoc,
                $issueWithoutFiscalId,
                $storeId,
                $now,
                $numeroFatura
            ): Sale {
                $sale = Sale::create([
                    'store_id' => $storeId,
                    'cash_register_session_id' => $this->cashRegisterService->sessionIdForNewStoreSale($storeId),
                    'calendar_event_id' => (int) $events->first()->id,
                    'client_id' => $client->id,
                    'numero_fatura' => $numeroFatura,
                    'data_emissao' => $now->toDateString(),
                    'total' => $total,
                    'gorjeta' => $gorjeta > 0 ? round($gorjeta, 2) : null,
                    'desconto' => $descontoDoc > 0 ? round($descontoDoc, 2) : null,
                    'valor_pago' => round($valorPago, 2),
                    'iva_total' => null,
                    'payment_method' => $paymentMethod,
                    'scope' => Sale::SCOPE_CAIXA_LIQUIDACAO,
                    'status' => Sale::STATUS_PAGO,
                    'invoice_status' => $invoiceStatus,
                    'issue_without_fiscal_id' => $issueWithoutFiscalId,
                ]);

                foreach ($items as $idx => $row) {
                    $qty = (int) $row['quantidade'];
                    $preco = (float) $row['preco_unitario'];
                    $bruto = round($qty * $preco, 2);
                    $descLinha = isset($row['desconto']) ? (float) $row['desconto'] : 0;
                    $descLinha = min(max(0, $descLinha), $bruto);
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'tipo' => $row['tipo'],
                        'calendar_event_service_id' => $row['calendar_event_service_id'] ?? null,
                        'service_id' => $row['service_id'] ?? null,
                        'extra_id' => $row['extra_id'] ?? null,
                        'fee_id' => $row['fee_id'] ?? null,
                        'descricao' => $row['descricao'],
                        'quantidade' => $qty,
                        'preco_unitario' => $preco,
                        'subtotal' => round($bruto - $descLinha, 2),
                        'desconto' => $descLinha > 0 ? round($descLinha, 2) : null,
                        'sort_order' => $idx,
                    ]);
                }

                if ($paymentMethod === Sale::PAYMENT_CREDITOS_CARTEIRA) {
                    $debitCents = (int) round($total * 100);
                    $this->walletService->debit(
                        $client,
                        $debitCents,
                        ClientWalletTransaction::TYPE_DEBIT_POS_CHECKOUT,
                        ClientWalletService::idempotencyKeyForPosDebit((int) $sale->id),
                        [
                            'sale_id' => $sale->id,
                            'calendar_event_id' => (int) $events->first()->id,
                            'description' => 'Pagamento em loja (fatura '.($sale->numero_fatura ?? '').')',
                            'created_by_type' => ClientWalletTransaction::CREATED_BY_STAFF,
                            'created_by_user_id' => auth()->id(),
                        ],
                    );
                }

                $shareCents = $events->count() > 0 ? (int) floor(((int) round($total * 100)) / $events->count()) : 0;
                $remainingCents = (int) round($total * 100);
                foreach ($events as $index => $event) {
                    $eventCents = $index === ($events->count() - 1) ? $remainingCents : $shareCents;
                    $remainingCents -= $eventCents;

                    DB::table('sale_calendar_events')->updateOrInsert(
                        [
                            'sale_id' => $sale->id,
                            'calendar_event_id' => (int) $event->id,
                        ],
                        [
                            'amount_settled_cents' => max(0, $eventCents),
                            'is_primary' => $index === 0,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $event->update(['status' => CalendarEvent::STATUS_COMPLETO]);
                }

                return $sale;
            });
        } catch (InsufficientWalletBalanceException $e) {
            throw $e;
        }
    }
}
