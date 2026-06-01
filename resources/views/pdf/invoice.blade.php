<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Fatura-recibo {{ $sale->numero_fatura }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        .header { margin-bottom: 24px; }
        .header h1 { font-size: 18px; margin: 0 0 4px 0; }
        .header .sub { font-size: 10px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .totals { margin-top: 20px; }
        .totals table { max-width: 280px; margin-left: auto; }
        .totals .total-row { font-weight: bold; font-size: 13px; }
        .client-block { margin-bottom: 20px; }
        .client-block strong { display: block; margin-bottom: 4px; }
        .footer { margin-top: 32px; font-size: 9px; color: #888; }
        .draft-banner {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #664d03;
            padding: 10px 12px;
            margin-bottom: 16px;
            font-weight: 700;
            font-size: 11px;
            text-align: center;
        }
    </style>
</head>
<body>
    @if($sale->isInvoiceDraft())
    <div class="draft-banner">RASCUNHO — Documento provisório (não comunicado à Vendus)</div>
    @endif
    @php
        $amountDue = method_exists($sale, 'amountDue') ? (float) $sale->amountDue() : max(0, (float) $sale->total - (float) ($sale->valor_pago ?? 0));
        $isPartial = ($sale->scope ?? null) === \App\Models\Sale::SCOPE_BOOKING_RESERVA;
        $partialPaid = (float) ($sale->valor_pago ?? 0);
        $isSettlement = ($sale->scope ?? null) === \App\Models\Sale::SCOPE_CAIXA_LIQUIDACAO;
        $walletAppliedEur = 0.0;
        if ($isPartial && $sale->calendar_event_id) {
            $booking = \App\Models\Booking::query()
                ->where('calendar_event_id', $sale->calendar_event_id)
                ->where('payment_status', \App\Models\Booking::PAYMENT_PAID)
                ->orderByDesc('id')
                ->first(['wallet_applied_cents']);

            if ($booking) {
                $walletAppliedEur = round(max(0, (int) ($booking->wallet_applied_cents ?? 0)) / 100, 2);
            }
        }
        $previousPaid = 0.0;
        if ($isSettlement && $sale->calendar_event_id) {
            $previousPaid = (float) \App\Models\Sale::query()
                ->where('calendar_event_id', $sale->calendar_event_id)
                ->where('status', '!=', \App\Models\Sale::STATUS_ANULADO)
                ->where('id', '<', $sale->id)
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)'));
        }
        $subtotal = (float) $sale->items->sum('subtotal');
        $serviceGrossTotal = 0.0;
        if ($sale->calendarEvent) {
            foreach ($sale->calendarEvent->eventServiceItems as $esi) {
                $serviceGrossTotal += (float) ($esi->price ?? 0);
                foreach ($esi->extras as $ex) {
                    $serviceGrossTotal += (float) ($ex->price ?? $ex->extra?->price ?? 0);
                }
            }
        }
        if ($serviceGrossTotal <= 0.00001) {
            $serviceGrossTotal = $isSettlement ? max($subtotal, $sale->total + $previousPaid) : max((float) $sale->total, $partialPaid);
        }
        $serviceGrossTotal = round($serviceGrossTotal, 2);
        $creditsDiscount = $walletAppliedEur > 0.00001
            ? round(min($walletAppliedEur, max(0.0, $serviceGrossTotal - $partialPaid)), 2)
            : 0.0;
        $totalWithCredits = round($partialPaid + $creditsDiscount, 2);
        $remainingAfterDeposit = max(0.0, round($serviceGrossTotal - $totalWithCredits, 2));
    @endphp
    <div class="header">
        <h1>Fatura-recibo</h1>
        <div class="sub">Nº {{ $sale->numero_fatura }} · Data de emissão: {{ $sale->data_emissao?->format('d/m/Y') }}</div>
    </div>

    @if($sale->client)
    <div class="client-block">
        <strong>Cliente</strong>
        {{ $sale->client->name }}<br>
        @if($sale->client->email){{ $sale->client->email }}<br>@endif
        @if($sale->issue_without_fiscal_id ?? false)
            Consumidor final (sem contribuinte neste documento)
        @elseif($sale->client->nif)
            NIF: {{ $sale->client->nif }}
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="text-right">Qtd</th>
                <th class="text-right">Preço unit.</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @if($isPartial)
                <tr>
                    <td>{{ $sale->invoiceDisplayDescription($sale->items->first()?->descricao) }}</td>
                    <td class="text-right">1</td>
                    <td class="text-right">{{ number_format($partialPaid, 2, ',', ' ') }} €</td>
                    <td class="text-right">{{ number_format($partialPaid, 2, ',', ' ') }} €</td>
                </tr>
            @elseif($isSettlement)
                @foreach($sale->items as $item)
                <tr>
                    <td>{{ $sale->invoiceDisplayDescription($item->descricao) }}</td>
                    <td class="text-right">{{ $item->quantidade }}</td>
                    <td class="text-right">{{ number_format($item->preco_unitario, 2, ',', ' ') }} €</td>
                    <td class="text-right">{{ number_format($item->subtotal, 2, ',', ' ') }} €</td>
                </tr>
                @endforeach
            @else
                @foreach($sale->items as $item)
                <tr>
                    <td>{{ $sale->invoiceDisplayDescription($item->descricao) }}</td>
                    <td class="text-right">{{ $item->quantidade }}</td>
                    <td class="text-right">{{ number_format($item->preco_unitario, 2, ',', ' ') }} €</td>
                    <td class="text-right">{{ number_format($item->subtotal, 2, ',', ' ') }} €</td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="totals">
        <table>
            @if($isPartial)
                <tr class="total-row">
                    <td>Valor pago</td>
                    <td class="text-right">{{ number_format($partialPaid, 2, ',', ' ') }} €</td>
                </tr>
                @if($creditsDiscount > 0.00001)
                    <tr>
                        <td>Créditos (desconto)</td>
                        <td class="text-right">-{{ number_format($creditsDiscount, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>Total pré-pagamento</td>
                    <td class="text-right">{{ number_format($totalWithCredits, 2, ',', ' ') }} €</td>
                </tr>
                @if($remainingAfterDeposit > 0.00001)
                    <tr>
                        <td>Valor em falta (dia da marcação)</td>
                        <td class="text-right">{{ number_format($remainingAfterDeposit, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
            @elseif($isSettlement)
                <tr>
                    <td>Valor total do serviço</td>
                    <td class="text-right">{{ number_format($serviceGrossTotal, 2, ',', ' ') }} €</td>
                </tr>
                @if($previousPaid > 0.00001)
                <tr>
                    <td>Valor do pré-pagamento</td>
                    <td class="text-right">-{{ number_format($previousPaid, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                @if($sale->gorjeta && $sale->gorjeta > 0)
                <tr>
                    <td>Gorjeta</td>
                    <td class="text-right">{{ number_format($sale->gorjeta, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                @if($sale->desconto && $sale->desconto > 0)
                <tr>
                    <td>Desconto</td>
                    <td class="text-right">-{{ number_format($sale->desconto, 2, ',', ' ') }} €</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Total liquidação</td>
                    <td class="text-right">{{ number_format($sale->total, 2, ',', ' ') }} €</td>
                </tr>
            @else
                <tr class="total-row">
                    <td>Total</td>
                    <td class="text-right">{{ number_format($sale->total, 2, ',', ' ') }} €</td>
                </tr>
            @endif
            @if($sale->iva_total)
            <tr>
                <td>IVA</td>
                <td class="text-right">{{ number_format($sale->iva_total, 2, ',', ' ') }} €</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="footer" style="margin-top: 40px;">
        Meio de pagamento: {{ \App\Models\Sale::paymentMethods()[$sale->payment_method] ?? $sale->payment_method }}<br>
        Documento gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
