<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Fatura {{ $sale->numero_fatura }}</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Fatura</h1>
        <div class="sub">Nº {{ $sale->numero_fatura }} · Data de emissão: {{ $sale->data_emissao?->format('d/m/Y') }}</div>
    </div>

    @if($sale->client)
    <div class="client-block">
        <strong>Cliente</strong>
        {{ $sale->client->name }}<br>
        @if($sale->client->email){{ $sale->client->email }}<br>@endif
        @if($sale->client->nif)NIF: {{ $sale->client->nif }}@endif
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
            @foreach($sale->items as $item)
            <tr>
                <td>{{ $item->descricao }}</td>
                <td class="text-right">{{ $item->quantidade }}</td>
                <td class="text-right">{{ number_format($item->preco_unitario, 2, ',', ' ') }} €</td>
                <td class="text-right">{{ number_format($item->subtotal, 2, ',', ' ') }} €</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            @php $subtotal = $sale->items->sum('subtotal'); @endphp
            @if($sale->gorjeta && $sale->gorjeta > 0)
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ number_format($subtotal, 2, ',', ' ') }} €</td>
            </tr>
            <tr>
                <td>Gorjeta</td>
                <td class="text-right">{{ number_format($sale->gorjeta, 2, ',', ' ') }} €</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ number_format($sale->total, 2, ',', ' ') }} €</td>
            </tr>
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
