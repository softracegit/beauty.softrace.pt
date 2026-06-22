<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Support\ApplicableFees;
use App\Support\SaleTechnicianAttribution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendasReportService
{
    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     */
    public function reportQuery(array $filters): Builder
    {
        $today = now()->startOfDay();
        $firstDayOfMonth = now()->copy()->startOfMonth();

        $desde = $filters['desde'] ?? null;
        $ate = $filters['ate'] ?? null;
        $cliente = $filters['cliente'] ?? null;
        $servico = $filters['servico'] ?? null;
        $tecnico = $filters['tecnico'] ?? null;
        $estado = (string) ($filters['estado'] ?? '');

        if (! $desde) {
            $desde = $firstDayOfMonth->toDateString();
        }
        if (! $ate) {
            $ate = $today->toDateString();
        }

        $q = Sale::query()
            ->where('store_id', current_store_id())
            ->whereHas('calendarEvent', function (Builder $cq) {
                $cq->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            });

        if (in_array($estado, [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO], true)) {
            $q->where('invoice_status', $estado);
        } else {
            $q->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO]);
        }

        $q->whereDate('data_emissao', '>=', $desde);
        $q->whereDate('data_emissao', '<=', $ate);

        if ($cliente) {
            $q->where('client_id', $cliente);
        }
        if ($servico) {
            $q->whereHas('items', function (Builder $iq) use ($servico) {
                $iq->where('tipo', SaleItem::TIPO_SERVICO)
                    ->where('service_id', $servico);
            });
        }
        if ($tecnico) {
            $q->where(function (Builder $outer) use ($tecnico): void {
                $outer->whereHas('calendarEvent', fn (Builder $cq) => $cq
                    ->where('store_id', current_store_id())
                    ->where('user_id', $tecnico))
                    ->orWhereHas('items.calendarEventService.event', fn (Builder $cq) => $cq
                        ->where('store_id', current_store_id())
                        ->where('user_id', $tecnico));
            });
        }

        return $q;
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{total_valor: float, total_valor_com_gorjeta: float, total_gorjeta: float, total_taxas: float, total_absoluto: float, num_vendas: int, total_servicos: int, total_desconto: float, total_divida: float}
     */
    public function totaisRodape(Collection $lines): array
    {
        $totalValor = 0.0;
        $totalDesconto = 0.0;
        $totalGorjeta = 0.0;
        $totalTaxas = 0.0;
        $totalServicos = 0;
        $activeLines = $lines->filter(fn ($linha): bool => empty($linha->is_anulado));
        foreach ($activeLines as $linha) {
            $totalValor += (float) $linha->valor;
            $totalDesconto += (float) ($linha->desconto ?? 0);
            $totalGorjeta += (float) ($linha->gorjeta ?? 0);
            $totalTaxas += (float) ($linha->taxas ?? 0);
            $isServiceLine = ($linha->tipo_item ?? null) === SaleItem::TIPO_SERVICO
                || ($linha->tipo_item ?? null) === 'resumo';
            if ($isServiceLine) {
                $totalServicos += (int) ($linha->quantidade ?? 0);
            }
        }

        $numVendas = $activeLines->pluck('sale_id')->unique()->count();

        $totalDivida = 0.0;
        $vistoSale = [];
        foreach ($activeLines as $linha) {
            $sid = (int) $linha->sale_id;
            if (isset($vistoSale[$sid])) {
                continue;
            }
            $vistoSale[$sid] = true;
            $totalDivida += (float) ($linha->pendente ?? 0);
        }

        $totalValorComGorjeta = round($totalValor + $totalGorjeta, 2);
        $totalTaxasRounded = round($totalTaxas, 2);

        return [
            'total_valor' => round($totalValor, 2),
            'total_valor_com_gorjeta' => $totalValorComGorjeta,
            'total_gorjeta' => round($totalGorjeta, 2),
            'total_taxas' => $totalTaxasRounded,
            'total_absoluto' => round($totalValorComGorjeta + $totalTaxasRounded, 2),
            'num_vendas' => $numVendas,
            'total_servicos' => $totalServicos,
            'total_desconto' => round($totalDesconto, 2),
            'total_divida' => round($totalDivida, 2),
        ];
    }

    /**
     * Uma linha por técnica/marcação na venda (vendas consolidadas geram várias linhas, mesma fatura).
     *
     * @return Collection<int, object>
     */
    public function resumoCollection(Collection $sales, ?string $vendasServico, ?string $vendasTecnico = null): Collection
    {
        $servicoFilter = $vendasServico !== null && $vendasServico !== '' ? (int) $vendasServico : null;
        $tecnicoFilter = $vendasTecnico !== null && $vendasTecnico !== '' ? (int) $vendasTecnico : null;

        return $sales->flatMap(function (Sale $sale) use ($servicoFilter, $tecnicoFilter) {
            $slices = SaleTechnicianAttribution::slicesForSale($sale, $servicoFilter, $tecnicoFilter);
            if ($slices->isEmpty()) {
                return [];
            }

            $client = $sale->client;

            return $slices->map(function (array $slice) use ($sale, $client) {
                /** @var Collection<int, SaleItem> $serviceItems */
                $serviceItems = $slice['service_items'];
                $serviceLabels = $serviceItems
                    ->map(fn (SaleItem $item) => $this->serviceLabelForSaleItem($item))
                    ->filter(fn (?string $label) => $label !== null && trim($label) !== '')
                    ->values();

                $subtitulo = $sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO ? 'Pagamento em loja' : null;
                if ($slice['is_consolidated']) {
                    $subtitulo = $subtitulo !== null
                        ? $subtitulo.' · venda consolidada'
                        : 'Venda consolidada';
                }

                return (object) [
                    'sale' => $sale,
                    'sale_id' => $sale->id,
                    'sale_status' => $sale->status,
                    'is_anulado' => $sale->isAnulado(),
                    'credit_note_pdf_url' => $sale->hasCreditNote() ? route('sales.credit-note.pdf', $sale) : null,
                    'invoice_status' => $sale->invoice_status ?? Sale::INVOICE_STATUS_FATURADO,
                    'data' => $sale->data_emissao,
                    'numero_fatura' => $sale->numero_fatura,
                    'cliente' => $client?->name ?? '—',
                    'nif' => $client?->nif ?? '',
                    'tecnico' => $slice['user_name'],
                    ...$this->servicoColunaPayload(
                        $this->servicoNomesFromLabels($serviceLabels),
                        $subtitulo
                    ),
                    'quantidade' => (int) $serviceItems->count(),
                    'valor' => (float) $slice['valor'],
                    'taxas' => (float) $slice['taxas'],
                    'tipo_item' => 'resumo',
                    'desconto' => (float) $slice['desconto'],
                    'gorjeta' => (float) $slice['gorjeta'],
                    'pendente' => $slice['is_anchor'] ? $this->pendenteForSale($sale) : 0.0,
                    'calendar_event_id' => $slice['calendar_event_id'],
                ];
            })->all();
        })->values();
    }

    public function servicosOpts(): Collection
    {
        return Service::query()
            ->forStore(current_store_id())
            ->join('sale_items', 'services.id', '=', 'sale_items.service_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', current_store_id())
            ->where('calendar_events.store_id', current_store_id())
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();
    }

    public function clientesOpts(): Collection
    {
        return Client::query()
            ->forStore(current_store_id())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('sales')
                    ->join('calendar_events', 'calendar_events.id', '=', 'sales.calendar_event_id')
                    ->whereColumn('sales.client_id', 'clients.id')
                    ->where('sales.store_id', current_store_id())
                    ->where('calendar_events.store_id', current_store_id())
                    ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, SaleItem>  $serviceItems
     */
    private function valorLinhaForSale(Sale $sale, Collection $serviceItems): float
    {
        if ($sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO) {
            $gorjeta = (float) ($sale->gorjeta ?? 0);
            $taxas = (float) $sale->items
                ->where('tipo', SaleItem::TIPO_TAXA)
                ->sum(fn (SaleItem $item) => (float) $item->subtotal);

            return max(0, round((float) $sale->total - $gorjeta - $taxas, 2));
        }

        return (float) $serviceItems->sum(fn (SaleItem $item) => (float) $item->subtotal);
    }

    private function pendenteForSale(Sale $sale): float
    {
        if ($sale->status === Sale::STATUS_ANULADO) {
            return 0.0;
        }

        if ($sale->scope === Sale::SCOPE_BOOKING_RESERVA) {
            $eventId = (int) ($sale->calendar_event_id ?? 0);
            if ($eventId <= 0 || $this->marcacaoHasActiveCaixaSale($eventId)) {
                return 0.0;
            }

            $event = $sale->calendarEvent;
            if (! $event) {
                return 0.0;
            }

            if (! $event->relationLoaded('eventServiceItems')) {
                $event->load(['eventServiceItems.extras.extra']);
            }

            $chargeSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);

            return ApplicableFees::amountDueCashFromEventId($eventId, $chargeSubtotal);
        }

        return $sale->amountDue();
    }

    private function marcacaoHasActiveCaixaSale(int $calendarEventId): bool
    {
        if ($calendarEventId <= 0) {
            return false;
        }

        $saleIds = Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('id')
            ->merge(
                DB::table('sale_calendar_events')
                    ->where('calendar_event_id', $calendarEventId)
                    ->pluck('sale_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($saleIds === []) {
            return false;
        }

        return Sale::query()
            ->whereIn('id', $saleIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
            ->exists();
    }

    private function serviceLabelForSaleItem(SaleItem $item): string
    {
        $optionName = trim((string) ($item->calendarEventService?->option_name ?? ''));
        if ($optionName !== '') {
            return $optionName;
        }

        $descricao = trim((string) $item->descricao);
        if ($descricao !== '') {
            return $descricao;
        }

        if ($item->service) {
            return (string) $item->service->name;
        }

        return (string) ($item->calendarEventService?->service?->name ?? '—');
    }

    /**
     * @param  Collection<int, string>  $serviceLabels
     * @return array{servico: string, servico_nomes: string, servico_subtitulo: ?string}
     */
    private function servicoColunaForSale(Sale $sale, Collection $serviceLabels): array
    {
        $nomesFromEvent = $this->servicoNomesFromEvent($sale);

        if ($sale->scope === Sale::SCOPE_BOOKING_RESERVA) {
            $nomes = $nomesFromEvent ?? $this->prepagamentoNomesFromDescricao($serviceLabels->first() ?? '');

            return $this->servicoColunaPayload($nomes, 'Pré-pagamento');
        }

        if ($sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO) {
            $nomes = $nomesFromEvent ?? $this->servicoNomesFromLabels($serviceLabels);

            return $this->servicoColunaPayload($nomes, 'Pagamento em loja');
        }

        $nomes = $this->servicoNomesFromLabels($serviceLabels);

        return $this->servicoColunaPayload($nomes, null);
    }

    /**
     * @return array{servico: string, servico_nomes: string, servico_subtitulo: ?string}
     */
    private function servicoColunaPayload(string $nomes, ?string $subtitulo): array
    {
        $nomes = trim($nomes);
        $subtitulo = $subtitulo !== null && trim($subtitulo) !== '' ? trim($subtitulo) : null;
        $servico = $subtitulo !== null && $nomes !== ''
            ? $nomes."\n".$subtitulo
            : ($nomes !== '' ? $nomes : ($subtitulo ?? '—'));

        return [
            'servico' => $servico,
            'servico_nomes' => $nomes !== '' ? $nomes : '—',
            'servico_subtitulo' => $subtitulo,
        ];
    }

    private function servicoNomesFromEvent(Sale $sale): ?string
    {
        $event = $sale->calendarEvent;
        if (! $event) {
            return null;
        }

        $names = $event->eventServiceItems
            ->map(function ($es) {
                $optionName = trim((string) ($es->option_name ?? ''));

                return $optionName !== '' ? $optionName : ($es->service?->name ?? null);
            })
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        return $names->implode(' / ');
    }

    /**
     * @param  Collection<int, string>  $serviceLabels
     */
    private function servicoNomesFromLabels(Collection $serviceLabels): string
    {
        return $serviceLabels
            ->map(fn (string $label): string => preg_replace('/^\s*\d{1,2}:\d{2}\s*-\s*/u', '', $label) ?: $label)
            ->filter(fn (string $label) => trim($label) !== '')
            ->implode(', ');
    }

    private function prepagamentoNomesFromDescricao(string $descricao): string
    {
        $label = trim($descricao);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s*[—–-]\s*pr[eé]-pagamento\s*(\([^)]*\))?\s*$/iu', '', $label) ?? $label;
        $label = trim($label);

        if (preg_match('/^(.+?)\s+-\s+(.+)$/u', $label, $m)) {
            return trim($m[2]);
        }

        return $label;
    }
}
