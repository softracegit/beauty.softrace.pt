<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Vendas — {{ config('app.name') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; }
    .header { margin-bottom: 12px; }
    .header h1 { font-size: 15px; margin: 0 0 6px 0; }
    .header .meta { font-size: 7px; color: #666; margin-bottom: 8px; }
    .filtros { font-size: 7px; color: #555; margin-bottom: 10px; line-height: 1.45; }
    .filtros strong { color: #333; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 4px 5px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
    th { background: #f5f5f5; font-size: 6px; text-transform: uppercase; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .footer { margin-top: 14px; font-size: 7px; color: #888; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Relatório de vendas (linhas)</h1>
    <div class="meta">{{ $appName ?? config('app.name') }} · Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $totalLinhas }} linha(s)</div>
  </div>

  @if(!empty($filtrosLinhas))
    <div class="filtros">
      <strong>Filtros aplicados:</strong><br>
      @foreach($filtrosLinhas as $linha)
        {{ $linha }}@if(!$loop->last)<br>@endif
      @endforeach
    </div>
  @endif

  <table>
    <thead>
      <tr>
        <th style="width:8%">Data</th>
        <th style="width:10%">N.º fatura</th>
        <th style="width:14%">Cliente</th>
        <th style="width:9%">NIF</th>
        <th style="width:11%">Técnico</th>
        <th style="width:22%">Serviço</th>
        <th class="text-center" style="width:5%">Qtd</th>
        <th class="text-end" style="width:8%">Valor</th>
        <th style="width:8%">Estado</th>
      </tr>
    </thead>
    <tbody>
      @foreach($linhas as $linha)
        <tr>
          <td>{{ $linha->data->format('d/m/Y') }}</td>
          <td>{{ $linha->numero_fatura }}</td>
          <td>{{ $linha->cliente }}</td>
          <td>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '—' }}</td>
          <td>{{ $linha->tecnico }}</td>
          <td>{{ $linha->servico }}@if(($linha->tipo_item ?? '') === \App\Models\SaleItem::TIPO_EXTRA) (Extra)@endif</td>
          <td class="text-center">{{ $linha->quantidade }}</td>
          <td class="text-end">{{ number_format($linha->valor, 2, ',', ' ') }} €</td>
          <td>{{ \App\Models\Sale::statuses()[$linha->sale_status] ?? $linha->sale_status }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if($linhas->isEmpty())
    <p style="margin-top:10px; font-size:8px; color:#666;">Nenhum registo para os filtros selecionados.</p>
  @endif

  <div class="footer">
    Uma linha por item faturado. Use o PDF individual de cada fatura no CRM para o documento oficial.
  </div>
</body>
</html>
